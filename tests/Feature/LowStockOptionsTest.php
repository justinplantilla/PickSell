<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductVariation;
use App\Notifications\LowStockAlert;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Feature\BuyerApi\Concerns\BuildsMarketplace;
use Tests\TestCase;

class LowStockOptionsTest extends TestCase
{
    use BuildsMarketplace, RefreshDatabase;

    public function test_one_low_option_makes_the_product_low_stock(): void
    {
        $seller = $this->seller();
        $shirt = $this->product($seller, ['name' => 'Shirt', 'stock' => 22]);
        ProductVariation::create(['product_id' => $shirt->id, 'type' => 'Size', 'value' => 'S', 'stock' => 20]);
        ProductVariation::create(['product_id' => $shirt->id, 'type' => 'Size', 'value' => 'M', 'stock' => 2]);
        $this->product($seller, ['name' => 'Plenty', 'stock' => 50]);

        $this->assertSame(['Shirt'], Product::lowStock()->pluck('name')->all());
        $this->actingAs($seller)->get(route('seller.dashboard'))->assertOk()->assertSee('Size: M: 2 left');
        $this->actingAs($seller)->get(route('seller.inventory', ['filter' => 'low-stock']))->assertOk()
            ->assertSee('Shirt')->assertDontSee('Plenty')->assertSee('class="inventory-option-low">M', false);
    }

    public function test_sellers_hear_when_an_order_takes_an_option_low(): void
    {
        Notification::fake();
        $seller = $this->seller();
        $buyer = $this->buyer();
        $shirt = $this->product($seller, ['name' => 'Shirt', 'stock' => 20]);
        $medium = ProductVariation::create(['product_id' => $shirt->id, 'type' => 'Size', 'value' => 'M', 'stock' => 7]);

        $line = $this->cartLine($buyer, $shirt, 1, $medium->id);
        $this->actingAs($buyer)->post(route('buyer.checkout'), ['item_ids' => [$line->id]]);
        Notification::assertNotSentTo($seller, LowStockAlert::class);   // 6 left: still above the line

        $line = $this->cartLine($buyer, $shirt, 2, $medium->id);
        $this->actingAs($buyer)->post(route('buyer.checkout'), ['item_ids' => [$line->id]]);
        Notification::assertSentTo($seller, LowStockAlert::class, fn ($n) => $n->left === 4
            && str_contains($n->toDatabase($seller)['message'], 'Shirt (Size: M) has 4 left'));

        $line = $this->cartLine($buyer, $shirt, 1, $medium->id);
        $this->actingAs($buyer)->post(route('buyer.checkout'), ['item_ids' => [$line->id]]);
        Notification::assertSentToTimes($seller, LowStockAlert::class, 1);   // already low: no repeat
    }
}
