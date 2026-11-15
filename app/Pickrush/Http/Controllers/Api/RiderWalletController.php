<?php

namespace App\Pickrush\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Pickrush\Models\Rider;
use App\Pickrush\Models\RiderDeduction;
use App\Pickrush\Models\RiderPayslip;
use App\Pickrush\Models\RiderWithdrawal;
use App\Pickrush\Services\OperationRefused;
use App\Pickrush\Services\Rider\RiderPayroll;
use App\Pickrush\Support\Actor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * The rider's wallet in the app: the balance they can withdraw, this week's pay so far, their
 * weekly payslips, deductions they can dispute, and withdrawals (simulated: paid at once with
 * a mock reference, no money moves).
 */
class RiderWalletController extends Controller
{
    public function __construct(private RiderPayroll $payroll) {}

    /** The wallet as the Earnings page shows it (also part of GET /earnings). */
    public static function summary(Rider $rider): array
    {
        $week = RiderPayroll::weekStart(now());
        $account = trim((string) $rider->payout_account);

        return app(RiderPayroll::class)->wallet($rider) + [
            'week_start' => $week->toDateString(),
            'closes_at' => $week->addDays(6)->endOfDay()->toIso8601String(),
            'payout' => $rider->payout_method && $account !== '' ? [
                'method' => $rider->payout_method,
                'account' => strlen($account) <= 4 ? $account : str_repeat('•', strlen($account) - 4).substr($account, -4),
            ] : null,
            // Deductions still open to dispute: not on a released payslip.
            'deductions' => RiderDeduction::withoutGlobalScopes()->with('parcel', 'payslip')->where('rider_id', $rider->id)
                ->whereIn('status', [RiderDeduction::ACTIVE, RiderDeduction::DISPUTED])
                ->where(fn ($q) => $q->whereNull('payslip_id')->orWhereHas('payslip', fn ($p) => $p->withoutGlobalScopes()->where('status', RiderPayslip::CLOSED)))
                ->latest('occurred_at')->get()->map(fn (RiderDeduction $d) => self::deduction($d))->values(),
            'withdrawals' => RiderWithdrawal::withoutGlobalScopes()->where('rider_id', $rider->id)->latest('id')->limit(5)->get()
                ->map(fn (RiderWithdrawal $w) => self::withdrawal($w))->values(),
        ];
    }

    public function payslips(): JsonResponse
    {
        $rider = Actor::rider();

        return response()->json([
            'payslips' => RiderPayslip::withoutGlobalScopes()->where('rider_id', $rider->id)->orderByDesc('week_start')->limit(12)->get()
                ->map(fn (RiderPayslip $p) => self::payslipRow($p))->values(),
        ]);
    }

    public function payslip(int $payslip): JsonResponse
    {
        $p = RiderPayslip::withoutGlobalScopes()->where('rider_id', Actor::rider()->id)->findOrFail($payslip);

        return response()->json(['payslip' => self::payslipRow($p) + [
            'earnings' => DB::table('pr_rider_earnings')->leftJoin('pr_parcels', 'pr_parcels.id', '=', 'pr_rider_earnings.parcel_id')
                ->where('pr_rider_earnings.payslip_id', $p->id)->orderBy('earned_at')
                ->get(['pr_rider_earnings.kind', 'pr_rider_earnings.amount', 'pr_rider_earnings.earned_at', 'pr_parcels.tracking_no', 'pr_parcels.parcel_class'])
                ->map(fn ($e) => [
                    'kind' => $e->kind,
                    'amount' => (float) $e->amount,
                    'at' => \Illuminate\Support\Carbon::parse($e->earned_at)->toIso8601String(),
                    'tracking_no' => $e->tracking_no,
                    'parcel_class' => $e->parcel_class,
                ])->values(),
            'deduction_items' => RiderDeduction::withoutGlobalScopes()->with('parcel', 'payslip')->where('payslip_id', $p->id)->orderBy('occurred_at')->get()
                ->map(fn (RiderDeduction $d) => self::deduction($d))->values(),
        ]]);
    }

    public function withdraw(Request $request): JsonResponse
    {
        $data = $request->validate(['amount' => 'required|numeric|min:1|max:1000000'], ['amount.min' => 'Withdraw at least ₱1.00.']);
        $rider = Actor::rider();
        try {
            $withdrawal = $this->payroll->withdraw($rider, (float) $data['amount']);
        } catch (OperationRefused $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => '₱'.number_format((float) $withdrawal->amount, 2).' sent to your '.self::methodLabel($withdrawal->method).' '.$withdrawal->account.'.',
            'withdrawal' => self::withdrawal($withdrawal),
            'wallet' => self::summary($rider),
        ]);
    }

    public function dispute(Request $request, int $deduction): JsonResponse
    {
        $data = $request->validate(['reason' => 'required|string|min:5|max:500'], ['reason.required' => 'Say why this deduction is wrong.']);
        $rider = Actor::rider();
        $d = RiderDeduction::withoutGlobalScopes()->where('rider_id', $rider->id)->findOrFail($deduction);
        try {
            $this->payroll->dispute($d, $rider, $data['reason']);
        } catch (OperationRefused $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Dispute sent. It stays off your payslip until your station manager decides.',
            'wallet' => self::summary($rider),
        ]);
    }

    private static function payslipRow(RiderPayslip $p): array
    {
        return [
            'id' => $p->id,
            'week_start' => $p->week_start->toDateString(),
            'week_end' => $p->week_end->toDateString(),
            'pickups' => $p->pickups,
            'deliveries' => $p->deliveries,
            'returns' => $p->returns,
            'earned' => (float) $p->earned,
            'deductions' => (float) $p->deductions,
            'net' => (float) $p->net,
            'status' => $p->status,
            'released_at' => $p->released_at?->toIso8601String(),
        ];
    }

    private static function deduction(RiderDeduction $d): array
    {
        return [
            'id' => $d->id,
            'kind' => $d->kind,
            'label' => $d->label(),
            'amount' => (float) $d->amount,
            'reason' => $d->reason,
            'tracking_no' => $d->parcel?->tracking_no,
            'at' => $d->occurred_at->toIso8601String(),
            'status' => $d->status,
            'dispute_reason' => $d->dispute_reason,
            'decision_note' => $d->decision_note,
            'can_dispute' => $d->status === RiderDeduction::ACTIVE && ! ($d->payslip?->isReleased() ?? false),
        ];
    }

    private static function withdrawal(RiderWithdrawal $w): array
    {
        return [
            'id' => $w->id,
            'amount' => (float) $w->amount,
            'method' => $w->method,
            'account' => $w->account,
            'reference' => $w->reference,
            'status' => $w->status,
            'at' => ($w->paid_at ?? $w->created_at)->toIso8601String(),
        ];
    }

    private static function methodLabel(string $method): string
    {
        return ['gcash' => 'GCash', 'maya' => 'Maya', 'bank' => 'bank account'][$method] ?? $method;
    }
}
