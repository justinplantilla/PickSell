<?php

namespace App\Services\Orders;

use App\Models\Order;
use App\Models\ProductReview;
use App\Pickrush\Bridge\PicksellBridge;

/**
 * A buyer's rating of a delivered order, the same from the website and the buyer app: the product
 * (orders.rating, published as the product's review once the order is completed), the seller's service, and the delivery,
 * which is credited to the Pickrush rider who delivered the parcel.
 */
class OrderRating
{
    public function rate(Order $order, int $product, ?int $seller, ?int $delivery, ?string $feedback): void
    {
        $order->update([
            'rating' => $product,
            'seller_rating' => $seller,
            'delivery_rating' => $delivery,
            'delivery_rider_id' => $delivery ? PicksellBridge::deliveryRiderId($order) : null,
            'feedback' => $feedback,
        ]);

        // A delivered order's review is published when the seller confirms it (SellerController::confirmDelivery).
        if ($order->status === 'completed' && $order->product_id) {
            ProductReview::updateOrCreate(
                ['product_id' => $order->product_id, 'buyer_id' => $order->buyer_id, 'order_id' => $order->id],
                ['rating' => $product, 'body' => $feedback],
            );
        }
    }
}
