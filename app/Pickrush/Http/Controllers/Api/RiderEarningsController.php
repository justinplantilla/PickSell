<?php

namespace App\Pickrush\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Pickrush\Models\CodRemittance;
use App\Pickrush\Models\ParcelClass;
use App\Pickrush\Models\Rider;
use App\Pickrush\Models\RiderBatch;
use App\Pickrush\Services\Station\RiderAvailability;
use App\Pickrush\Support\Actor;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The rider's Earnings page: what finished tasks earned (today, this week, this month, the last
 * 7 days, per batch) and the COD cash they still hold, read from the earnings ledger
 * (RiderEarnings): a pickup at the flat rate, a marketplace delivery at its parcel class's pay,
 * failed attempts nothing. Payouts are handled by the station outside the app.
 */
class RiderEarningsController extends Controller
{
    public function index(RiderAvailability $availability): JsonResponse
    {
        $rider = Actor::rider();
        // Delivery pay depends on the parcel class; "delivery" is the lowest, for older app versions.
        $classes = ParcelClass::where('is_active', true)->orderBy('min_kg_exclusive')->pluck('rider_pay', 'code')->map(fn ($pay) => (float) $pay);
        $rates = ['pickup' => (float) config('pickrush.rider_pay.pickup_per_parcel', 0), 'delivery' => (float) $classes->min(), 'classes' => $classes];

        $since = now()->startOfMonth()->min(now()->subDays(6)->startOfDay());
        $earned = DB::table('pr_rider_earnings')->where('rider_id', $rider->id)->where('earned_at', '>=', $since)
            ->get(['kind', 'amount', 'earned_at'])
            ->map(fn ($row) => (object) ['kind' => $row->kind, 'amount' => (float) $row->amount, 'at' => Carbon::parse($row->earned_at)]);

        $days = collect(range(6, 0))->map(function (int $back) use ($earned) {
            $day = today()->subDays($back);

            return ['date' => $day->toDateString()] + self::sum($earned->filter(fn ($row) => $row->at->isSameDay($day)));
        })->values();

        return response()->json([
            'rates' => $rates,
            'today' => self::sum($earned->filter(fn ($row) => $row->at->gte(today()))),
            'week' => self::sum($earned->filter(fn ($row) => $row->at->gte(now()->startOfWeek()))),
            'month' => self::sum($earned->filter(fn ($row) => $row->at->gte(now()->startOfMonth()))),
            'days' => $days,
            'batches' => $this->batches($rider),
            'cod' => $this->cod($rider, $availability),
        ]);
    }

    /** What a rider earned today, for the home screen. */
    public static function today(Rider $rider): array
    {
        return self::sum(DB::table('pr_rider_earnings')->where('rider_id', $rider->id)->where('earned_at', '>=', today())
            ->get(['kind', 'amount'])->map(fn ($row) => (object) ['kind' => $row->kind, 'amount' => (float) $row->amount]));
    }

    private static function sum(Collection $rows): array
    {
        return [
            'earnings' => round($rows->sum('amount'), 2),
            'pickups' => $rows->where('kind', 'pickup')->count(),
            'deliveries' => $rows->whereIn('kind', ['delivery', 'return'])->count(),
        ];
    }

    /** The latest finished batches and what each earned. */
    private function batches(Rider $rider): array
    {
        $batches = RiderBatch::where('rider_id', $rider->id)->where('status', 'completed')
            ->latest('completed_at')->limit(20)->get();
        $earned = DB::table('pr_rider_earnings')->join('pr_assignments', 'pr_assignments.id', '=', 'pr_rider_earnings.assignment_id')
            ->whereIn('pr_assignments.batch_id', $batches->pluck('id'))
            ->groupBy('pr_assignments.batch_id')
            ->selectRaw('pr_assignments.batch_id, count(*) as parcels, sum(pr_rider_earnings.amount) as earned')
            ->get()->keyBy('batch_id');

        return $batches->map(fn (RiderBatch $batch) => [
            'id' => $batch->id,
            'batch_no' => $batch->batch_no,
            'type' => $batch->type,
            'completed_at' => $batch->completed_at?->toIso8601String(),
            'parcels' => (int) ($earned[$batch->id]->parcels ?? 0),
            'earned' => round((float) ($earned[$batch->id]->earned ?? 0), 2),
        ])->values()->all();
    }

    /** COD still with the rider, and what they handed over to the station. */
    private function cod(Rider $rider, RiderAvailability $availability): array
    {
        $last = CodRemittance::withoutGlobalScope('hub')->where('rider_id', $rider->id)->latest('id')->first();

        return [
            'in_hand' => round($availability->codInHand($rider), 2),
            'limit' => (float) $rider->cod_limit,
            'remitted_this_month' => round((float) CodRemittance::withoutGlobalScope('hub')->where('rider_id', $rider->id)
                ->where('created_at', '>=', now()->startOfMonth())->sum('received_amount'), 2),
            'last_remittance' => $last ? [
                'amount' => (float) $last->received_amount,
                'at' => $last->created_at->toIso8601String(),
                'status' => $last->status,
            ] : null,
        ];
    }
}
