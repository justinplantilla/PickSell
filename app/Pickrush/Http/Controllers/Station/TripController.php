<?php

namespace App\Pickrush\Http\Controllers\Station;

use App\Pickrush\Http\Controllers\ManagesTrips;
use App\Pickrush\Models\Manifest;
use App\Pickrush\Services\ManifestService;

/**
 * Trips between this station and its province hub: build and dispatch outbound trips (first-mile
 * parcels and returns), and check inbound trips parcel by parcel.
 */
class TripController extends StationController
{
    use ManagesTrips;

    protected function tripRoutePrefix(): string
    {
        return 'pickrush.station.trips';
    }

    public function index(ManifestService $manifests)
    {
        $station = $this->station();
        $province = $station->parent;

        return view('pickrush.station.trips', [
            'province' => $province,
            'ready' => $manifests->readyFor($station, $province)->oldest('updated_at')->get(),
            'drafts' => Manifest::where('from_hub_id', $station->id)->where('status', 'draft')->latest()->get(),
            'recent' => Manifest::with('fromHub', 'toHub')->where(fn ($q) => $q->where('from_hub_id', $station->id)->orWhere('to_hub_id', $station->id))
                ->where('status', '!=', 'draft')->latest('updated_at')->limit(30)->get(),
        ]);
    }

    public function store(ManifestService $manifests)
    {
        $manifest = $manifests->create($this->station(), $this->station()->parent, $this->staff());

        return redirect()->route('pickrush.station.trips.show', $manifest)->with('success', "Trip {$manifest->manifest_no} started. Scan the parcels going on it.");
    }
}
