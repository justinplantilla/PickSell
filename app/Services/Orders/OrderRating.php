<?php

namespace App\Services\Orders;

use App\Models\Order;
use App\Models\ProductReview;
use App\Models\ProductReviewPhoto;
use App\Pickrush\Bridge\PicksellBridge;
use Illuminate\Http\UploadedFile;

/**
 * A buyer's rating of a delivered order, the same from the website and the buyer app: the product
 * (orders.rating, published as the product's review; the order is completed by "Order Received"), the seller's
 * service, and the delivery, which is credited to the Pickrush rider who delivered the parcel.
 * Photos are kept with the order and shown with its product review.
 */
class OrderRating
{
    /** @param  UploadedFile[]  $photos */
    public function rate(Order $order, int $product, ?int $seller, ?int $delivery, ?string $feedback, array $photos = []): void
    {
        $order->update([
            'rating' => $product,
            'seller_rating' => $seller,
            'delivery_rating' => $delivery,
            'delivery_rider_id' => $delivery ? PicksellBridge::deliveryRiderId($order) : null,
            'feedback' => $feedback,
        ]);
        if ($order->delivery_rider_id) {
            PicksellBridge::deliveryRated($order->delivery_rider_id);
        }
        if ($order->seller) {
            app(\App\Services\SellerPerformance::class)->forget($order->seller);
        }
        foreach (array_slice($photos, 0, ProductReviewPhoto::MAX_PER_REVIEW) as $photo) {
            $order->reviewPhotos()->create(['path' => $photo->store('review-photos', 'public')]);
        }

        // Rated once the buyer confirmed receipt (OrderReceipt), so the review is published straight away.
        if ($order->status === 'completed' && $order->product_id) {
            ProductReview::updateOrCreate(
                ['product_id' => $order->product_id, 'buyer_id' => $order->buyer_id, 'order_id' => $order->id],
                ['rating' => $product, 'body' => $feedback],
            );
        }
    }
}
