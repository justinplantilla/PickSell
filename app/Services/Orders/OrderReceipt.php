<?php

namespace App\Services\Orders;

use App\Models\Order;
use App\Models\ProductReview;
use App\Models\User;
use App\Notifications\BuyerOrderUpdate;
use App\Notifications\SellerDeliveryReceived;
use App\Services\Finance\FinancialLedgerService;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * "Order Received": once Pickrush delivers the parcel, the buyer confirms it really reached them.
 * That completes the order (the seller's earnings are posted) and opens it for rating; a rating
 * left before, on an older order, becomes the product review now. If the buyer doesn't confirm
 * within AUTO_CONFIRM_DAYS of delivery, it is confirmed for them (orders:auto-confirm-received).
 * Used by the website's order pages and the buyer app. The seller does not confirm deliveries.
 */
class OrderReceipt
{
    public const AUTO_CONFIRM_DAYS = 5;

    public function __construct(private OrderLifecycleService $lifecycle, private FinancialLedgerService $ledger) {}

    public function confirm(Order $order, User $buyer): Order
    {
        abort_if($order->buyer_id !== $buyer->id, 404, 'Order not found.');

        return $this->complete($order, $buyer->id, 'buyer', 'Buyer confirmed receipt', $buyer);
    }

    /** When a delivered order is confirmed automatically if the buyer hasn't; null once it isn't waiting. */
    public static function autoConfirmAt(Order $order): ?CarbonInterface
    {
        return $order->status === 'delivered' && $order->delivered_at ? $order->delivered_at->copy()->addDays(self::AUTO_CONFIRM_DAYS) : null;
    }

    /** Confirms every delivered order the buyer left unconfirmed for AUTO_CONFIRM_DAYS. @return int orders confirmed */
    public function autoConfirmDue(): int
    {
        $done = 0;
        Order::where('status', 'delivered')->whereNotNull('delivered_at')
            ->where('delivered_at', '<=', now()->subDays(self::AUTO_CONFIRM_DAYS))
            ->orderBy('id')->each(function (Order $order) use (&$done): void {
                try {
                    $completed = $this->complete($order, null, 'system', 'Automatically confirmed '.self::AUTO_CONFIRM_DAYS.' days after delivery');
                } catch (\Throwable $e) {
                    report($e);   // one stuck order must not hold up the rest

                    return;
                }
                if ($completed->status === 'completed') {
                    $order->buyer?->notify(new BuyerOrderUpdate($completed,
                        "Order {$completed->order_number} was marked received ".self::AUTO_CONFIRM_DAYS.' days after delivery. You can now rate it.'));
                    $done++;
                }
            });

        return $done;
    }

    private function complete(Order $order, ?int $actorId, string $source, string $trackingStatus, ?User $actor = null): Order
    {
        $confirmed = false;
        $order = DB::transaction(function () use ($order, $actorId, $source, $trackingStatus, $actor, &$confirmed) {
            $locked = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            if ($locked->status === 'completed') {
                return $locked;   // confirmed already (a second tap, or the buyer beat the automatic run)
            }
            abort_unless($locked->status === 'delivered', 422, 'You can confirm once Pickrush has delivered your order.');

            $this->lifecycle->transition($locked, 'completed', $actorId, $source, null, ['tracking_status' => $trackingStatus]);
            $this->ledger->postCompletedOrder($locked, $actor);
            if ($locked->rating && $locked->product_id) {
                ProductReview::firstOrCreate(
                    ['product_id' => $locked->product_id, 'buyer_id' => $locked->buyer_id, 'order_id' => $locked->id],
                    ['rating' => $locked->rating, 'body' => $locked->feedback],
                );
            }
            $confirmed = true;

            return $locked;
        });

        if ($confirmed) {
            $order->seller?->notify(new SellerDeliveryReceived($order));
        }

        return $order;
    }
}
