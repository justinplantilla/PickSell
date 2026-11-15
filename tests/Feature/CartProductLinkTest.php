<?php

namespace Tests\Feature;

use App\Models\ProductVariation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\BuyerApi\Concerns\BuildsMarketplace;
use Tests\TestCase;

/** A cart or checkout line's photo and name open its product page, with the line's option picked. */
class CartProductLinkTest extends TestCase
{
    use BuildsMarketplace, RefreshDatabase;

    public function test_cart_and_checkout_lines_link_to_the_product_page_with_their_option(): void
    {
        $buyer = $this->buyer();
        $product = $this->product($this->seller());
        $blue = ProductVariation::create(['product_id' => $product->id, 'type' => 'Color', 'value' => 'Blue', 'stock' => 3]);
        $line = $this->cartLine($buyer, $product, 1, $blue->id);
        $url = e(route('buyer.product', [$product, 'option' => $blue->id]));

        $this->actingAs($buyer)->get(route('buyer.cart'))->assertOk()->assertSee('href="'.$url.'"', false);
        $this->get(route('buyer.checkout.page', ['item_ids' => [$line->id]]))->assertOk()->assertSee('href="'.$url.'"', false);
    }

    public function test_a_line_without_an_option_links_to_the_plain_product_page(): void
    {
        $buyer = $this->buyer();
        $product = $this->product($this->seller());
        $this->cartLine($buyer, $product);

        $this->actingAs($buyer)->get(route('buyer.cart'))->assertOk()
            ->assertSee('href="'.route('buyer.product', $product).'" class="cart-title cart-title-link"', false);
    }

    public function test_an_archived_product_in_the_cart_is_not_a_link(): void
    {
        $buyer = $this->buyer();
        $product = $this->product($this->seller());
        $this->cartLine($buyer, $product);
        $product->update(['status' => 'archived']);

        $this->actingAs($buyer)->get(route('buyer.cart'))->assertOk()
            ->assertSeeText('Desk Lamp')
            ->assertDontSee('href="'.route('buyer.product', $product), false);
    }
}
