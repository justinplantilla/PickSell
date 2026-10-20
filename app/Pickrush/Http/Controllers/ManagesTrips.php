<?php

namespace App\Pickrush\Http\Controllers;

use App\Pickrush\Models\Manifest;
use App\Pickrush\Models\ManifestParcel;
use App\Pickrush\Models\Vehicle;
use App\Pickrush\Services\ManifestService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Trip actions shared by the station and province portals: load and dispatch an outbound trip,
 * check an inbound one. The using controller names its route prefix.
 */
trait ManagesTrips
{
    abstract protected function tripRoutePrefix(): string;

    public function show(Manifest $manifest, ManifestService $manifests)
    {
        $manifest->load('fromHub', 'toHub', 'items.parcel');

        return view('pickrush.trips.show', [
            'prefix' => $this->tripRoutePrefix(),
            'manifest' => $manifest,
            'outbound' => $manifest->from_hub_id === $this->hub()->id,
            'scanKey' => (string) Str::uuid(),
            'readyCount' => $manifest->status === 'draft' ? $manifests->readyFor($manifest->fromHub, $manifest->toHub)->count() : 0,
            'vehicles' => Vehicle::where('is_active', true)->orderBy('plate_no')->get(),
        ]);
    }

    public function add(Request $request, Manifest $manifest, ManifestService $manifests)
    {
        $this->outboundOnly($manifest);
        $data = $request->validate(['tracking_no' => 'required|string|max:30']);

        return $this->attempt(fn () => $manifests->add($manifest, $data['tracking_no']), fn ($parcel) => "{$parcel->tracking_no} added to the trip.");
    }

    public function addAll(Manifest $manifest, ManifestService $manifests)
    {
        $this->outboundOnly($manifest);

        return $this->attempt(fn () => $manifests->addAllReady($manifest), fn (int $count) => "{$count} parcels added.");
    }

    public function remove(Manifest $manifest, ManifestParcel $item, ManifestService $manifests)
    {
        $this->outboundOnly($manifest);

        return $this->attempt(fn () => $manifests->remove($manifest, $item), 'Parcel taken off the trip.');
    }

    public function dispatch(Request $request, Manifest $manifest, ManifestService $manifests)
    {
        $this->outboundOnly($manifest);
        $data = $request->validate(['vehicle_plate' => 'required|string|max:20', 'driver_name' => 'required|string|max:120']);

        return $this->attempt(
            fn () => $manifests->dispatch($manifest, $data['vehicle_plate'], $data['driver_name'], $this->staff()),
            "Trip {$manifest->manifest_no} dispatched to {$manifest->toHub->name}.",
        );
    }

    public function receive(Request $request, Manifest $manifest, ManifestService $manifests)
    {
        $data = $request->validate(['tracking_no' => 'required|string|max:30', 'scan_key' => 'nullable|string|max:80']);

        return $this->attempt(
            fn () => $manifests->receive($manifest, $data['tracking_no'], $this->staff(), self::scanKey($data)),
            fn ($parcel) => "{$parcel->tracking_no} received.",
        );
    }

    public function close(Manifest $manifest, ManifestService $manifests)
    {
        return $this->attempt(fn () => $manifests->close($manifest, $this->staff()),
            fn (int $missing) => $missing ? "Trip closed. {$missing} parcels are missing; an exception was opened for each." : 'Trip closed. Every parcel arrived.');
    }

    private function outboundOnly(Manifest $manifest): void
    {
        abort_unless($manifest->from_hub_id === $this->hub()->id, 403, 'Only the sending hub edits a trip.');
    }
}
