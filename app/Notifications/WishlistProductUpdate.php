<?php

namespace App\Notifications;

use App\Models\Product;
use Illuminate\Notifications\Notification;

/** A product on the buyer's wishlist got cheaper or is back in stock. */
class WishlistProductUpdate extends Notification
{
    public const PRICE_DROP = 'price_drop';
    public const BACK_IN_STOCK = 'back_in_stock';

    public function __construct(public Product $product, public string $kind, public ?float $oldPrice = null)
    {
    }

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toDatabase($notifiable): array
    {
        $name = $this->product->name;
        $price = '₱'.number_format($this->product->effective_price, 2);

        return [
            'product_id' => $this->product->id,
            'kind' => $this->kind,
            'title' => $this->kind === self::PRICE_DROP ? 'Price drop on your wishlist' : 'Back in stock',
            'message' => $this->kind === self::PRICE_DROP
                ? "{$name} on your wishlist is now {$price} (was ₱".number_format((float) $this->oldPrice, 2).').'
                : "{$name} on your wishlist is back in stock at {$price}.",
            'url' => route('buyer.product', $this->product, false),
        ];
    }
}
