<?php

namespace App\Pickrush\Services\Rider;

use App\Pickrush\Enums\StaffRole;
use App\Pickrush\Models\Parcel;
use App\Pickrush\Models\Rider;
use App\Pickrush\Models\RiderDeduction;
use App\Pickrush\Models\RiderNotification;
use App\Pickrush\Models\RiderPayslip;
use App\Pickrush\Models\RiderWithdrawal;
use App\Pickrush\Models\Staff;
use App\Pickrush\Services\OperationRefused;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The rider wallet and weekly payroll. Earnings (pr_rider_earnings) and deductions wait for the
 * week's cutoff (Sunday night): close() puts each rider's into one payslip. The station manager
 * releases it, or it releases itself on Tuesday; then its net counts toward the wallet, which the
 * rider withdraws from any day. A negative net simply lowers the wallet, so it carries over.
 *
 * Earnings kept out of COD before the wallet (settled_at set, no payslip) never enter a payslip.
 * A disputed deduction stays off every payslip until the station manager decides it; upheld, it
 * lands on the next one. Withdrawals are simulated: paid at once with a mock reference.
 */
class RiderPayroll
{
    /** Days after the week ends (Sunday) that an unreleased payslip releases itself: Tuesday. */
    public const AUTO_RELEASE_AFTER_DAYS = 2;

    /** The Monday that starts the week $at falls in. */
    public static function weekStart(CarbonInterface $at): CarbonImmutable
    {
        return CarbonImmutable::parse($at)->startOfWeek(CarbonInterface::MONDAY)->startOfDay();
    }

    /**
     * Close the week that starts $weekStart into payslips: every rider's earnings and active
     * deductions up to that Sunday that are not on a payslip yet. Safe to repeat: a rider's week
     * is closed once.
     *
     * @return int payslips made
     */
    public function close(CarbonInterface $weekStart): int
    {
        $start = self::weekStart($weekStart);
        $end = $start->addDays(6)->endOfDay();
        if ($end->isFuture()) {
            throw new OperationRefused('That week has not ended yet.');
        }

        $riders = DB::table('pr_rider_earnings')->whereNull('settled_at')->whereNull('payslip_id')->where('earned_at', '<=', $end)->pluck('rider_id')
            ->merge(RiderDeduction::withoutGlobalScopes()->where('status', RiderDeduction::ACTIVE)->whereNull('payslip_id')->where('occurred_at', '<=', $end)->pluck('rider_id'))
            ->unique();

        $made = 0;
        foreach ($riders as $riderId) {
            $made += DB::transaction(fn () => $this->closeFor(Rider::withoutGlobalScopes()->findOrFail($riderId), $start, $end)) ? 1 : 0;
        }

        return $made;
    }

    private function closeFor(Rider $rider, CarbonImmutable $start, CarbonImmutable $end): bool
    {
        if (RiderPayslip::withoutGlobalScopes()->where('rider_id', $rider->id)->whereDate('week_start', $start->toDateString())->exists()) {
            return false;
        }
        $earnings = DB::table('pr_rider_earnings')->where('rider_id', $rider->id)->whereNull('settled_at')->whereNull('payslip_id')
            ->where('earned_at', '<=', $end)->lockForUpdate()->get(['id', 'kind', 'amount']);
        $deductions = RiderDeduction::withoutGlobalScopes()->where('rider_id', $rider->id)->where('status', RiderDeduction::ACTIVE)
            ->whereNull('payslip_id')->where('occurred_at', '<=', $end)->lockForUpdate()->get();
        if ($earnings->isEmpty() && $deductions->isEmpty()) {
            return false;
        }

        $payslip = RiderPayslip::create([
            'rider_id' => $rider->id,
            'station_hub_id' => $rider->station_hub_id,
            'week_start' => $start->toDateString(),
            'week_end' => $end->toDateString(),
            'status' => RiderPayslip::CLOSED,
            'closed_at' => now(),
        ]);
        DB::table('pr_rider_earnings')->whereIn('id', $earnings->pluck('id'))->update(['payslip_id' => $payslip->id]);
        RiderDeduction::withoutGlobalScopes()->whereIn('id', $deductions->pluck('id'))->update(['payslip_id' => $payslip->id]);
        $this->total($payslip);

        RiderNotification::send($rider, 'payslip', 'Your payslip is ready',
            'Week of '.$start->format('M j').': ₱'.number_format((float) $payslip->net, 2).' net. Check it in Earnings; it is released by Tuesday.',
            ['payslip_id' => $payslip->id]);

        return true;
    }

    /** Recount a payslip from what is on it. */
    private function total(RiderPayslip $payslip): void
    {
        $counts = DB::table('pr_rider_earnings')->where('payslip_id', $payslip->id)
            ->selectRaw('kind, count(*) as n, coalesce(sum(amount), 0) as amount')->groupBy('kind')->get()->keyBy('kind');
        $earned = round((float) $counts->sum('amount'), 2);
        $deducted = round((float) RiderDeduction::withoutGlobalScopes()->where('payslip_id', $payslip->id)->where('status', RiderDeduction::ACTIVE)->sum('amount'), 2);

        $payslip->update([
            'pickups' => (int) ($counts['pickup']->n ?? 0),
            'deliveries' => (int) ($counts['delivery']->n ?? 0),
            'returns' => (int) ($counts['return']->n ?? 0),
            'earned' => $earned,
            'deductions' => $deducted,
            'net' => round($earned - $deducted, 2),
        ]);
    }

    /** The station manager releases a closed payslip: its net becomes withdrawable. */
    public function release(RiderPayslip $payslip, Staff $manager): RiderPayslip
    {
        if ($manager->role !== StaffRole::HubManager || $manager->hub_id !== $payslip->station_hub_id) {
            throw new OperationRefused('Only this station\'s hub manager can release a payslip.');
        }

        return $this->releaseNow($payslip, $manager);
    }

    /**
     * Release every closed payslip whose week ended AUTO_RELEASE_AFTER_DAYS or more ago (Tuesday).
     *
     * @return int payslips released
     */
    public function autoRelease(?CarbonInterface $now = null): int
    {
        $lastWeekEnd = CarbonImmutable::parse($now ?? now())->subDays(self::AUTO_RELEASE_AFTER_DAYS)->toDateString();
        $released = 0;
        RiderPayslip::withoutGlobalScopes()->where('status', RiderPayslip::CLOSED)->whereDate('week_end', '<=', $lastWeekEnd)->orderBy('id')
            ->each(function (RiderPayslip $payslip) use (&$released) {
                $this->releaseNow($payslip, null);
                $released++;
            });

        return $released;
    }

    private function releaseNow(RiderPayslip $payslip, ?Staff $manager): RiderPayslip
    {
        return DB::transaction(function () use ($payslip, $manager) {
            $payslip = RiderPayslip::withoutGlobalScopes()->whereKey($payslip->id)->lockForUpdate()->firstOrFail();
            if ($payslip->isReleased()) {
                throw new OperationRefused('This payslip was already released.');
            }
            $payslip->update(['status' => RiderPayslip::RELEASED, 'released_at' => now(), 'released_by' => $manager?->id]);
            RiderNotification::send($payslip->rider, 'payslip_released', 'Your pay is ready to withdraw',
                'Week of '.$payslip->week_start->format('M j').': ₱'.number_format((float) $payslip->net, 2).' added to your wallet.',
                ['payslip_id' => $payslip->id]);

            return $payslip;
        });
    }

    /** The station manager takes a damaged or lost parcel (or something else) off a rider's pay. */
    public function deduct(Rider $rider, Staff $manager, string $kind, float $amount, string $reason, ?Parcel $parcel = null, ?int $logisticsIssueId = null): RiderDeduction
    {
        if ($manager->role !== StaffRole::HubManager || $manager->hub_id !== $rider->station_hub_id) {
            throw new OperationRefused('Only this rider\'s station hub manager can deduct from their pay.');
        }
        if (! array_key_exists($kind, RiderDeduction::KINDS) || $kind === 'shortage') {
            throw new OperationRefused('Cash shortages are deducted when the remittance variance is resolved.');
        }
        $amount = round($amount, 2);
        if ($amount <= 0) {
            throw new OperationRefused('Enter the amount to deduct.');
        }

        $deduction = RiderDeduction::create([
            'rider_id' => $rider->id,
            'kind' => $kind,
            'amount' => $amount,
            'reason' => $reason,
            'parcel_id' => $parcel?->id,
            'logistics_issue_id' => $logisticsIssueId,
            'status' => RiderDeduction::ACTIVE,
            'created_by' => $manager->id,
            'occurred_at' => now(),
        ]);
        RiderNotification::send($rider, 'deduction', $deduction->label().' deducted from your pay',
            '₱'.number_format($amount, 2).': '.$reason.'. It comes off your next payslip. You can dispute it in Earnings.',
            ['deduction_id' => $deduction->id]);

        return $deduction;
    }

    /**
     * The rider disputes a deduction. Allowed until its payslip is released; it comes off that
     * payslip (if any) and waits for the station manager.
     */
    public function dispute(RiderDeduction $deduction, Rider $rider, string $reason): RiderDeduction
    {
        return DB::transaction(function () use ($deduction, $rider, $reason) {
            $deduction = RiderDeduction::withoutGlobalScopes()->whereKey($deduction->id)->lockForUpdate()->firstOrFail();
            if ($deduction->rider_id !== $rider->id) {
                throw new OperationRefused('This deduction is for another rider.');
            }
            if ($deduction->status !== RiderDeduction::ACTIVE) {
                throw new OperationRefused($deduction->status === RiderDeduction::DISPUTED ? 'You already disputed this deduction.' : 'This deduction was waived.');
            }
            $payslip = $deduction->payslip;
            if ($payslip?->isReleased()) {
                throw new OperationRefused('This payslip was already released. Talk to your station manager.');
            }
            $deduction->update(['status' => RiderDeduction::DISPUTED, 'dispute_reason' => $reason, 'disputed_at' => now(), 'payslip_id' => null]);
            if ($payslip) {
                $this->total($payslip);
            }

            return $deduction;
        });
    }

    /**
     * The station manager decides a disputed deduction: upheld, it lands on the next payslip;
     * waived, it is dropped.
     */
    public function decide(RiderDeduction $deduction, Staff $manager, bool $upheld, string $note): RiderDeduction
    {
        return DB::transaction(function () use ($deduction, $manager, $upheld, $note) {
            $deduction = RiderDeduction::withoutGlobalScopes()->whereKey($deduction->id)->lockForUpdate()->firstOrFail();
            if ($manager->role !== StaffRole::HubManager || $manager->hub_id !== $deduction->rider->station_hub_id) {
                throw new OperationRefused('Only this rider\'s station hub manager can decide the dispute.');
            }
            if ($deduction->status !== RiderDeduction::DISPUTED) {
                throw new OperationRefused('This deduction is not disputed.');
            }
            $deduction->update([
                'status' => $upheld ? RiderDeduction::ACTIVE : RiderDeduction::WAIVED,
                'decision' => $upheld ? 'upheld' : 'waived',
                'decision_note' => $note,
                'decided_by' => $manager->id,
                'decided_at' => now(),
            ]);
            RiderNotification::send($deduction->rider, 'deduction_decided',
                $upheld ? 'Your dispute was not accepted' : 'Your dispute was accepted',
                ($upheld ? '₱'.number_format((float) $deduction->amount, 2).' comes off your next payslip. ' : 'The deduction was dropped. ').$note,
                ['deduction_id' => $deduction->id]);

            return $deduction;
        });
    }

    /**
     * The wallet: available (released nets less withdrawals; may be negative), the closed payslips
     * waiting for release, and this week's earnings and deductions not on a payslip yet.
     *
     * @return array{available: float, awaiting_release: float, pending_earned: float, pending_deductions: float, disputed: float}
     */
    public function wallet(Rider $rider): array
    {
        $payslips = RiderPayslip::withoutGlobalScopes()->where('rider_id', $rider->id);
        $released = (float) (clone $payslips)->where('status', RiderPayslip::RELEASED)->sum('net');
        $withdrawn = (float) RiderWithdrawal::withoutGlobalScopes()->where('rider_id', $rider->id)->sum('amount');
        $deductions = RiderDeduction::withoutGlobalScopes()->where('rider_id', $rider->id)->whereNull('payslip_id');

        return [
            'available' => round($released - $withdrawn, 2),
            'awaiting_release' => round((float) (clone $payslips)->where('status', RiderPayslip::CLOSED)->sum('net'), 2),
            'pending_earned' => round((float) DB::table('pr_rider_earnings')->where('rider_id', $rider->id)->whereNull('settled_at')->whereNull('payslip_id')->sum('amount'), 2),
            'pending_deductions' => round((float) (clone $deductions)->where('status', RiderDeduction::ACTIVE)->sum('amount'), 2),
            'disputed' => round((float) (clone $deductions)->where('status', RiderDeduction::DISPUTED)->sum('amount'), 2),
        ];
    }

    /** The rider withdraws to the payout account on file. Simulated: paid at once, no money moves. */
    public function withdraw(Rider $rider, float $amount): RiderWithdrawal
    {
        $amount = round($amount, 2);
        if ($amount <= 0) {
            throw new OperationRefused('Enter the amount to withdraw.');
        }
        if (! $rider->payout_method || ! trim((string) $rider->payout_account)) {
            throw new OperationRefused('Your station has no payout account on file for you yet. Ask your station manager to add it.');
        }

        return DB::transaction(function () use ($rider, $amount) {
            // One withdrawal at a time per rider, so two taps cannot both spend the same balance.
            Rider::withoutGlobalScopes()->whereKey($rider->id)->lockForUpdate()->first();
            $available = $this->wallet($rider)['available'];
            if ($amount > $available + 0.001) {
                throw new OperationRefused('You can withdraw up to ₱'.number_format(max(0, $available), 2).'.');
            }
            $account = trim((string) $rider->payout_account);

            return RiderWithdrawal::create([
                'rider_id' => $rider->id,
                'amount' => $amount,
                'method' => $rider->payout_method,
                'account' => strlen($account) <= 4 ? $account : str_repeat('•', strlen($account) - 4).substr($account, -4),
                'reference' => 'SIM-'.now()->format('ymd').'-'.Str::upper(Str::random(8)),
                'status' => 'paid',
                'paid_at' => now(),
            ]);
        });
    }
}
