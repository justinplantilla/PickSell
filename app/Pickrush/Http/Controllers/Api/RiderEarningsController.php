<?php

namespace App\Pickrush\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Pickrush\Models\Assignment;
use App\Pickrush\Models\CodRemittance;
use App\Pickrush\Models\RiderBatch;
use App\Pickrush\Support\Actor;
use Carbon\CarbonInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;

/**
 * The rider app's Earnings page. A pickup, delivery or return earns its flat rate once it is done
 * (failed attempts earn nothing): today, this week, this month, the last 7 days, the batches
 * finished lately, and the COD cash still in hand.
 */
class RiderEarningsController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $rider = Actor::rider();
        $rates = [
            'pickup' => (float) config('pickrush.rider_pay.pickup_per_parcel', 0),
            'delivery' => (float) config('pickrush.rider_pay.delivery_per_parcel', 0),
        ];
        $monthStart = today()->startOfMonth();
        $weekAgo = today()->subDays(6);
        $from = $monthStart->lessThan($weekAgo) ? $monthStart : $weekAgo;

        $done = Assignment::where('rider_id', $rider->id)->where('status', 'completed')
            ->where('completed_at', '>=', $from)->get(['id', 'type', 'completed_at']);
        $total = fn (CarbonInterface $since, ?CarbonInterface $until = null) => self::total(
            $done->filter(fn (Assignment $task) => $task->completed_at >= $since && (! $until || $task->completed_at < $until)), $rates);

        $batches = RiderBatch::where('rider_id', $rider->id)->where('status', 'completed')
            ->withCount(['assignments as parcels' => fn ($q) => $q->where('status', 'completed')])
            ->latest('completed_at')->limit(20)->get();

        $inHand = (float) Assignment::where('rider_id', $rider->id)->where('status', 'completed')
            ->whereNull('cod_remittance_id')->sum('cod_collected');
        $remittances = CodRemittance::withoutGlobalScopes()->where('rider_id', $rider->id);
        $last = (clone $remittances)->latest('id')->first();

        return response()->json([
            'rates' => $rates,
            'today' => $total(today()),
            'week' => $total(today()->startOfWeek()),
            'month' => $total($monthStart),
            'days' => collect(range(6, 0))->map(fn (int $ago) => ['date' => today()->subDays($ago)->toDateString()]
                + $total(today()->subDays($ago), today()->subDays($ago - 1)))->values(),
            'batches' => $batches->map(fn (RiderBatch $batch) => [
                'id' => $batch->id,
                'batch_no' => $batch->batch_no,
                'type' => $batch->type,
                'parcels' => $batch->parcels,
                'earned' => round($batch->parcels * $rates[$batch->isDelivery() ? 'delivery' : 'pickup'], 2),
                'completed_at' => $batch->completed_at?->toIso8601String(),
            ])->values(),
            'cod' => [
                'in_hand' => round($inHand, 2),
                'limit' => (float) $rider->cod_limit,
                'remitted_this_month' => round((float) (clone $remittances)->where('remit_date', '>=', $monthStart)->sum('received_amount'), 2),
                'last_remittance' => $last ? [
                    'amount' => (float) $last->received_amount,
                    'at' => $last->created_at->toIso8601String(),
                    'status' => $last->status,
                ] : null,
            ],
        ]);
    }

    /** @param  Collection<int, Assignment>  $tasks */
    private static function total(Collection $tasks, array $rates): array
    {
        $pickups = $tasks->where('type', 'pickup')->count();
        $deliveries = $tasks->whereIn('type', ['delivery', 'return'])->count();

        return [
            'earnings' => round($pickups * $rates['pickup'] + $deliveries * $rates['delivery'], 2),
            'pickups' => $pickups,
            'deliveries' => $deliveries,
        ];
    }
}
