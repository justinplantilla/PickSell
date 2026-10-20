<?php

namespace App\Pickrush\Http\Controllers\Province;

use App\Pickrush\Enums\ParcelStatus;
use App\Pickrush\Http\Controllers\PortalController;
use App\Pickrush\Models\Manifest;
use App\Pickrush\Models\Parcel;
use App\Pickrush\Models\ParcelEvent;
use App\Pickrush\Models\ParcelException;
use App\Pickrush\Services\Province\Inventory;

/** Sorting center overview: what is coming, what is on the floor, what left, and what is stuck. */
class DashboardController extends PortalController
{
    public function __invoke(Inventory $inventory)
    {
        $hub = $this->hub();
        $since = now()->subDays(7);
        $volume = fn (array $statuses, $from) => ParcelEvent::where('hub_id', $hub->id)->whereIn('status', $statuses)->where('occurred_at', '>=', $from)->distinct()->count('parcel_id');
        $outStatuses = [ParcelStatus::InTransitToProvince, ParcelStatus::InTransitToStation];
        $onHand = $inventory->onHand($hub);

        return view('pickrush.province.dashboard', [
            'hub' => $hub,
            'gaps' => $hub->readinessGaps(),
            'inboundTrips' => Manifest::where('to_hub_id', $hub->id)->where('status', 'dispatched')->count(),
            'onTheWay' => Parcel::where('next_hub_id', $hub->id)->whereIn('current_status', $outStatuses)->count(),
            'onHand' => $onHand->count(),
            'unsorted' => $onHand->where('current_status', ParcelStatus::AtProvinceHub)->whereNull('sorted_at')->count(),
            'onHold' => $onHand->where('current_status', ParcelStatus::OnHold)->count(),
            'aging' => $onHand->filter(fn ($p) => $p->dwell_hours >= Inventory::AGING_HOURS)->count(),
            'openExceptions' => ParcelException::where('status', 'open')->where('hub_id', $hub->id)->count(),
            'inToday' => $volume([ParcelStatus::AtProvinceHub], today()),
            'outToday' => $volume($outStatuses, today()),
            'in7d' => $volume([ParcelStatus::AtProvinceHub], $since),
            'out7d' => $volume($outStatuses, $since),
            'avgDwell' => $inventory->averageDwellHours($hub, $since),
            'tripsToday' => Manifest::where('from_hub_id', $hub->id)->whereDate('dispatched_at', today())->count(),
        ]);
    }
}
