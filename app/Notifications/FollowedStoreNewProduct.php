<?php

namespace App\Notifications;

use App\Models\Product;
use Illuminate\Notifications\Notification;

/** Tells a buyer that a store they follow listed a new product. */
class FollowedStoreNewProduct extends Notification
{
    public function __construct(public Product $product)
    {
    }

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toDatabase($notifiable): array
    {
        $store = $this->product->seller?->business_name ?: $this->product->seller?->full_name;

        return [
            'product_id' => $this->product->id,
            'title' => "New from {$store}",
            'message' => "{$store}, a store you follow, just listed {$this->product->name}.",
            'url' => route('buyer.product', $this->product, false),
        ];
    }
}
