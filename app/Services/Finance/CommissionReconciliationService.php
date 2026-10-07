<?php

namespace App\Services\Finance;

use App\Models\FinancialTransaction;
use App\Models\Order;
use App\Models\Refund;
use App\Services\CommissionService;
use Illuminate\Support\Collection;

class CommissionReconciliationService
{
    public function __construct(private CommissionService $commission) {}

    /**
     * Compare completed-order and approved-refund snapshots with their posted ledger entries.
     *
     * @param  Collection<int, Order>  $orders
     * @return array{checked: int, reconciled: int, discrepancies: Collection<int, array{reference: string, order_number: string, seller: string, issues: array<int, string>}>}
     */
    public function reconcile(Collection $orders): array
    {
        $orderIds = $orders->modelKeys();
        if ($orderIds === []) {
            return ['checked' => 0, 'reconciled' => 0, 'discrepancies' => collect()];
        }

        $orderEntries = FinancialTransaction::query()
            ->where('reference_type', 'order')
            ->whereIn('reference_id', $orderIds)
            ->where('status', 'posted')
            ->get(['reference_id', 'type', 'debit', 'credit'])
            ->groupBy('reference_id');
        $refunds = Refund::query()
            ->whereIn('order_id', $orderIds)
            ->whereIn('status', ['approved', 'processed'])
            ->get();
        $refundEntries = FinancialTransaction::query()
            ->where('reference_type', 'refund')
            ->whereIn('reference_id', $refunds->modelKeys())
            ->where('status', 'posted')
            ->get(['reference_id', 'type', 'debit', 'credit'])
            ->groupBy('reference_id');

        $discrepancies = collect();
        foreach ($orders as $order) {
            $expected = [
                'order_gross' => [$this->commission->toCents($order->amount), 0],
                'commission' => [0, $this->commission->toCents($order->commission)],
                'seller_net' => [0, $this->commission->toCents($order->amount) - $this->commission->toCents($order->commission)],
            ];
            $issues = $this->compareEntries($expected, $orderEntries->get($order->id, collect()));
            if ($issues !== []) {
                $discrepancies->push($this->finding(
                    'Order #'.$order->id,
                    $order,
                    $issues,
                ));
            }
        }

        foreach ($refunds as $refund) {
            $order = $orders->firstWhere('id', $refund->order_id);
            if (! $order) {
                continue;
            }

            $expected = [
                'refund_gross' => [$this->commission->toCents($refund->amount), 0],
                'commission_reversal' => [0, $this->commission->toCents($refund->commission_reversal)],
                'seller_adjustment' => [0, $this->commission->toCents($refund->seller_adjustment)],
            ];
            $issues = $this->compareEntries($expected, $refundEntries->get($refund->id, collect()));
            if ($issues !== []) {
                $discrepancies->push($this->finding(
                    'Refund #'.$refund->id,
                    $order,
                    $issues,
                ));
            }
        }

        return [
            'checked' => $orders->count() + $refunds->count(),
            'reconciled' => $orders->count() + $refunds->count() - $discrepancies->count(),
            'discrepancies' => $discrepancies,
        ];
    }

    /**
     * @param  array<string, array{int, int}>  $expected
     * @param  Collection<int, FinancialTransaction>  $entries
     * @return array<int, string>
     */
    private function compareEntries(array $expected, Collection $entries): array
    {
        $actual = $entries->groupBy('type');
        $issues = [];

        foreach ($expected as $type => [$expectedDebit, $expectedCredit]) {
            $rows = $actual->get($type, collect());
            if ($rows->count() !== 1) {
                $issues[] = $rows->isEmpty()
                    ? "Missing {$type} entry."
                    : "Expected one {$type} entry; found {$rows->count()}.";
            }

            $actualDebit = $rows->sum(fn (FinancialTransaction $entry): int => $this->commission->toCents($entry->debit));
            $actualCredit = $rows->sum(fn (FinancialTransaction $entry): int => $this->commission->toCents($entry->credit));
            if ($actualDebit !== $expectedDebit || $actualCredit !== $expectedCredit) {
                $issues[] = "{$type} amount or debit/credit direction does not match its source record.";
            }
        }

        foreach ($actual->keys()->diff(array_keys($expected)) as $unexpectedType) {
            $issues[] = "Unexpected {$unexpectedType} entry.";
        }

        return $issues;
    }

    /**
     * @param  array<int, string>  $issues
     * @return array{reference: string, order_number: string, seller: string, issues: array<int, string>}
     */
    private function finding(string $reference, Order $order, array $issues): array
    {
        return [
            'reference' => $reference,
            'order_number' => $order->order_number,
            'seller' => $order->seller?->full_name ?? 'Unavailable seller',
            'issues' => $issues,
        ];
    }
}
