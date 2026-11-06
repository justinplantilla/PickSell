<?php

namespace Tests\Feature;

use App\Models\CartItem;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariation;
use App\Services\Orders\OrderLifecycleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\BuyerApi\Concerns\BuildsMarketplace;
use Tests\TestCase;

class SellerVariationTest extends TestCase
{
    use BuildsMarketplace, RefreshDatabase;

    private function form(array $extra = []): array
    {
        return $extra + [
            'name' => 'Cotton Shirt', 'category' => 'Fashion', 'price' => 300, 'discount' => 0,
            'voucher_discount' => 0, 'stock' => 999, 'weight_kg' => 0.3,
        ];
    }

    public function test_seller_adds_a_product_with_options_and_its_stock_is_their_total(): void
    {
        $seller = $this->seller();

        $this->actingAs($seller)->post(route('seller.inventory.store'), $this->form([
            'variations_managed' => 1,
            'variation_type' => 'Size',
            'variations' => [['value' => 'S', 'stock' => 2], ['value' => 'M', 'stock' => 5]],
        ]))->assertSessionHasNoErrors();

        $product = Product::sole();
        $this->assertSame(7, $product->stock);
        $this->assertSame([['Size', 'S', 2], ['Size', 'M', 5]], $product->variations->map(fn ($v) => [$v->type, $v->value, $v->stock])->all());

        $this->get(route('seller.inventory'))->assertOk()->assertSeeText('Size: S (2) · M (5)');
    }

    public function test_options_need_a_name_and_different_values(): void
    {
        $this->actingAs($this->seller())->post(route('seller.inventory.store'), $this->form([
            'variations_managed' => 1,
            'variations' => [['value' => 'Red', 'stock' => 1], ['value' => 'red', 'stock' => 1]],
        ]))->assertSessionHasErrors(['variation_type', 'variations.0.value', 'variations.1.value']);

        $this->assertDatabaseCount('products', 0);
    }

    public function test_editing_keeps_changes_adds_and_removes_options(): void
    {
        $seller = $this->seller();
        $product = $this->product($seller);
        $small = ProductVariation::create(['product_id' => $product->id, 'type' => 'Size', 'value' => 'S', 'stock' => 2]);
        $large = ProductVariation::create(['product_id' => $product->id, 'type' => 'Size', 'value' => 'L', 'stock' => 2]);
        $other = ProductVariation::create(['product_id' => $this->product($seller)->id, 'type' => 'Size', 'value' => 'XL', 'stock' => 9]);

        $this->actingAs($seller)->patch(route('seller.inventory.update', $product), $this->form([
            'variations_managed' => 1,
            'variation_type' => 'Size',
            'variations' => [
                ['id' => $small->id, 'value' => 'Small', 'stock' => 4],
                ['value' => 'M', 'stock' => 1],
                // Another product's option id is never taken over: it becomes a new option here.
                ['id' => $other->id, 'value' => 'XL', 'stock' => 3],
            ],
        ]))->assertSessionHasNoErrors();

        $product->refresh();
        $this->assertSame(8, $product->stock);
        $this->assertSame(['Small', 'M', 'XL'], $product->variations->pluck('value')->all());
        $this->assertSame($small->id, $product->variations->first()->id);
        $this->assertModelMissing($large);
        $this->assertSame(9, $other->fresh()->stock);
    }

    public function test_a_form_without_the_options_editor_leaves_options_alone(): void
    {
        $seller = $this->seller();
        $product = $this->product($seller);
        ProductVariation::create(['product_id' => $product->id, 'type' => 'Size', 'value' => 'S', 'stock' => 2]);

        $this->actingAs($seller)->patch(route('seller.inventory.update', $product), $this->form())->assertSessionHasNoErrors();

        $this->assertSame(1, $product->variations()->count());
    }

    public function test_checkout_records_the_option_and_takes_its_stock(): void
    {
        $seller = $this->seller();
        $shirt = $this->product($seller, ['stock' => 5]);
        $medium = ProductVariation::create(['product_id' => $shirt->id, 'type' => 'Size', 'value' => 'M', 'stock' => 3]);
        ProductVariation::create(['product_id' => $shirt->id, 'type' => 'Size', 'value' => 'L', 'stock' => 2]);
        $buyer = $this->buyer();

        $this->actingAs($buyer)->post(route('buyer.cart.add', $shirt), ['quantity' => 2, 'variation_id' => $medium->id])->assertRedirect();
        $line = CartItem::sole();

        $this->actingAs($buyer)->post(route('buyer.checkout'), ['item_ids' => [$line->id]])->assertRedirect(route('buyer.orders'));

        $order = Order::sole();
        $this->assertSame([$medium->id, 'Size: M'], [$order->variation_id, $order->variation_label]);
        $this->assertSame(1, $medium->fresh()->stock);
        $this->assertSame(3, $shirt->fresh()->stock);

        $this->actingAs($seller)->get(route('seller.orders'))->assertOk()->assertSee('Size: M');

        // Cancelling puts the stock back on the option as well as the product.
        app(OrderLifecycleService::class)->transition($order, 'cancelled', $seller->id, 'seller', 'test');
        $this->assertSame(3, $medium->fresh()->stock);
        $this->assertSame(5, $shirt->fresh()->stock);
    }

    public function test_checkout_refuses_more_than_the_option_has(): void
    {
        $seller = $this->seller();
        $shirt = $this->product($seller, ['stock' => 5]);
        $medium = ProductVariation::create(['product_id' => $shirt->id, 'type' => 'Size', 'value' => 'M', 'stock' => 1]);
        $buyer = $this->buyer();
        $line = $this->cartLine($buyer, $shirt, 2, $medium->id);

        $this->actingAs($buyer)->post(route('buyer.checkout'), ['item_ids' => [$line->id]])
            ->assertRedirect(route('buyer.cart'))->assertSessionHasErrors('item_ids');

        $this->assertDatabaseCount('orders', 0);
        $this->assertSame(1, $medium->fresh()->stock);
    }

    public function test_a_product_sold_in_options_needs_one_chosen(): void
    {
        $seller = $this->seller();
        $shirt = $this->product($seller);
        ProductVariation::create(['product_id' => $shirt->id, 'type' => 'Size', 'value' => 'M', 'stock' => 3]);
        $otherOption = ProductVariation::create(['product_id' => $this->product($seller)->id, 'type' => 'Size', 'value' => 'L', 'stock' => 3]);
        $buyer = $this->buyer();

        $this->actingAs($buyer)->post(route('buyer.cart.add', $shirt), ['quantity' => 1])->assertSessionHasErrors('variation_id');
        $this->actingAs($buyer)->post(route('buyer.cart.add', $shirt), ['quantity' => 1, 'variation_id' => $otherOption->id])->assertSessionHasErrors('variation_id');

        // A line left without an option (e.g. the seller deleted it) cannot be checked out.
        $line = $this->cartLine($buyer, $shirt, 1);
        $this->actingAs($buyer)->get(route('buyer.checkout.start', ['item_ids' => [$line->id]]))
            ->assertRedirect(route('buyer.cart'))->assertSessionHasErrors('item_ids');
        $this->actingAs($buyer)->post(route('buyer.checkout'), ['item_ids' => [$line->id]])->assertSessionHasErrors('item_ids');
        $this->actingAs($buyer)->get(route('buyer.cart'))->assertOk()->assertSee('Choose an option');
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_the_app_cart_flags_a_line_without_an_option(): void
    {
        $seller = $this->seller();
        $shirt = $this->product($seller);
        $medium = ProductVariation::create(['product_id' => $shirt->id, 'type' => 'Size', 'value' => 'M', 'stock' => 1]);
        $buyer = $this->actingAsBuyer();

        $this->postJson('/api/mobile/buyer/cart', ['product_id' => $shirt->id, 'quantity' => 1])->assertUnprocessable()->assertJsonValidationErrors('variation_ids');
        $this->postJson('/api/mobile/buyer/cart', ['product_id' => $shirt->id, 'quantity' => 2, 'variation_ids' => [$medium->id]])->assertUnprocessable()->assertJsonValidationErrors('quantity');

        $this->cartLine($buyer, $shirt, 1);
        $this->getJson('/api/mobile/buyer/cart')->assertOk()->assertJsonPath('cart.groups.0.items.0.issue', 'option_required');
    }
}
