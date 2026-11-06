<?php

namespace Tests\Feature;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Feature\BuyerApi\Concerns\BuildsMarketplace;
use Tests\TestCase;

class ScheduledSaleTest extends TestCase
{
    use BuildsMarketplace, RefreshDatabase;

    public function test_a_scheduled_sale_starts_and_ends_by_itself(): void
    {
        Carbon::setTestNow('2026-11-06 10:00:00');
        $seller = $this->seller();
        $lamp = $this->product($seller, ['name' => 'Lamp', 'price' => 1000, 'discount' => 10]);

        $this->actingAs($seller)->patch(route('seller.inventory.update', $lamp), [
            'name' => 'Lamp', 'category' => 'Home & Living', 'price' => 1000, 'discount' => 10, 'voucher_discount' => 0, 'stock' => 5, 'weight_kg' => 1,
            'sale_discount' => 30, 'sale_starts_at' => '2026-11-07 00:00', 'sale_ends_at' => '2026-11-08 23:59',
        ])->assertSessionHasNoErrors();

        // Before: the regular 10%.
        $this->assertEquals(900, Product::find($lamp->id)->effective_price);
        $this->assertFalse(Product::find($lamp->id)->saleIsOn());

        // During: 30%, everywhere prices are worked out.
        Carbon::setTestNow('2026-11-07 12:00:00');
        $during = Product::find($lamp->id);
        $this->assertEquals(700, $during->effective_price);
        $this->assertEquals(30, $during->discount);
        $this->assertEquals(10, $during->regular_discount, 'the edit form still sees the regular discount');
        $buyer = $this->buyer();
        $this->actingAs($buyer)->get(route('buyer.product', $lamp))->assertOk()->assertSee('₱700.00')->assertSee('Sale ends Nov 8');
        $names = fn (array $q) => collect($this->actingAs($buyer)->get(route('buyer.browse', $q))->viewData('products')->items())->pluck('name')->all();
        $this->assertSame(['Lamp'], $names(['max_price' => 750]));
        $line = $this->cartLine($buyer, $lamp);
        $this->actingAs($buyer)->post(route('buyer.checkout'), ['item_ids' => [$line->id]])->assertRedirect(route('buyer.orders'));
        $this->assertEquals(700, (float) $lamp->orders()->sole()->amount);

        // After: back to 10% without anyone touching it.
        Carbon::setTestNow('2026-11-09 00:01:00');
        $this->assertEquals(900, Product::find($lamp->id)->effective_price);
        $this->assertSame([], $names(['max_price' => 750]));
    }

    public function test_a_sale_needs_both_dates_in_order(): void
    {
        $seller = $this->seller();
        $lamp = $this->product($seller);
        $form = ['name' => 'Lamp', 'category' => 'Home & Living', 'price' => 1000, 'discount' => 0, 'voucher_discount' => 0, 'stock' => 5, 'weight_kg' => 1];

        $this->actingAs($seller)->patch(route('seller.inventory.update', $lamp), $form + ['sale_discount' => 20])
            ->assertSessionHasErrors(['sale_starts_at', 'sale_ends_at']);
        $this->actingAs($seller)->patch(route('seller.inventory.update', $lamp), $form + ['sale_discount' => 20, 'sale_starts_at' => '2026-11-09', 'sale_ends_at' => '2026-11-08'])
            ->assertSessionHasErrors('sale_ends_at');
    }
}
