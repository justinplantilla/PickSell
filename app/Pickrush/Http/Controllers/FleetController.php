<?php

namespace App\Pickrush\Http\Controllers;

use App\Pickrush\Enums\StaffRole;
use App\Pickrush\Models\Manifest;
use App\Pickrush\Models\Vehicle;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** The hub's trucks and vans with their usual drivers; picked from the list when a trip is dispatched. */
class FleetController extends PortalController
{
    public function index()
    {
        $vehicles = Vehicle::orderBy('plate_no')->get();

        return view('pickrush.fleet.index', [
            'vehicles' => $vehicles,
            'onTrip' => Manifest::where('from_hub_id', $this->hub()->id)->where('status', 'dispatched')->pluck('vehicle_plate')->flip(),
            'canManage' => $this->staff()->hasRole(StaffRole::HubManager),
        ]);
    }

    public function store(Request $request)
    {
        $this->managerOnly();
        $data = $this->validated($request);

        return $this->attempt(fn () => Vehicle::create($data + ['hub_id' => $this->hub()->id, 'is_active' => true]), "{$data['plate_no']} added to the fleet.");
    }

    public function update(Request $request, Vehicle $vehicle)
    {
        $this->managerOnly();
        $data = $this->validated($request, $vehicle) + ['is_active' => $request->boolean('is_active')];

        return $this->attempt(fn () => $vehicle->update($data), "{$vehicle->plate_no} saved.");
    }

    private function validated(Request $request, ?Vehicle $vehicle = null): array
    {
        $data = $request->validate([
            'plate_no' => ['required', 'string', 'max:20', Rule::unique('pr_vehicles', 'plate_no')->ignore($vehicle?->id)],
            'vehicle_type' => 'required|in:truck,van,motorcycle,car',
            'capacity_parcels' => 'nullable|integer|min:1|max:100000',
            'driver_name' => 'nullable|string|max:120',
            'driver_mobile' => ['nullable', 'regex:/^09\d{9}$/'],
        ]);
        $data['plate_no'] = strtoupper(trim($data['plate_no']));

        return $data;
    }

    private function managerOnly(): void
    {
        abort_unless($this->staff()->hasRole(StaffRole::HubManager), 403, 'Only the hub manager manages the fleet.');
    }
}
