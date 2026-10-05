<?php

namespace App\Services\Finance;

use App\Models\Order;
use App\Services\PlatformCommission;
use Carbon\CarbonInterface;

/**
 * The Finance layer's definition of marketplace money figures. Gross sales are completed orders
 * (buyer confirmed); commission is the platform rate applied per order. Dashboard and reports
 * read totals from here so they always agree.
 */
class FinancialSummary
{
    public function __construct(private PlatformCommission $commission) {}

    /**
     * One order's money, by the same rule as the period totals. commission_at_placement is the
     * figure stored on the order when it was placed (informational; the rate may have changed).
     *
     * @return array{amount: float, commission: float, net_to_seller: float, commission_rate: float, commission_at_placement: float, counts_toward_sales: bool}
     */
    public function forOrder(Order $order): array
    {
        $amount = (float) $order->amount;
        $commission = $this->commission->deduction($amount);

        return [
            'amount' => $amount,
            'commission' => $commission,
            'net_to_seller' => $amount - $commission,
            'commission_rate' => $this->commission->rate(),
            'commission_at_placement' => (float) $order->commission,
            'counts_toward_sales' => $order->status === 'completed',
        ];
    }

    /** @return array{gross_sales: float, commission: float, net_to_sellers: float, completed_orders: int, commission_rate: float} */
    public function forPeriod(CarbonInterface $from, CarbonInterface $to): array
    {
        $amounts = Order::where('status', 'completed')
            ->whereBetween('created_at', [$from, $to])
            ->pluck('amount');

        $gross = (float) $amounts->sum();
        $commission = (float) $amounts->sum(fn ($amount) => $this->commission->deduction((float) $amount));

        return [
            'gross_sales' => $gross,
            'commission' => $commission,
            'net_to_sellers' => $gross - $commission,
            'completed_orders' => $amounts->count(),
            'commission_rate' => $this->commission->rate(),
        ];
    }
}
