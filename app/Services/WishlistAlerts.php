<?php

namespace App\Services;

use App\Models\Product;
use App\Models\User;
use App\Notifications\WishlistProductUpdate;
use Illuminate\Support\Facades\Notification;

/**
 * Tells buyers when a product on their wishlist gets cheaper or comes back in stock. Runs on
 * every product save (Product::booted), comparing the saved values with the ones before.
 */
class WishlistAlerts
{
    public static function productUpdated(Product $product): void
    {
        $alerts = [];
        if ($product->wasChanged(['price', 'discount'])) {
            $before = (float) $product->getOriginal('price') * (1 - (float) $product->getOriginal('discount') / 100);
            if ($product->effective_price < $before - 0.005) {
                $alerts[] = new WishlistProductUpdate($product, WishlistProductUpdate::PRICE_DROP, $before);
            }
        }
        if ($product->wasChanged('stock') && (int) $product->getOriginal('stock') <= 0 && $product->stock > 0) {
            $alerts[] = new WishlistProductUpdate($product, WishlistProductUpdate::BACK_IN_STOCK);
        }
        if (! $alerts || ! $product->isShowcased()) {
            return;
        }

        $buyers = User::where('status', 'approved')
            ->whereIn('id', $product->wishlistItems()->select('user_id'))->get();
        foreach ($alerts as $alert) {
            Notification::send($buyers, $alert);
        }
    }
}
