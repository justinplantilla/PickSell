<?php

namespace App\Pickrush\Http\Controllers;

use App\Pickrush\Enums\StaffRole;
use App\Pickrush\Models\AuditLog;
use App\Pickrush\Models\Driver;
use App\Pickrush\Models\Manifest;
use App\Pickrush\Models\Vehicle;
use App\Pickrush\Services\OperationRefused;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * The hub's company trucks and the drivers Pickrush hired for them. A station keeps one truck, a
 * province hub up to three; each truck has a default driver. Trips are dispatched from this list.
 */
class FleetController extends PortalController
{
    public function index()
    {
        $hub = $this->hub();
        $out = Manifest::where('from_hub_id', $hub->id)->where('status', 'dispatched')->get(['vehicle_id', 'driver_id']);

        return view('pickrush.fleet.index', [
            'vehicles' => Vehicle::with('defaultDriver')->orderBy('plate_no')->get(),
            'drivers' => Driver::orderBy('full_name')->get(),
            'vehiclesOut' => $out->pluck('vehicle_id')->flip(),
            'driversOut' => $out->pluck('driver_id')->flip(),
            'limit' => Vehicle::limitFor($hub),
            'company' => $this->staff()->company?->name,
            'licenseCodes' => Driver::LICENSE_CODES,
            'canManage' => $this->staff()->hasRole(StaffRole::HubManager),
        ]);
    }

    // Trucks ----------------------------------------------------------------------------------

    public function store(Request $request)
    {
        $this->managerOnly();
        $data = $this->validatedVehicle($request);

        return $this->attempt(function () use ($data) {
            DB::transaction(function () use ($data) {
                $this->checkLimit();

                return Vehicle::create($data + ['hub_id' => $this->hub()->id, 'registered_owner' => $this->staff()->company?->name, 'is_active' => true]);
            });
        }, "{$data['plate_no']} added to the fleet.");
    }

    public function update(Request $request, Vehicle $vehicle)
    {
        $this->managerOnly();
        $data = $this->validatedVehicle($request, $vehicle) + ['is_active' => $request->boolean('is_active')];

        return $this->attempt(function () use ($vehicle, $data) {
            DB::transaction(function () use ($vehicle, $data) {
                if ($data['is_active'] && ! $vehicle->is_active) {
                    $this->checkLimit();
                }
                $vehicle->update($data);
            });
        }, "{$vehicle->plate_no} saved.");
    }

    // Drivers ---------------------------------------------------------------------------------

    public function storeDriver(Request $request)
    {
        $this->managerOnly();
        $data = $this->validatedDriver($request);

        return $this->attempt(function () use ($request, $data) {
            $driver = Driver::create($data + ['hub_id' => $this->hub()->id, 'is_active' => true]);
            $driver->update($this->storeLicense($request, $driver));
        }, "{$data['first_name']} {$data['last_name']} is registered as a driver.");
    }

    public function updateDriver(Request $request, Driver $driver)
    {
        $this->managerOnly();
        $data = $this->validatedDriver($request, $driver) + ['is_active' => $request->boolean('is_active')];

        return $this->attempt(fn () => $driver->update($data + $this->storeLicense($request, $driver)), "{$driver->full_name} saved.");
    }

    /** The license photo, for the hub's own staff (another hub's driver is a 404 by the hub scope). */
    public function license(Driver $driver, string $side)
    {
        $path = match ($side) {
            'front' => $driver->license_front_path,
            'back' => $driver->license_back_path,
            default => null,
        };
        abort_unless($path && Storage::disk('local')->exists($path), 404);
        AuditLog::record($driver, 'viewed');

        return Storage::disk('local')->response($path);
    }

    // ------------------------------------------------------------------------------------------

    private function validatedVehicle(Request $request, ?Vehicle $vehicle = null): array
    {
        $data = $request->validate([
            'plate_no' => ['required', 'string', 'max:20', Rule::unique('pr_vehicles', 'plate_no')->ignore($vehicle?->id)],
            'vehicle_type' => 'required|in:truck,van',
            'capacity_parcels' => 'nullable|integer|min:1|max:100000',
            'default_driver_id' => ['nullable', 'integer', Rule::exists('pr_drivers', 'id')->where('hub_id', $this->hub()->id)],
        ]);
        $data['plate_no'] = strtoupper(trim($data['plate_no']));

        return $data;
    }

    /** The same formats the rider application asks for (name, +63 mobile, license no., expiry, photos). */
    private function validatedDriver(Request $request, ?Driver $driver = null): array
    {
        $request->merge(['license_no' => strtoupper(trim((string) $request->input('license_no')))]);
        $image = 'file|mimes:jpg,jpeg,png,heic,webp|max:8192';
        $data = $request->validate([
            'first_name' => 'required|string|min:2|max:60',
            'last_name' => 'required|string|min:2|max:60',
            'birthdate' => 'required|date|before_or_equal:-18 years',
            // The 10 digits after +63; stored as 09XXXXXXXXX.
            'mobile' => ['required', 'regex:/^9\d{9}$/', function ($attribute, $value, $fail) use ($driver) {
                $taken = Driver::withoutGlobalScopes()->where('mobile_no', '0'.$value)
                    ->when($driver, fn ($q) => $q->whereKeyNot($driver->id))->exists();
                if ($taken) {
                    $fail('This mobile number is already registered.');
                }
            }],
            'license_no' => ['required', 'regex:/^[A-Z]\d{2}-\d{2}-\d{6}$/', Rule::unique('pr_drivers', 'license_no')->ignore($driver?->id)],
            'license_codes' => 'required|array|min:1',
            'license_codes.*' => ['string', Rule::in(Driver::LICENSE_CODES)],
            'license_expiry' => 'required|date|after:today',
            // Both sides are required when registering; on an update they only replace the old photo.
            'license_front' => ($driver ? 'nullable|' : 'required|').$image,
            'license_back' => ($driver ? 'nullable|' : 'required|').$image,
        ], [
            'birthdate.before_or_equal' => 'The driver must be at least 18 years old.',
            'mobile.regex' => 'Enter a valid 10-digit number.',
            'license_no.regex' => 'Format must be A00-00-000000.',
            'license_no.unique' => 'This license no. is already registered.',
            'license_expiry.after' => 'The license is expired. Please use a valid license.',
        ]);

        return [
            'first_name' => trim($data['first_name']),
            'last_name' => trim($data['last_name']),
            'birthdate' => $data['birthdate'],
            'mobile_no' => '0'.$data['mobile'],
            'license_no' => $data['license_no'],
            'license_codes' => implode(',', array_values(array_intersect(Driver::LICENSE_CODES, $data['license_codes']))),
            'license_expiry' => $data['license_expiry'],
        ];
    }

    /** @return array<string, string> the license photo columns that were uploaded */
    private function storeLicense(Request $request, Driver $driver): array
    {
        $paths = [];
        foreach (['front', 'back'] as $side) {
            if ($file = $request->file("license_{$side}")) {
                $paths["license_{$side}_path"] = $file->store("pickrush/drivers/{$driver->id}", 'local');   // private disk
            }
        }

        return $paths;
    }

    private function checkLimit(): void
    {
        $hub = $this->hub();
        $limit = Vehicle::limitFor($hub);
        if (Vehicle::where('hub_id', $hub->id)->where('is_active', true)->lockForUpdate()->count() >= $limit) {
            $kind = $hub->isStation() ? 'A station hub' : 'A province hub';
            throw new OperationRefused("{$kind} keeps at most {$limit} active ".($limit === 1 ? 'truck' : 'trucks').'. Deactivate one first.');
        }
    }

    private function managerOnly(): void
    {
        abort_unless($this->staff()->hasRole(StaffRole::HubManager), 403, 'Only the hub manager manages the fleet.');
    }
}
