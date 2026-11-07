<?php

namespace App\Pickrush\Support;

use App\Pickrush\Enums\ParcelStatus;
use App\Pickrush\Models\CodRemittance;
use App\Pickrush\Models\Manifest;
use App\Pickrush\Models\Parcel;
use App\Pickrush\Models\ParcelException;
use App\Pickrush\Models\Rider;
use App\Pickrush\Models\Staff;
use App\Pickrush\Services\Station\DispatchService;

/**
 * Counts shown beside sidebar links in the hub portals: work waiting on that page right now.
 * Keyed by the link's route; a tab with nothing waiting is left out.
 */
final class NavBadges
{
    /** @return array<string, int> */
    public static function for(Staff $staff): array
    {
        $hub = $staff->hub;
        if (! $hub || $staff->isCompany()) {
            return [];
        }
        $arriving = fn () => Manifest::where('to_hub_id', $hub->id)->whereIn('status', ['dispatched', 'received'])->count();
        $loading = fn () => Manifest::where('from_hub_id', $hub->id)->where('status', 'draft')->count();
        $exceptions = fn () => ParcelException::where('hub_id', $hub->id)->where('status', 'open')->count();

        $counts = $hub->isStation() ? [
            'pickrush.station.receiving' => $arriving(),
            'pickrush.station.dispatch' => self::dispatchQueue($hub->id),
            'pickrush.station.failed' => Parcel::where('current_hub_id', $hub->id)->where('current_status', ParcelStatus::DeliveryFailed)->count(),
            'pickrush.station.trips.index' => $loading(),
            'pickrush.station.riders.index' => Rider::where('station_hub_id', $hub->id)->whereIn('status', ['pending', 'under_review'])->count(),
            'pickrush.station.cash' => CodRemittance::where('station_hub_id', $hub->id)->where('status', 'disputed')->count(),
            'pickrush.exceptions.index' => $exceptions(),
        ] : [
            'pickrush.province.inbound' => $arriving(),
            'pickrush.province.sorting' => Parcel::where('current_hub_id', $hub->id)->where('current_status', ParcelStatus::AtProvinceHub)->whereNull('sorted_at')->count(),
            'pickrush.province.trips.index' => $loading(),
            'pickrush.exceptions.index' => $exceptions(),
        ];

        return array_filter($counts);
    }

    /** Parcels waiting for a rider, as the Dispatch page lists them (DispatchService::taskFor). */
    private static function dispatchQueue(int $stationId): int
    {
        return Parcel::where(fn ($q) => $q
            ->where(fn ($p) => $p->where('origin_hub_id', $stationId)->where('current_status', ParcelStatus::WaitingPickup))
            ->orWhere(fn ($d) => $d->where('current_hub_id', $stationId)
                ->whereIn('current_status', [ParcelStatus::ArrivedAtStation, ParcelStatus::AtOriginHub, ParcelStatus::DeliveryFailed, ParcelStatus::ReturningToSender])
                ->where(fn ($here) => $here->where(fn ($r) => $r->where('is_return', true)->where('origin_hub_id', $stationId))
                    ->orWhere(fn ($r) => $r->where('is_return', false)->where('dest_hub_id', $stationId)))))
            ->whereDoesntHave('openAssignment')
            ->whereDoesntHave('assignments', fn ($q) => DispatchService::stillWithRider($q))
            ->count();
    }
}
