<?php

namespace App\Pickrush\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Pickrush\Models\CodRemittance;
use App\Pickrush\Services\Station\CashHandling;
use App\Pickrush\Models\ParcelClass;
use App\Pickrush\Models\Rider;
use App\Pickrush\Models\RiderBatch;
use App\Pickrush\Services\OperationRefused;
use App\Pickrush\Services\Station\RiderAvailability;
use App\Pickrush\Support\Actor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
        // Pickup and delivery pay the same, by parcel class; "pickup" and "delivery" are the lowest
        // and "classes" the base pay, for older app versions. "class_rules" adds the weights and per-kg pay.
        $active = ParcelClass::where('is_active', true)->orderBy('min_kg_exclusive')->get();
        $classes = $active->pluck('rider_pay', 'code')->map(fn ($pay) => (float) $pay);
        $rules = $active->map(fn (ParcelClass $class) => [
            'code' => $class->code,
            'above_kg' => (float) $class->min_kg_exclusive,
            'up_to_kg' => (float) $class->max_kg_inclusive,
            'pay' => (float) $class->rider_pay,
            'per_kg_pay' => (float) $class->per_kg_pay,
        ])->values();
        $rates = ['pickup' => (float) $classes->min(), 'delivery' => (float) $classes->min(), 'classes' => $classes, 'class_rules' => $rules];

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

    /** The rider agrees with the station's count of their cash. */
    public function confirmCount(int $remittance, CashHandling $cash): JsonResponse
    {
        $rider = Actor::rider();
        try {
            $count = $cash->confirmCount($this->count($remittance, $rider), $rider);
        } catch (OperationRefused $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => (float) $count->variance == 0.0
                ? 'Count confirmed. Thank you.'
                : 'Count confirmed. The hub manager will settle the ₱'.number_format(abs((float) $count->variance), 2).' difference with you.',
            'status' => $count->status,
        ]);
    }

    /** The rider says the count is wrong; the station counts the cash again with them. */
    public function rejectCount(Request $request, int $remittance, CashHandling $cash): JsonResponse
    {
        $data = $request->validate(['reason' => 'required|string|min:5|max:500'], ['reason.required' => 'Say what is wrong with the count.']);
        $rider = Actor::rider();
        try {
            $cash->rejectCount($this->count($remittance, $rider), $rider, $data['reason']);
        } catch (OperationRefused $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => 'Count rejected. Ask the station to count your cash again with you.', 'status' => CodRemittance::REJECTED]);
    }

    /** A station count waiting for the rider, as the app shows it (earnings and the handover screen). */
    public static function pendingCount(CodRemittance $count): array
    {
        return [
            'id' => $count->id,
            'expected' => (float) $count->expected_amount,
            'counted' => (float) $count->received_amount,
            'difference' => (float) $count->variance,
            'pay_kept' => (float) $count->rider_pay_kept,
            'counted_by' => $count->receiver?->full_name,
            'at' => $count->created_at->toIso8601String(),
        ];
    }

    private function count(int $id, Rider $rider): CodRemittance
    {
        return CodRemittance::withoutGlobalScope('hub')->where('rider_id', $rider->id)->findOrFail($id);
    }

    /** COD still with the rider, and what they handed over to the station. */
    private function cod(Rider $rider, RiderAvailability $availability): array
    {
        $last = CodRemittance::withoutGlobalScope('hub')->where('rider_id', $rider->id)->where('status', '!=', CodRemittance::REJECTED)->latest('id')->first();
        $pending = $rider->cashCountToConfirm()?->loadMissing('receiver');
        $cash = app(CashHandling::class)->breakdown($rider);

        return [
            'in_hand' => $cash['cod'],
            // The rider keeps their pay out of the cash; to_remit is what they hand over at the station.
            'pay_kept' => $cash['pay'],
            'to_remit' => $cash['to_remit'],
            'limit' => (float) $rider->cod_limit,
            // A station count waiting for the rider to confirm or reject it.
            'pending_count' => $pending ? self::pendingCount($pending) : null,
            'remitted_this_month' => round((float) CodRemittance::withoutGlobalScope('hub')->where('rider_id', $rider->id)
                ->whereIn('status', ['reconciled', 'disputed'])->where('created_at', '>=', now()->startOfMonth())->sum('received_amount'), 2),
            'last_remittance' => $last ? [
                'amount' => (float) $last->received_amount,
                'at' => $last->created_at->toIso8601String(),
                'status' => $last->status,
            ] : null,
        ];
    }
}
