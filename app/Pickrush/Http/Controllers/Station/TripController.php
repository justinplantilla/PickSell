<?php

namespace App\Pickrush\Http\Controllers\Station;

use App\Pickrush\Models\Manifest;
use App\Pickrush\Models\ManifestParcel;
use App\Pickrush\Services\ManifestService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Trips between this station and its province hub: build and dispatch outbound trips (first-mile
 * parcels and returns), and check inbound trips parcel by parcel.
 */
class TripController extends StationController
{
    public function __construct(private ManifestService $manifests) {}

    public function index()
    {
        $station = $this->station();
        $province = $station->parent;

        return view('pickrush.station.trips', [
            'province' => $province,
            'ready' => $this->manifests->readyFor($station, $province)->oldest('updated_at')->get(),
            'drafts' => Manifest::where('from_hub_id', $station->id)->where('status', 'draft')->latest()->get(),
            'recent' => Manifest::with('fromHub', 'toHub')->where(fn ($q) => $q->where('from_hub_id', $station->id)->orWhere('to_hub_id', $station->id))
                ->where('status', '!=', 'draft')->latest('updated_at')->limit(30)->get(),
        ]);
    }

    public function store()
    {
        $manifest = $this->manifests->create($this->station(), $this->station()->parent, $this->staff());

        return redirect()->route('pickrush.station.trips.show', $manifest)->with('success', "Trip {$manifest->manifest_no} started. Scan the parcels going on it.");
    }

    public function show(Manifest $manifest)
    {
        $manifest->load('fromHub', 'toHub', 'items.parcel');

        return view('pickrush.station.trip', [
            'manifest' => $manifest,
            'outbound' => $manifest->from_hub_id === $this->station()->id,
            'scanKey' => (string) Str::uuid(),
            'readyCount' => $manifest->status === 'draft' ? $this->manifests->readyFor($manifest->fromHub, $manifest->toHub)->count() : 0,
        ]);
    }

    public function add(Request $request, Manifest $manifest)
    {
        $this->outboundOnly($manifest);
        $data = $request->validate(['tracking_no' => 'required|string|max:30']);

        return $this->attempt(fn () => $this->manifests->add($manifest, $data['tracking_no']), fn ($parcel) => "{$parcel->tracking_no} added to the trip.");
    }

    public function addAll(Manifest $manifest)
    {
        $this->outboundOnly($manifest);

        return $this->attempt(fn () => $this->manifests->addAllReady($manifest), fn (int $count) => "{$count} parcels added.");
    }

    public function remove(Manifest $manifest, ManifestParcel $item)
    {
        $this->outboundOnly($manifest);

        return $this->attempt(fn () => $this->manifests->remove($manifest, $item), 'Parcel taken off the trip.');
    }

    public function dispatch(Request $request, Manifest $manifest)
    {
        $this->outboundOnly($manifest);
        $data = $request->validate(['vehicle_plate' => 'required|string|max:20', 'driver_name' => 'required|string|max:120']);

        return $this->attempt(
            fn () => $this->manifests->dispatch($manifest, $data['vehicle_plate'], $data['driver_name'], $this->staff()),
            "Trip {$manifest->manifest_no} dispatched to {$manifest->toHub->name}.",
        );
    }

    public function receive(Request $request, Manifest $manifest)
    {
        $data = $request->validate(['tracking_no' => 'required|string|max:30', 'scan_key' => 'nullable|string|max:80']);

        return $this->attempt(
            fn () => $this->manifests->receive($manifest, $data['tracking_no'], $this->staff(), self::scanKey($data)),
            fn ($parcel) => "{$parcel->tracking_no} received.",
        );
    }

    public function close(Manifest $manifest)
    {
        return $this->attempt(fn () => $this->manifests->close($manifest, $this->staff()),
            fn (int $missing) => $missing ? "Trip closed. {$missing} parcels are missing and were flagged." : 'Trip closed. Every parcel arrived.');
    }

    private function outboundOnly(Manifest $manifest): void
    {
        abort_unless($manifest->from_hub_id === $this->station()->id, 403, 'Only the sending hub edits a trip.');
    }
}
