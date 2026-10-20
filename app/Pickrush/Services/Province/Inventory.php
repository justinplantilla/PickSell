<?php

namespace App\Pickrush\Services\Province;

use App\Pickrush\Enums\ParcelStatus;
use App\Pickrush\Models\Hub;
use App\Pickrush\Models\Parcel;
use App\Pickrush\Models\ParcelEvent;
use Illuminate\Support\Collection;

/** What is physically in a hub and for how long (dwell time from its arrival scan). */
class Inventory
{
    /** Parcels on the floor longer than this are aging. */
    public const AGING_HOURS = 24;

    /** @return Collection<int, Parcel> each with arrived_at and dwell_hours */
    public function onHand(Hub $hub): Collection
    {
        $parcels = Parcel::withoutGlobalScopes()->with('nextHub')
            ->where('current_hub_id', $hub->id)
            ->whereIn('current_status', [ParcelStatus::AtOriginHub, ParcelStatus::AtProvinceHub, ParcelStatus::ArrivedAtStation, ParcelStatus::OnHold, ParcelStatus::ReturningToSender, ParcelStatus::DeliveryFailed])
            ->get();

        $arrivals = ParcelEvent::whereIn('parcel_id', $parcels->pluck('id'))->where('hub_id', $hub->id)
            ->whereIn('status', [ParcelStatus::AtOriginHub, ParcelStatus::AtProvinceHub, ParcelStatus::ArrivedAtStation])
            ->selectRaw('parcel_id, max(occurred_at) as arrived_at')->groupBy('parcel_id')->pluck('arrived_at', 'parcel_id');

        return $parcels->each(function (Parcel $parcel) use ($arrivals) {
            $parcel->arrived_at = isset($arrivals[$parcel->id]) ? \Illuminate\Support\Carbon::parse($arrivals[$parcel->id]) : $parcel->updated_at;
            $parcel->dwell_hours = round($parcel->arrived_at->diffInMinutes(now()) / 60, 1);
        })->sortByDesc('dwell_hours')->values();
    }

    /** Average hours between arriving at and leaving the hub, for parcels that left in the period. */
    public function averageDwellHours(Hub $hub, $since): ?float
    {
        $departures = ParcelEvent::where('hub_id', $hub->id)->where('occurred_at', '>=', $since)
            ->whereIn('status', [ParcelStatus::InTransitToProvince, ParcelStatus::InTransitToStation])
            ->get(['parcel_id', 'occurred_at']);
        if ($departures->isEmpty()) {
            return null;
        }
        $arrivals = ParcelEvent::where('hub_id', $hub->id)->whereIn('parcel_id', $departures->pluck('parcel_id'))
            ->whereIn('status', [ParcelStatus::AtProvinceHub, ParcelStatus::AtOriginHub, ParcelStatus::ArrivedAtStation])
            ->get(['parcel_id', 'occurred_at'])->groupBy('parcel_id');

        $hours = $departures->map(function ($departure) use ($arrivals) {
            $arrival = $arrivals->get($departure->parcel_id)?->filter(fn ($a) => $a->occurred_at <= $departure->occurred_at)->sortByDesc('occurred_at')->first();

            return $arrival ? $arrival->occurred_at->diffInMinutes($departure->occurred_at) / 60 : null;
        })->filter(fn ($h) => $h !== null);

        return $hours->isEmpty() ? null : round($hours->avg(), 1);
    }
}
