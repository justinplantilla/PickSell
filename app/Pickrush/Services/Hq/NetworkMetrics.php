<?php

namespace App\Pickrush\Services\Hq;

use App\Pickrush\Enums\ParcelStatus;
use App\Pickrush\Models\Assignment;
use App\Pickrush\Models\CodRemittance;
use App\Pickrush\Models\Hub;
use App\Pickrush\Models\HubRemittance;
use App\Pickrush\Models\Parcel;
use App\Pickrush\Models\ParcelEvent;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Network figures for HQ, read live from the event log. Success rate counts parcels that finished
 * their journey in the period: delivered against delivered plus returned to the seller.
 */
class NetworkMetrics
{
    /** @return array<string, mixed> */
    public function summary(CarbonInterface $from, CarbonInterface $to): array
    {
        $finished = fn (ParcelStatus $status) => ParcelEvent::where('status', $status)->whereBetween('occurred_at', [$from, $to])->distinct()->count('parcel_id');
        $delivered = $finished(ParcelStatus::Delivered);
        $returned = $finished(ParcelStatus::ReturnedToSender);
        $deliveredParcels = Parcel::withoutGlobalScopes()->whereBetween('delivered_at', [$from, $to])->get(['created_at', 'delivered_at', 'attempt_count']);

        return [
            'booked' => Parcel::withoutGlobalScopes()->whereBetween('created_at', [$from, $to])->count(),
            'delivered' => $delivered,
            'returned' => $returned,
            'failed_attempts' => $finished(ParcelStatus::DeliveryFailed),
            'in_transit' => Parcel::withoutGlobalScopes()->whereIn('current_status', [ParcelStatus::InTransitToProvince, ParcelStatus::InTransitToStation])->count(),
            'in_network' => Parcel::withoutGlobalScopes()->whereNotIn('current_status', [ParcelStatus::Delivered, ParcelStatus::ReturnedToSender, ParcelStatus::Cancelled, ParcelStatus::Lost])->count(),
            'success_rate' => self::rate($delivered, $delivered + $returned),
            'first_attempt_rate' => self::rate($deliveredParcels->where('attempt_count', 0)->count(), $deliveredParcels->count()),
            'avg_delivery_hours' => $deliveredParcels->isEmpty() ? null
                : round($deliveredParcels->avg(fn ($p) => $p->created_at->diffInMinutes($p->delivered_at) / 60), 1),
            'cod_collected' => (float) Assignment::where('type', 'delivery')->where('status', 'completed')->whereBetween('completed_at', [$from, $to])->sum('cod_collected'),
            'cod_remitted' => (float) HubRemittance::withoutGlobalScopes()->where('status', 'confirmed')->whereBetween('confirmed_at', [$from, $to])->sum('remitted_amount'),
        ];
    }

    /**
     * Station ranking by delivery success in the period, best first.
     *
     * @return Collection<int, object{hub: Hub, delivered: int, returned: int, failed: int, success_rate: ?float}>
     */
    public function hubRanking(CarbonInterface $from, CarbonInterface $to): Collection
    {
        $counts = ParcelEvent::whereBetween('occurred_at', [$from, $to])
            ->whereIn('status', [ParcelStatus::Delivered, ParcelStatus::ReturnedToSender, ParcelStatus::DeliveryFailed])
            ->whereNotNull('hub_id')
            ->selectRaw('hub_id, status, count(distinct parcel_id) as total')->groupBy('hub_id', 'status')->toBase()->get()
            ->groupBy('hub_id');

        return Hub::stations()->whereIn('id', $counts->keys())->get()
            ->map(function (Hub $hub) use ($counts) {
                $row = $counts[$hub->id]->pluck('total', 'status');
                $delivered = (int) ($row[ParcelStatus::Delivered->value] ?? 0);
                $returned = (int) ($row[ParcelStatus::ReturnedToSender->value] ?? 0);

                return (object) [
                    'hub' => $hub,
                    'delivered' => $delivered,
                    'returned' => $returned,
                    'failed' => (int) ($row[ParcelStatus::DeliveryFailed->value] ?? 0),
                    'success_rate' => self::rate($delivered, $delivered + $returned),
                ];
            })
            ->sortByDesc(fn ($row) => [$row->success_rate ?? -1, $row->delivered])->values();
    }

    /** @return Collection<string, int> failure reason => attempts */
    public function failureReasons(CarbonInterface $from, CarbonInterface $to, ?int $stationId = null): Collection
    {
        return Assignment::where('status', 'failed')->whereBetween('completed_at', [$from, $to])
            ->when($stationId, fn ($q) => $q->whereIn('rider_id', \App\Pickrush\Models\Rider::withoutGlobalScopes()->where('station_hub_id', $stationId)->select('id')))
            ->selectRaw('fail_reason, count(*) as total')->groupBy('fail_reason')->orderByDesc('total')
            ->toBase()->pluck('total', 'fail_reason')->map(fn ($n) => (int) $n);
    }

    /** Cash the network holds that has not reached the company yet. */
    public function cashOutstanding(): array
    {
        return [
            'at_stations' => (float) CodRemittance::withoutGlobalScopes()->where('status', 'reconciled')->whereNull('hub_remittance_id')->sum('received_amount'),
            'with_riders' => (float) Assignment::where('type', 'delivery')->where('status', 'completed')->whereNull('cod_remittance_id')->sum('cod_collected'),
            'submitted_unconfirmed' => (float) HubRemittance::withoutGlobalScopes()->where('status', 'submitted')->sum('remitted_amount'),
        ];
    }

    public static function rate(int $part, int $whole): ?float
    {
        return $whole > 0 ? round($part / $whole * 100, 1) : null;
    }
}
