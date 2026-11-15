<?php

namespace Tests\Feature;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\BuyerApi\Concerns\BuildsMarketplace;
use Tests\TestCase;

class SellerDiscountTest extends TestCase
{
    use BuildsMarketplace, RefreshDatabase;

    private function form(array $extra = []): array
    {
        return $extra + ['name' => 'Desk Lamp', 'price' => 1000, 'stock' => 5, 'weight_kg' => 0.5];
    }

    public static function clearedDiscounts(): array
    {
        return ['blank' => [''], 'null' => [null], 'zero' => ['0']];
    }

    #[DataProvider('clearedDiscounts')]
    public function test_seller_can_clear_discounts(mixed $value): void
    {
        $seller = $this->seller();
        $product = $this->product($seller, ['discount' => 20, 'voucher_discount' => 10]);

        $this->actingAs($seller)->patch(route('seller.inventory.update', $product), $this->form([
            'discount' => $value, 'voucher_discount' => $value,
        ]))->assertRedirect()->assertSessionHasNoErrors();

        $product->refresh();
        $this->assertEquals(0, $product->discount);
        $this->assertEquals(0, $product->voucher_discount);
        $this->assertEquals(1000, $product->effective_price);
    }

    public function test_seller_can_create_product_with_blank_discounts(): void
    {
        $this->actingAs($this->seller())->post(route('seller.inventory.store'), $this->form([
            'discount' => '', 'voucher_discount' => '',
        ]))->assertRedirect()->assertSessionHasNoErrors();

        $product = Product::sole();
        $this->assertEquals(0, $product->discount);
        $this->assertEquals(0, $product->voucher_discount);
    }

    public function test_omitted_discounts_preserve_existing_values(): void
    {
        $seller = $this->seller();
        $product = $this->product($seller, ['discount' => 20, 'voucher_discount' => 10]);

        $this->actingAs($seller)->patch(route('seller.inventory.update', $product), $this->form())
            ->assertRedirect()->assertSessionHasNoErrors();

        $this->assertEquals(20, $product->fresh()->discount);
        $this->assertEquals(10, $product->fresh()->voucher_discount);
    }

    public function test_invalid_discount_is_rejected(): void
    {
        $seller = $this->seller();
        $product = $this->product($seller, ['discount' => 20]);

        $this->actingAs($seller)->patch(route('seller.inventory.update', $product), $this->form([
            'discount' => -1,
        ]))->assertSessionHasErrors('discount');

        $this->assertEquals(20, $product->fresh()->discount);
    }
}
