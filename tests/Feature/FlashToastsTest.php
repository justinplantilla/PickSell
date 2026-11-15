<?php

namespace Tests\Feature;

use App\Models\ProductVariation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\BuyerApi\Concerns\BuildsMarketplace;
use Tests\TestCase;

/** Server messages reach every portal as toasts (partials/flash-toasts → notify.js), not page banners. */
class FlashToastsTest extends TestCase
{
    use BuildsMarketplace, RefreshDatabase;

    public function test_buyer_pages_hand_success_and_warning_messages_to_the_toasts(): void
    {
        $this->actingAs($this->buyer())
            ->withSession(['success' => 'Item added to cart.', 'warning' => 'Stock is low.'])
            ->get(route('buyer.wishlist'))->assertOk()
            ->assertSee('<div class="flash-toasts" hidden data-flash-toasts>', false)
            ->assertSee('<p data-flash="success">Item added to cart.</p>', false)
            ->assertSee('<p data-flash="warning">Stock is low.</p>', false)
            ->assertDontSee('class="alert alert-success"', false);
    }

    public function test_a_validation_error_comes_back_as_an_error_toast(): void
    {
        $product = $this->product($this->seller());
        ProductVariation::create(['product_id' => $product->id, 'type' => 'Size', 'value' => 'M', 'stock' => 2]);

        // Adding a product sold in options without choosing one is refused back to its page.
        $this->actingAs($this->buyer())->from(route('buyer.product', $product))->followingRedirects()
            ->post(route('buyer.cart.add', $product), ['quantity' => 1])->assertOk()
            ->assertSee('<p data-flash="error">Choose an option first.</p>', false)
            ->assertDontSee('class="alert alert-error"', false);
    }

    public function test_seller_and_admin_pages_use_the_toasts(): void
    {
        $this->actingAs($this->seller())->withSession(['success' => 'Product updated.'])
            ->get(route('seller.inventory'))->assertOk()
            ->assertSee('<p data-flash="success">Product updated.</p>', false)
            ->assertDontSee('class="alert alert-success"', false);

        $this->actingAs($this->admin())->withSession(['warning' => 'Settings are read-only.'])
            ->get(route('admin.dashboard'))->assertOk()
            ->assertSee('<p data-flash="warning">Settings are read-only.</p>', false)
            ->assertDontSee('class="alert alert-warning"', false);
    }

    public function test_a_page_without_messages_renders_no_toast_list(): void
    {
        $this->actingAs($this->buyer())->get(route('buyer.wishlist'))->assertOk()
            ->assertDontSee('data-flash-toasts', false);
    }

    public function test_the_pickrush_sign_in_page_shows_its_errors_as_toasts(): void
    {
        $page = $this->from(route('pickrush.login'))->followingRedirects()
            ->post(route('pickrush.login.attempt'), ['email' => 'nobody@example.com', 'password' => 'wrong-password'])
            ->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/<p data-flash="error">[^<]+<\/p>/', $page);
    }
}
