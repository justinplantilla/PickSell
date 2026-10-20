<?php

namespace App\Pickrush\Http\Controllers\Province;

use App\Pickrush\Http\Controllers\ManagesTrips;
use App\Pickrush\Http\Controllers\PortalController;
use App\Pickrush\Models\Hub;
use App\Pickrush\Models\Manifest;
use App\Pickrush\Services\ManifestService;
use App\Pickrush\Services\Province\Sorting;
use Illuminate\Http\Request;

/**
 * Outbound from the sorting center: one trip per destination, down to this province's stations
 * or across to another province hub (inter-province transfer). Also checks inbound trips.
 */
class TripController extends PortalController
{
    use ManagesTrips;

    protected function tripRoutePrefix(): string
    {
        return 'pickrush.province.trips';
    }

    public function index(ManifestService $manifests)
    {
        $hub = $this->hub();
        $readyByDestination = \App\Pickrush\Models\Parcel::withoutGlobalScopes()->where('current_hub_id', $hub->id)
            ->where('current_status', \App\Pickrush\Enums\ParcelStatus::AtProvinceHub)->whereNotNull('next_hub_id')
            ->selectRaw('next_hub_id, count(*) as total, sum(case when sorted_at is not null then 1 else 0 end) as sorted')
            ->groupBy('next_hub_id')->get()->keyBy('next_hub_id');
        $destinations = Hub::whereIn('id', $readyByDestination->keys())->orderBy('type')->orderBy('name')->get()
            ->map(fn (Hub $to) => (object) [
                'hub' => $to,
                'bin' => Sorting::binFor($to),
                'ready' => (int) $readyByDestination[$to->id]->total,
                'sorted' => (int) $readyByDestination[$to->id]->sorted,
            ]);

        return view('pickrush.province.trips', [
            'destinations' => $destinations,
            'allDestinations' => $hub->childStations()->orderBy('name')->get()
                ->concat(Hub::provinces()->where('id', '!=', $hub->id)->orderBy('name')->get()),
            'drafts' => Manifest::with('toHub')->where('from_hub_id', $hub->id)->where('status', 'draft')->latest()->get(),
            'recent' => Manifest::with('fromHub', 'toHub')->where(fn ($q) => $q->where('from_hub_id', $hub->id)->orWhere('to_hub_id', $hub->id))
                ->where('status', '!=', 'draft')->latest('updated_at')->limit(40)->get(),
        ]);
    }

    public function store(Request $request, ManifestService $manifests)
    {
        $data = $request->validate(['to_hub_id' => 'required|integer|exists:pr_hubs,id']);
        $to = Hub::findOrFail($data['to_hub_id']);
        $hub = $this->hub();
        $allowed = ($to->isStation() && $to->parent_hub_id === $hub->id) || ($to->isProvince() && ! $to->is($hub));
        if (! $allowed) {
            return back()->withErrors(['operation' => 'Trips go to this province\'s stations or to another province hub.']);
        }

        $manifest = $manifests->create($hub, $to, $this->staff());

        return redirect()->route('pickrush.province.trips.show', $manifest)->with('success', "Trip {$manifest->manifest_no} to {$to->name} started.");
    }
}
