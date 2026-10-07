<?php

namespace App\Services\Finance;

use App\Models\FinancialTransaction;
use App\Models\Order;
use App\Models\Refund;
use App\Models\User;
use App\Services\CommissionService;
use Illuminate\Support\Facades\DB;

class FinancialLedgerService
{
    public function __construct(private CommissionService $commission) {}

    public function postCompletedOrder(Order $order, ?User $actor = null): void
    {
        DB::transaction(function () use ($order, $actor): void {
            $locked = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== 'completed') {
                throw new \LogicException('Ledger entries can only be posted for completed orders.');
            }

            $existingTypes = FinancialTransaction::query()
                ->where('reference_type', 'order')
                ->where('reference_id', $locked->id)
                ->pluck('type')
                ->all();
            $expectedTypes = ['order_gross', 'commission', 'seller_net'];
            if ($existingTypes !== []) {
                sort($existingTypes);
                $sortedExpected = $expectedTypes;
                sort($sortedExpected);
                if ($existingTypes === $sortedExpected) {
                    return;
                }

                throw new \LogicException('The order has an incomplete financial ledger posting.');
            }

            $grossCents = $this->commission->toCents($locked->amount);
            $commissionCents = $this->commission->toCents($locked->commission);
            if ($grossCents < 0 || $commissionCents < 0 || $commissionCents > $grossCents) {
                throw new \LogicException('A completed order must have non-negative gross and commission, with commission no greater than gross.');
            }
            $gross = $this->commission->fromCents($grossCents);
            $commission = $this->commission->fromCents($commissionCents);
            $sellerNet = $this->commission->fromCents($grossCents - $commissionCents);

            foreach ([
                ['order_gross', $gross, '0.00', $gross],
                ['commission', '0.00', $commission, $commission],
                ['seller_net', '0.00', $sellerNet, $sellerNet],
            ] as [$type, $debit, $credit, $amount]) {
                FinancialTransaction::create([
                    'order_id' => $locked->id,
                    'seller_id' => $locked->seller_id,
                    'type' => $type,
                    'debit' => $debit,
                    'credit' => $credit,
                    'amount' => $amount,
                    'reference_type' => 'order',
                    'reference_id' => $locked->id,
                    'status' => 'posted',
                    'description' => ucfirst(str_replace('_', ' ', $type)).' for order '.$locked->order_number,
                    'created_by' => $actor?->id ?? auth()->id(),
                ]);
            }
        });
    }

    public function postApprovedRefund(Refund $refund, User $actor): void
    {
        DB::transaction(function () use ($refund, $actor): void {
            $locked = Refund::query()->whereKey($refund->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== 'approved') {
                throw new \LogicException('Ledger entries can only be posted for approved refunds.');
            }
            $order = Order::query()->whereKey($locked->order_id)->lockForUpdate()->firstOrFail();

            $existingTypes = FinancialTransaction::query()
                ->where('reference_type', 'refund')
                ->where('reference_id', $locked->id)
                ->pluck('type')
                ->all();
            $expectedTypes = ['refund_gross', 'commission_reversal', 'seller_adjustment'];
            if ($existingTypes !== []) {
                sort($existingTypes);
                $sortedExpected = $expectedTypes;
                sort($sortedExpected);
                if ($existingTypes === $sortedExpected) {
                    return;
                }

                throw new \LogicException('The refund has an incomplete financial ledger posting.');
            }

            $refundCents = $this->commission->toCents($locked->amount);
            $reversalCents = $this->commission->toCents($locked->commission_reversal ?? 0);
            $sellerAdjustmentCents = $this->commission->toCents($locked->seller_adjustment ?? 0);
            if (
                $refundCents <= 0
                || $reversalCents < 0
                || $sellerAdjustmentCents < 0
                || $reversalCents + $sellerAdjustmentCents !== $refundCents
            ) {
                throw new \LogicException('Refund ledger entries must be non-negative and balance to the approved refund amount.');
            }

            $refundAmount = $this->commission->fromCents($refundCents);
            $reversal = $this->commission->fromCents($reversalCents);
            $sellerAdjustment = $this->commission->fromCents($sellerAdjustmentCents);
            foreach ([
                ['refund_gross', $refundAmount, '0.00', $refundAmount],
                ['commission_reversal', '0.00', $reversal, $reversal],
                ['seller_adjustment', '0.00', $sellerAdjustment, $sellerAdjustment],
            ] as [$type, $debit, $credit, $amount]) {
                FinancialTransaction::create([
                    'order_id' => $order->id,
                    'seller_id' => $order->seller_id,
                    'type' => $type,
                    'debit' => $debit,
                    'credit' => $credit,
                    'amount' => $amount,
                    'reference_type' => 'refund',
                    'reference_id' => $locked->id,
                    'status' => 'posted',
                    'description' => ucfirst(str_replace('_', ' ', $type)).' for refund #'.$locked->id,
                    'created_by' => $actor->id,
                ]);
            }
        });
    }
}
