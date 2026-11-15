<?php

namespace Tests\Feature;

use App\Models\ProductVariation;
use App\Models\WishlistItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\BuyerApi\Concerns\BuildsMarketplace;
use Tests\TestCase;

/** Product cards add options through the quick-add modal; adding to the cart or wishlist asks no confirmation. */
class QuickAddOptionsTest extends TestCase
{
    use BuildsMarketplace, RefreshDatabase;

    public function test_the_options_endpoint_lists_each_options_stock_price_and_image(): void
    {
        $product = $this->product($this->seller(), ['price' => 500, 'discount' => 10, 'stock' => 4]);
        $blueImage = $product->images()->create(['image_url' => 'products/blue.jpg', 'display_order' => 2, 'is_primary' => false]);
        $blue = ProductVariation::create(['product_id' => $product->id, 'type' => 'Color', 'value' => 'Blue', 'stock' => 9, 'price' => 600, 'image_id' => $blueImage->id]);
        $red = ProductVariation::create(['product_id' => $product->id, 'type' => 'Color', 'value' => 'Red', 'stock' => 0]);

        $this->actingAs($this->buyer())->getJson(route('buyer.product.options', $product))->assertOk()
            ->assertJsonPath('name', 'Desk Lamp')
            ->assertJsonPath('image', Storage::url($product->primary_image))
            ->assertJsonPath('choices.names', ['Color'])
            ->assertJsonPath('choices.values', [['Blue', 'Red']])
            ->assertJsonPath('choices.combinations.0.id', $blue->id)
            ->assertJsonPath('choices.combinations.0.values', ['Blue'])
            // An option never offers more than the product has in stock.
            ->assertJsonPath('choices.combinations.0.stock', 4)
            ->assertJsonPath('choices.combinations.0.price', 540)
            ->assertJsonPath('choices.combinations.0.image', $blueImage->url)
            ->assertJsonPath('choices.combinations.1.id', $red->id)
            ->assertJsonPath('choices.combinations.1.stock', 0)
            ->assertJsonPath('choices.combinations.1.image', null);
    }

    public function test_a_product_without_options_returns_none_and_an_archived_one_is_not_found(): void
    {
        $buyer = $this->buyer();
        $product = $this->product($this->seller());

        $this->actingAs($buyer)->getJson(route('buyer.product.options', $product))->assertOk()->assertJsonPath('choices.combinations', []);

        $product->update(['status' => 'archived']);
        $this->getJson(route('buyer.product.options', $product))->assertNotFound();
    }

    public function test_wishlist_forms_skip_the_confirmation(): void
    {
        $buyer = $this->buyer();
        $product = $this->product($this->seller());

        $this->actingAs($buyer)->get(route('buyer.product', $product))->assertOk()
            ->assertSee('class="wishlist-toggle-form" data-no-confirm', false);

        WishlistItem::create(['user_id' => $buyer->id, 'product_id' => $product->id]);
        $this->get(route('buyer.wishlist'))->assertOk()
            ->assertSee('action="'.route('buyer.wishlist.destroy', $product).'" data-no-confirm', false);
    }
}
