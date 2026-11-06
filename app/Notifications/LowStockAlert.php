<?php

namespace App\Notifications;

use App\Models\Product;
use App\Models\ProductVariation;
use Illuminate\Notifications\Notification;

/** Tells a seller an order just took a product, or one of its options, down to the low-stock line. */
class LowStockAlert extends Notification
{
    public function __construct(public Product $product, public ?ProductVariation $variation, public int $left)
    {
    }

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toDatabase($notifiable): array
    {
        $what = $this->product->name.($this->variation ? " ({$this->variation->label})" : '');

        return [
            'product_id' => $this->product->id,
            'variation_id' => $this->variation?->id,
            'title' => $this->left > 0 ? 'Running low on stock' : 'Sold out',
            'message' => $this->left > 0
                ? "{$what} has {$this->left} left. Restock it in Inventory so buyers can keep ordering."
                : "{$what} is sold out. Restock it in Inventory so buyers can order it again.",
            'url' => route('seller.inventory', ['filter' => 'low-stock'], false),
        ];
    }
}
