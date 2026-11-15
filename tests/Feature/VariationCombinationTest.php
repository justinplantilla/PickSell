<?php

namespace Tests\Feature;

use App\Models\CartItem;
use App\Models\Product;
use App\Models\ProductVariation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\BuyerApi\Concerns\BuildsMarketplace;
use Tests\TestCase;

/** A product sold in up to three options at once (Color and Size): each row is one combination. */
class VariationCombinationTest extends TestCase
{
    use BuildsMarketplace, RefreshDatabase;

    private function form(array $names, array $rows): array
    {
        return [
            'name' => 'Cotton Shirt', 'category' => 'Fashion', 'price' => 300, 'discount' => 0,
            'voucher_discount' => 0, 'stock' => 1, 'weight_kg' => 0.3,
            'variations_managed' => 1, 'option_names' => $names, 'variations' => $rows,
        ];
    }

    /** Red/S, Red/M, Blue/S, Blue/M with stock 1..4; Blue/M costs more. */
    private function shirt(): Product
    {
        $this->actingAs($this->seller())->post(route('seller.inventory.store'), $this->form(['Color', 'Size'], [
            ['values' => ['Red', 'S'], 'stock' => 1],
            ['values' => ['Red', 'M'], 'stock' => 2],
            ['values' => ['Blue', 'S'], 'stock' => 3],
            ['values' => ['Blue', 'M'], 'stock' => 4, 'price' => 350],
        ]))->assertSessionHasNoErrors();

        return Product::sole();
    }

    public function test_a_seller_adds_color_and_size_combinations(): void
    {
        $product = $this->shirt();

        $this->assertSame(10, $product->stock);
        $red = $product->variations->first();
        $this->assertSame([['Color', 'Red'], ['Size', 'S']], $red->options);
        $this->assertSame(['Color / Size', 'Red / S'], [$red->type, $red->value]);
        $this->assertSame('Color: Red, Size: S', $red->label);
        $this->assertSame([
            'names' => ['Color', 'Size'],
            'values' => [['Red', 'Blue'], ['S', 'M']],
        ], array_intersect_key($product->optionChoices(), ['names' => 1, 'values' => 1]));
        $this->assertEqualsWithDelta(350, $product->variations->last()->price, 0.001);
    }

    public function test_three_option_names_are_allowed_but_not_four(): void
    {
        $seller = $this->seller();

        $this->actingAs($seller)->post(route('seller.inventory.store'), $this->form(['Color', 'Size', 'Sleeve'], [
            ['values' => ['Red', 'S', 'Short'], 'stock' => 1],
        ]))->assertSessionHasNoErrors();
        $this->assertSame('Color: Red, Size: S, Sleeve: Short', ProductVariation::sole()->label);

        $this->post(route('seller.inventory.store'), $this->form(['Color', 'Size', 'Sleeve', 'Fit'], [
            ['values' => ['Red', 'S', 'Short', 'Slim'], 'stock' => 1],
        ]))->assertSessionHasErrors('option_names');
    }

    public function test_combinations_must_be_complete_different_and_within_the_value_limit(): void
    {
        $this->actingAs($this->seller());

        $this->post(route('seller.inventory.store'), $this->form(['Color', 'Size'], [
            ['values' => ['Red', 'S'], 'stock' => 1],
            ['values' => ['red', 's'], 'stock' => 1],
            ['values' => ['Blue', ''], 'stock' => 1],
        ]))->assertSessionHasErrors(['variations.0.value', 'variations.1.value', 'variations.2.value']);

        $this->post(route('seller.inventory.store'), $this->form(['Color', 'color'], [
            ['values' => ['Red', 'S'], 'stock' => 1],
        ]))->assertSessionHasErrors('option_names.1');

        $tooMany = collect(range(1, ProductVariation::MAX_VALUES_PER_OPTION + 1))->map(fn ($n) => ['values' => ["Shade {$n}"], 'stock' => 1])->all();
        $this->post(route('seller.inventory.store'), $this->form(['Color'], $tooMany))->assertSessionHasErrors('option_names.0');

        $this->assertDatabaseCount('products', 0);
    }

    public function test_editing_keeps_the_combinations_still_listed_and_removes_the_rest(): void
    {
        $product = $this->shirt();
        [$redS, $redM] = $product->variations;

        // Blue is gone; Red keeps its rows (by id) and gains L.
        $this->patch(route('seller.inventory.update', $product), $this->form(['Color', 'Size'], [
            ['id' => $redS->id, 'values' => ['Red', 'S'], 'stock' => 5],
            ['id' => $redM->id, 'values' => ['Red', 'M'], 'stock' => 2],
            ['values' => ['Red', 'L'], 'stock' => 1],
        ]))->assertSessionHasNoErrors();

        $product->refresh();
        $this->assertSame(['Red / S', 'Red / M', 'Red / L'], $product->variations->pluck('value')->all());
        $this->assertSame([$redS->id, $redM->id], $product->variations->take(2)->pluck('id')->all());
        $this->assertSame(8, $product->stock);
    }

    public function test_the_first_options_image_is_shared_by_its_combinations(): void
    {
        $seller = $this->seller();
        $product = $this->product($seller);
        $red = $product->images()->create(['image_url' => 'products/red.jpg', 'display_order' => 2]);

        $this->actingAs($seller)->patch(route('seller.inventory.update', $product), $this->form(['Color', 'Size'], [
            ['values' => ['Red', 'S'], 'stock' => 1, 'image' => "existing:{$red->id}"],
            ['values' => ['Red', 'M'], 'stock' => 1, 'image' => "existing:{$red->id}"],
            ['values' => ['Blue', 'S'], 'stock' => 1],
        ]))->assertSessionHasNoErrors();

        $this->assertSame([$red->id, $red->id, null], $product->variations()->pluck('image_id')->all());
    }

    public function test_a_buyer_adds_a_combination_and_the_cart_names_both_options(): void
    {
        $product = $this->shirt();
        $blueM = $product->variations->firstWhere('value', 'Blue / M');
        $buyer = $this->buyer();

        $this->actingAs($buyer)->post(route('buyer.cart.add', $product), ['quantity' => 2, 'variation_id' => $blueM->id])
            ->assertSessionHasNoErrors();

        $this->assertSame($blueM->id, CartItem::sole()->variation_id);
        $this->get(route('buyer.cart'))->assertOk()->assertSeeText('Color: Blue, Size: M');
    }

    public function test_the_buyer_app_sees_the_combinations_as_one_option_group(): void
    {
        $product = $this->shirt();

        $this->actingAs($this->buyer(), 'sanctum')->getJson("/api/mobile/products/{$product->id}")->assertOk()
            ->assertJsonCount(1, 'product.variation_groups')
            ->assertJsonPath('product.variation_groups.0.name', 'Color / Size')
            ->assertJsonPath('product.variation_groups.0.options.0.value', 'Red / S')
            ->assertJsonCount(4, 'product.variation_groups.0.options');
    }

    public function test_an_option_saved_before_combinations_still_reads_as_one_pair(): void
    {
        $product = $this->product($this->seller());
        $legacy = ProductVariation::create(['product_id' => $product->id, 'type' => 'Size', 'value' => 'XL', 'stock' => 2]);

        $this->assertSame([['Size', 'XL']], $legacy->optionPairs());
        $this->assertSame('Size: XL', $legacy->label);
    }
}
