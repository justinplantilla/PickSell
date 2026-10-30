<?php

namespace App\Pickrush\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Pickrush\Models\Assignment;
use App\Pickrush\Models\CodRemittance;
use App\Pickrush\Models\Rider;
use App\Pickrush\Models\RiderBatch;
use App\Pickrush\Services\Station\RiderAvailability;
use App\Pickrush\Support\Actor;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;

/**
 * The rider's Earnings page: what finished tasks earned (today, this week, this month, the last
 * 7 days, per batch) and the COD cash they still hold. A pickup or a delivery counts once it is
 * done (picked up / delivered); failed attempts earn nothing. Rates: config pickrush.rider_pay.
 * Payouts are handled by the station outside the app.
 */
class RiderEarningsController extends Controller
{
    public function index(RiderAvailability $availability): JsonResponse
    {
        $rider = Actor::rider();
        $rates = ['pickup' => self::rate('pickup'), 'delivery' => self::rate('delivery')];

        $since = now()->startOfMonth()->min(now()->subDays(6)->startOfDay());
        $done = Assignment::where('rider_id', $rider->id)->where('status', 'completed')
            ->where('completed_at', '>=', $since)->get(['id', 'type', 'batch_id', 'completed_at']);

        $days = collect(range(6, 0))->map(function (int $back) use ($done, $rates) {
            $day = today()->subDays($back);

            return ['date' => $day->toDateString()] + self::sum($done->filter(fn ($t) => $t->completed_at->isSameDay($day)), $rates);
        })->values();

        return response()->json([
            'rates' => $rates,
            'today' => self::sum($done->filter(fn ($t) => $t->completed_at->gte(today())), $rates),
            'week' => self::sum($done->filter(fn ($t) => $t->completed_at->gte(now()->startOfWeek())), $rates),
            'month' => self::sum($done->filter(fn ($t) => $t->completed_at->gte(now()->startOfMonth())), $rates),
            'days' => $days,
            'batches' => $this->batches($rider, $rates),
            'cod' => $this->cod($rider, $availability),
        ]);
    }

    /** @param  Collection<int, Assignment>  $tasks */
    private static function sum(Collection $tasks, array $rates): array
    {
        $pickups = $tasks->where('type', 'pickup')->count();
        $deliveries = $tasks->whereIn('type', ['delivery', 'return'])->count();

        return [
            'earnings' => round($pickups * $rates['pickup'] + $deliveries * $rates['delivery'], 2),
            'pickups' => $pickups,
            'deliveries' => $deliveries,
        ];
    }

    /** The latest finished batches and what each earned. */
    private function batches(Rider $rider, array $rates): array
    {
        $batches = RiderBatch::where('rider_id', $rider->id)->where('status', 'completed')
            ->latest('completed_at')->limit(20)->get();
        $done = Assignment::whereIn('batch_id', $batches->pluck('id'))->where('status', 'completed')
            ->get(['batch_id', 'type'])->groupBy('batch_id');

        return $batches->map(function (RiderBatch $batch) use ($done, $rates) {
            $count = ($done[$batch->id] ?? collect())->count();

            return [
                'id' => $batch->id,
                'batch_no' => $batch->batch_no,
                'type' => $batch->type,
                'completed_at' => $batch->completed_at?->toIso8601String(),
                'parcels' => $count,
                'earned' => round($count * $rates[$batch->isDelivery() ? 'delivery' : 'pickup'], 2),
            ];
        })->values()->all();
    }

    /** COD still with the rider, and what they handed over to the station. */
    private function cod(Rider $rider, RiderAvailability $availability): array
    {
        $last = CodRemittance::withoutGlobalScopes()->where('rider_id', $rider->id)->latest('id')->first();

        return [
            'in_hand' => round($availability->codInHand($rider), 2),
            'limit' => (float) $rider->cod_limit,
            'remitted_this_month' => round((float) CodRemittance::withoutGlobalScopes()->where('rider_id', $rider->id)
                ->where('created_at', '>=', now()->startOfMonth())->sum('received_amount'), 2),
            'last_remittance' => $last ? [
                'amount' => (float) $last->received_amount,
                'at' => $last->created_at->toIso8601String(),
                'status' => $last->status,             // reconciled, disputed
            ] : null,
        ];
    }

    private static function rate(string $type): float
    {
        return (float) config("pickrush.rider_pay.{$type}_per_parcel", 0);
    }
}
