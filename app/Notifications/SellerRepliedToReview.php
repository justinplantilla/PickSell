<?php

namespace App\Notifications;

use App\Models\ProductReview;
use Illuminate\Notifications\Notification;

/** Tells a buyer the seller answered their product review. */
class SellerRepliedToReview extends Notification
{
    public function __construct(public ProductReview $review)
    {
    }

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toDatabase($notifiable): array
    {
        $product = $this->review->product;
        $store = $product?->seller?->business_name ?: $product?->seller?->full_name;

        return [
            'product_id' => $this->review->product_id,
            'title' => 'The seller replied to your review',
            'message' => "{$store} replied to your review of {$product?->name}.",
            'url' => route('buyer.product', $this->review->product_id, false).'#review-'.$this->review->id,
        ];
    }
}
