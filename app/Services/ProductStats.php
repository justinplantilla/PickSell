<?php

namespace App\Services;

use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Counts product views and add-to-carts per day (product_daily_stats) for the seller's product
 * analytics. The seller looking at their own product is not counted.
 */
class ProductStats
{
    public function viewed(Product $product, ?User $viewer): void
    {
        $this->bump($product, $viewer, 'views');
    }

    public function addedToCart(Product $product, ?User $buyer): void
    {
        $this->bump($product, $buyer, 'cart_adds');
    }

    private function bump(Product $product, ?User $who, string $column): void
    {
        if ($who && $who->id === $product->seller_id) {
            return;
        }
        $day = ['product_id' => $product->id, 'date' => today()->toDateString()];
        DB::table('product_daily_stats')->insertOrIgnore($day + ['views' => 0, 'cart_adds' => 0]);
        DB::table('product_daily_stats')->where($day)->increment($column);
    }
}
