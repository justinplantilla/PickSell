<?php

namespace App\Services\Finance;

use App\Models\FinancialTransaction;
use App\Models\Order;
use App\Services\CommissionService;
use Carbon\CarbonInterface;

/**
 * The Finance layer's definition of marketplace money figures. Period totals use posted ledger
 * entries so approved refunds and adjustments are reflected without rewriting order snapshots.
 */
class FinancialSummary
{
    public function __construct(private CommissionService $commission) {}

    /**
     * One order's money, by the same rule as the period totals. commission_at_placement is the
     * figure stored on the order when it was placed (informational; the rate may have changed).
     *
     * @return array{amount: float, commission: float, net_to_seller: float, commission_rate: float, commission_at_placement: float, counts_toward_sales: bool}
     */
    public function forOrder(Order $order): array
    {
        $amount = (float) $order->amount;
        $commission = $this->commission->commissionForOrder($order);

        return [
            'amount' => $amount,
            'commission' => $commission,
            'net_to_seller' => $this->commission->sellerNet($amount, $commission),
            'commission_rate' => $this->commission->rateForOrder($order),
            'commission_at_placement' => (float) $order->commission,
            'counts_toward_sales' => $order->status === 'completed',
        ];
    }

    /** @return array{gross_sales: float, commission: float, net_to_sellers: float, completed_orders: int, commission_rate: float} */
    public function forPeriod(CarbonInterface $from, CarbonInterface $to): array
    {
        $entries = FinancialTransaction::query()
            ->where('status', 'posted')
            ->whereBetween('created_at', [$from, $to])
            ->get(['order_id', 'type', 'debit', 'credit']);

        $amountFor = fn (string $type, string $column): float => $this->commission->sum(
            $entries->where('type', $type)->pluck($column),
        );
        $gross = $amountFor('order_gross', 'debit');
        $commission = $amountFor('commission', 'credit') - $amountFor('commission_reversal', 'credit');
        $netToSellers = $amountFor('seller_net', 'credit') - $amountFor('seller_adjustment', 'credit');
        $orderIds = $entries->where('type', 'order_gross')->pluck('order_id')->unique();

        return [
            'gross_sales' => $gross,
            'commission' => $commission,
            'net_to_sellers' => $netToSellers,
            'completed_orders' => $orderIds->count(),
            'commission_rate' => $this->commission->rate(),
        ];
    }
}
