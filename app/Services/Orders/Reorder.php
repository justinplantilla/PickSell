<?php

namespace App\Services\Orders;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Order;

class Reorder
{
    public function add(Order $order, Cart $cart): array
    {
        return \Illuminate\Support\Facades\DB::transaction(function () use ($order, $cart) {
            $ids = [];
            foreach ($order->lines() as $line) {
                $product = $line->product()->with('seller', 'variations')->first();
                abort_unless($product && $product->isPurchasable() && $product->stock > 0, 422, 'A product is no longer available.');
                $variation = $product->variations->firstWhere('id', $line->variation_id);
                abort_if($product->variations->isNotEmpty() && ! $variation, 422, 'Choose an option for this product on its page.');
                abort_if($variation && $variation->stock <= 0, 422, 'That option is out of stock.');
                $item = CartItem::firstOrNew(['cart_id' => $cart->id, 'product_id' => $product->id, 'variation_id' => $variation?->id]);
                $item->quantity = min((int) $item->quantity + (int) $line->quantity, (int) ($variation ? min($variation->stock, $product->stock) : $product->stock));
                $item->save();
                $ids[] = $item->id;
            }
            return $ids;
        });
    }
}
