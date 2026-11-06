<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Feature\BuyerApi\Concerns\BuildsMarketplace;
use Tests\TestCase;

class SellerProductAnalyticsTest extends TestCase
{
    use BuildsMarketplace, RefreshDatabase;

    public function test_sellers_see_views_cart_adds_and_sales_per_product(): void
    {
        $seller = $this->seller();
        $buyer = $this->buyer();
        $lamp = $this->product($seller, ['name' => 'Desk Lamp']);
        $mug = $this->product($seller, ['name' => 'Mug']);

        $this->actingAs($buyer)->get(route('buyer.product', $lamp))->assertOk();
        $this->actingAs($buyer)->get(route('buyer.product', $lamp))->assertOk();
        $this->getJson("/api/mobile/products/{$lamp->id}")->assertOk();
        $this->actingAs($buyer)->post(route('buyer.cart.add', $lamp), ['quantity' => 1]);
        $this->actingAs($seller)->get(route('buyer.product', $lamp));   // sellers are not buyers: not counted either way

        $this->assertSame(['views' => 3, 'cart_adds' => 1], (array) DB::table('product_daily_stats')->where('product_id', $lamp->id)->first(['views', 'cart_adds']));

        $this->order($buyer, $lamp, 'completed', ['quantity' => 2, 'amount' => 2000]);
        $this->order($buyer, $lamp, 'cancelled', ['quantity' => 5, 'amount' => 5000]);
        $this->order($buyer, $mug, 'placed', ['quantity' => 1, 'amount' => 300])->forceFill(['created_at' => now()->subDays(40)])->save();

        $page = $this->actingAs($seller)->get(route('seller.analytics'))->assertOk();
        $rows = $page->viewData('rows');
        $lampRow = $rows->firstWhere('product.id', $lamp->id);
        $this->assertSame([3, 1, 1, 2, 2000.0, 33.3], [$lampRow['views'], $lampRow['cart_adds'], $lampRow['orders'], $lampRow['units'], $lampRow['revenue'], $lampRow['conversion']]);
        $this->assertSame(0, $rows->firstWhere('product.id', $mug->id)['units'], 'the mug sold 40 days ago, outside the last 30');
        $this->assertSame(1, $this->actingAs($seller)->get(route('seller.analytics', ['days' => 90]))->viewData('rows')->firstWhere('product.id', $mug->id)['units']);
        $page->assertSee('Product performance')->assertSee('₱2,000.00');
    }
}
