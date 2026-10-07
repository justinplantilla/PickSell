<?php

namespace App\Services\Reports;

use App\Models\FinancialTransaction;
use App\Models\Order;
use App\Models\User;
use App\Services\CommissionService;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class FinancialReportService
{
    public function __construct(private CommissionService $commissionService) {}

    /**
     * @return array{
     *   transactions: \Illuminate\Contracts\Pagination\LengthAwarePaginator,
     *   gross_sales: float,
     *   commission: float,
     *   seller_net: float,
     *   refunds: float,
     *   adjustments: float,
     *   financial_total: float,
     *   transaction_count: int,
     *   seller_totals: Collection,
     *   top_sellers: Collection,
     *   completed_orders: int,
     *   new_buyers: int,
     *   new_sellers: int,
     *   weekly_sales: array<int, float>,
     *   weeks: array<int, string>
     *   current_commission_rate: float
     * }
     */
    public function report(
        CarbonInterface $from,
        CarbonInterface $to,
        string $status,
        ?int $sellerId,
    ): array {
        $base = $this->transactions($from, $to, $status, $sellerId);
        $totals = (clone $base)->selectRaw(
            'type, coalesce(sum(debit), 0) as debit_total, coalesce(sum(credit), 0) as credit_total'
        )->groupBy('type')->get()->keyBy('type');
        $grossSales = (float) ($totals['order_gross']->debit_total ?? 0);
        $refunds = (float) ($totals['refund_gross']->debit_total ?? 0);
        $commission = (float) ($totals['commission']->credit_total ?? 0)
            - (float) ($totals['commission_reversal']->credit_total ?? 0);
        $sellerNet = (float) ($totals['seller_net']->credit_total ?? 0)
            - (float) ($totals['seller_adjustment']->credit_total ?? 0);
        $adjustments = (float) ($totals['commission_reversal']->credit_total ?? 0)
            + (float) ($totals['seller_adjustment']->credit_total ?? 0);

        $sellerTotals = $this->sellerTotals($from, $to, $status, $sellerId);
        [$weeklySales, $weeks] = $this->weeklySales($base, $from, $to);

        return [
            'transactions' => (clone $base)->with(['order', 'seller', 'creator'])
                ->latest('created_at')->paginate(30)->withQueryString(),
            'gross_sales' => round($grossSales, 2),
            'commission' => round($commission, 2),
            'seller_net' => round($sellerNet, 2),
            'refunds' => round($refunds, 2),
            'adjustments' => round($adjustments, 2),
            'financial_total' => round($grossSales - $refunds, 2),
            'transaction_count' => (clone $base)->count(),
            'completed_orders' => (clone $base)->where('type', 'order_gross')->distinct()->count('order_id'),
            'new_buyers' => User::query()->where('role', 'buyer')->whereBetween('created_at', [$from, $to])->count(),
            'new_sellers' => User::query()->where('role', 'seller')
                ->whereBetween('created_at', [$from, $to])
                ->when($sellerId !== null, fn (Builder $query) => $query->whereKey($sellerId))
                ->count(),
            'seller_totals' => $sellerTotals,
            'top_sellers' => $sellerTotals->take(5)->values(),
            'weekly_sales' => $weeklySales,
            'weeks' => $weeks,
            'current_commission_rate' => $this->commissionService->rate(),
        ];
    }

    /**
     * @return array{
     *   orders: Collection,
     *   financial_orders: Collection,
     *   gross_sales: float,
     *   commission: float,
     *   seller_net: float,
     *   completed_orders: int,
     *   average_commission_rate: float,
     *   days: array<int, string>,
     *   daily_sales: array<int, float>,
     *   top_products: Collection
     * }
     */
    public function sellerPeriod(CarbonInterface $from, CarbonInterface $to, int $sellerId): array
    {
        $entries = $this->transactions($from, $to, 'posted', $sellerId)
            ->with('order')
            ->get();
        $sumType = fn (string $type, string $column): float => $this->commissionService->sum(
            $entries->where('type', $type)->pluck($column),
        );
        $grossSales = $sumType('order_gross', 'debit');
        $commission = $sumType('commission', 'credit') - $sumType('commission_reversal', 'credit');
        $sellerNet = $sumType('seller_net', 'credit') - $sumType('seller_adjustment', 'credit');
        $orderEntries = $entries->where('type', 'order_gross');
        $orders = $orderEntries->pluck('order')->filter();

        $financialOrders = $orderEntries->map(function (FinancialTransaction $entry) use ($entries): array {
            $order = $entry->order;
            if (! $order instanceof Order) {
                return [];
            }

            $orderEntries = $entries->where('order_id', $order->id);
            $commission = $this->commissionService->sum($orderEntries->where('type', 'commission')->pluck('credit'))
                - $this->commissionService->sum($orderEntries->where('type', 'commission_reversal')->pluck('credit'));
            $netEarnings = $this->commissionService->sum($orderEntries->where('type', 'seller_net')->pluck('credit'))
                - $this->commissionService->sum($orderEntries->where('type', 'seller_adjustment')->pluck('credit'));

            return [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'created_at' => $order->created_at,
                'amount' => (float) $entry->debit,
                'commission_rate' => $this->commissionService->rateForOrder($order),
                'commission' => $commission,
                'net_earnings' => $netEarnings,
                'status' => $order->status,
            ];
        })->filter()->values();

        $start = CarbonImmutable::parse($from)->startOfDay();
        $end = CarbonImmutable::parse($to)->startOfDay();
        $days = [];
        $dailySales = [];
        while ($start->lte($end)) {
            $day = $start->toDateString();
            $days[] = $start->format('M d');
            $gross = $this->commissionService->sum(
                $orderEntries->filter(fn (FinancialTransaction $entry): bool => $entry->created_at->toDateString() === $day)
                    ->pluck('debit'),
            );
            $refunds = $this->commissionService->sum(
                $entries->where('type', 'refund_gross')
                    ->filter(fn (FinancialTransaction $entry): bool => $entry->created_at->toDateString() === $day)
                    ->pluck('debit'),
            );
            $dailySales[] = round($gross - $refunds, 2);
            $start = $start->addDay();
        }

        $topProducts = $orders->groupBy('product_name')
            ->map(fn (Collection $group): array => [
                'name' => $group->first()->product_name,
                'sales' => $this->commissionService->sum($group->pluck('amount')),
                'count' => $group->count(),
            ])
            ->sortByDesc('sales')
            ->take(5)
            ->values();

        return [
            'orders' => $financialOrders,
            'financial_orders' => $financialOrders,
            'gross_sales' => $grossSales,
            'commission' => $commission,
            'seller_net' => $sellerNet,
            'completed_orders' => $orderEntries->pluck('order_id')->unique()->count(),
            'average_commission_rate' => $grossSales > 0 ? round($commission / $grossSales * 100, 2) : 0.0,
            'days' => $days,
            'daily_sales' => $dailySales,
            'top_products' => $topProducts,
        ];
    }

    private function transactions(
        CarbonInterface $from,
        CarbonInterface $to,
        string $status,
        ?int $sellerId,
    ): Builder {
        return FinancialTransaction::query()
            ->whereBetween('created_at', [$from, $to])
            ->when($status !== 'all', fn (Builder $query) => $query->where('status', $status))
            ->when($sellerId !== null, fn (Builder $query) => $query->where('seller_id', $sellerId));
    }

    private function sellerTotals(
        CarbonInterface $from,
        CarbonInterface $to,
        string $status,
        ?int $sellerId,
    ): Collection {
        $totals = $this->transactions($from, $to, $status, $sellerId)
            ->selectRaw('seller_id, type, coalesce(sum(debit), 0) as debit_total, coalesce(sum(credit), 0) as credit_total')
            ->groupBy('seller_id', 'type')->get()->groupBy('seller_id');

        return $totals->map(function (Collection $entries, $id): array {
            $byType = $entries->keyBy('type');
            $seller = User::query()->find($id);
            $sales = (float) ($byType['order_gross']->debit_total ?? 0);
            $refunds = (float) ($byType['refund_gross']->debit_total ?? 0);
            $commission = (float) ($byType['commission']->credit_total ?? 0)
                - (float) ($byType['commission_reversal']->credit_total ?? 0);
            $net = (float) ($byType['seller_net']->credit_total ?? 0)
                - (float) ($byType['seller_adjustment']->credit_total ?? 0);

            return [
                'name' => $seller?->full_name ?? 'Unavailable seller',
                'gross_sales' => round($sales, 2),
                'commission' => round($commission, 2),
                'seller_net' => round($net, 2),
                'refunds' => round($refunds, 2),
                'financial_total' => round($sales - $refunds, 2),
            ];
        })->sortByDesc('gross_sales')->values();
    }

    /**
     * @return array{0: array<int, float>, 1: array<int, string>}
     */
    private function weeklySales(Builder $base, CarbonInterface $from, CarbonInterface $to): array
    {
        $weeks = [];
        $sales = [];
        $start = CarbonImmutable::parse($from)->startOfDay();
        $end = CarbonImmutable::parse($to)->startOfDay();
        $week = 1;

        while ($start->lte($end)) {
            $weeks[] = 'Week '.$week++;
            $sales[] = 0.0;
            $start = $start->addDays(7);
        }

        $firstDay = CarbonImmutable::parse($from)->startOfDay();
        (clone $base)->whereIn('type', ['order_gross', 'commission', 'commission_reversal'])
            ->get(['type', 'debit', 'credit', 'created_at'])
            ->each(function (FinancialTransaction $transaction) use (&$sales, $firstDay): void {
                $days = $firstDay->diffInDays($transaction->created_at->startOfDay());
                $index = intdiv($days, 7);
                if (! array_key_exists($index, $sales)) {
                    return;
                }

                $amount = match ($transaction->type) {
                    'order_gross' => (float) $transaction->debit,
                    'commission' => (float) $transaction->credit,
                    'commission_reversal' => -(float) $transaction->credit,
                    default => 0.0,
                };
                $sales[$index] = round($sales[$index] + $amount, 2);
            });

        return [$sales ?: [0.0], $weeks ?: ['No Data']];
    }
}
