<?php

namespace App\Services\Orders;

use App\Models\LogisticsIssue;
use App\Models\Order;
use App\Notifications\BuyerOrderUpdate;
use App\Notifications\SellerShippingDeadline;
use App\Services\Logistics\LogisticsIssueDesk;
use App\Services\SellerPerformance;
use Throwable;

/**
 * Enforces the hand-over time limit. Once a seller starts preparing an order they have
 * Order::SHIP_WITHIN_HOURS (a pre-order: its own days) to hand it to the courier. A reminder goes
 * out 12 hours before; past the deadline the order is cancelled for the buyer and counts against
 * the seller's performance. A parcel the seller handed over but the courier has not picked up is
 * the logistics company's delay, not the seller's: it opens a logistics issue instead.
 */
class ShippingDeadlines
{
    public const REMINDER_HOURS = 12;

    /** Handed over this long ago and still not picked up: the courier is late. */
    public const PICKUP_GRACE_HOURS = 48;

    public function __construct(
        private OrderCancellationService $cancellations,
        private SellerPerformance $performance,
        private LogisticsIssueDesk $issues,
    ) {}

    /** @return array{reminded: int, cancelled: int, pickup_issues: int} */
    public function run(): array
    {
        $result = ['reminded' => 0, 'cancelled' => 0, 'pickup_issues' => 0];

        Order::query()->whereIn('status', ['preparing', 'processing'])->whereNotNull('ship_by')->where('ship_by', '<=', now())
            ->with(['buyer', 'seller'])->chunkById(100, function ($orders) use (&$result) {
                foreach ($orders as $order) {
                    if (! $this->cancellations->cancelLate($order)) {
                        continue;
                    }
                    $result['cancelled']++;
                    $this->performance->forget($order->seller);
                    $this->notify(fn () => $order->buyer?->notify(new BuyerOrderUpdate($order,
                        "Order #{$order->order_number} was cancelled because the seller did not ship it in time. You were not charged for it.")));
                    $this->notify(fn () => $order->seller?->notify(new SellerShippingDeadline($order,
                        "Order #{$order->order_number} was cancelled automatically: it was not handed to the courier by the deadline. This counts against your seller performance.")));
                }
            });

        // One reminder per order, 12 hours before its deadline (only orders not reminded yet).
        Order::query()->whereIn('status', ['preparing', 'processing'])
            ->whereBetween('ship_by', [now(), now()->addHours(self::REMINDER_HOURS)])->whereNull('ship_reminded_at')
            ->with('seller')->chunkById(100, function ($orders) use (&$result) {
                foreach ($orders as $order) {
                    $order->forceFill(['ship_reminded_at' => now()])->saveQuietly();
                    $this->notify(fn () => $order->seller?->notify(new SellerShippingDeadline($order,
                        "Hand order #{$order->order_number} to the courier by {$order->ship_by->format('M d, h:i A')} or it is cancelled automatically.")));
                    $result['reminded']++;
                }
            });

        Order::query()->where('status', 'ready_for_pickup')->whereNotNull('handed_over_at')
            ->where('handed_over_at', '<=', now()->subHours(self::PICKUP_GRACE_HOURS))
            ->whereNotExists(fn ($q) => $q->from('logistics_issues')->whereColumn('logistics_issues.order_id', 'orders.id')->where('type', 'pickup_missed'))
            ->chunkById(100, function ($orders) use (&$result) {
                foreach ($orders as $order) {
                    if ($this->issues->pickupOverdue($order)) {
                        $result['pickup_issues']++;
                    }
                }
            });

        return $result;
    }

    private function notify(callable $send): void
    {
        try {
            $send();
        } catch (Throwable $e) {
            report($e);
        }
    }
}
