<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\BuyerApi\Concerns\BuildsMarketplace;
use Tests\TestCase;

class ThemeAndAccountLayoutTest extends TestCase
{
    use BuildsMarketplace, RefreshDatabase;

    public function test_buyer_pages_start_light_and_apply_a_saved_dark_choice_before_paint(): void
    {
        $page = $this->actingAs($this->buyer())->get(route('buyer.wishlist'))->assertOk()->getContent();

        // Light by default: dark only when the buyer chose it, applied right after <body> opens.
        $this->assertStringNotContainsString('<body class="dark"', $page);
        $this->assertMatchesRegularExpression('#<body[^>]*>\s*(?:\{\{--.*?--\}\}\s*)?<script>try \{ if \(localStorage\.getItem\(\'darkMode\'\) === \'true\'\)#s', $page);
        $this->assertStringContainsString('buyer-dark', $page);
        $this->assertStringContainsString('data-theme-toggle title="Dark mode" aria-label="Dark mode" aria-pressed="false"', $page);
    }

    public function test_seller_account_keeps_their_own_settings_left_and_the_store_right(): void
    {
        $page = $this->actingAs($this->seller())->get(route('seller.account'))->assertOk()->getContent();

        preg_match_all('#<span class="card-title">([^<]+)</span>#', $page, $titles);
        $this->assertSame([
            'Profile Information', 'Change Password',
            'Store Profile', 'Vacation Mode', 'Delivery Partners', 'Shop Pin for Pickups', 'Delete Account',
        ], $titles[1]);

        [$left, $right] = array_slice(explode('<div class="account-column">', $page), 1, 2);
        $this->assertStringContainsString('Change Password', $left);
        $this->assertStringNotContainsString('Delete Account', $left);
        $this->assertStringNotContainsString('Store Profile', $left);
        $this->assertStringContainsString('Shop Pin for Pickups', $right);
        // The map spans both columns below them.
        $this->assertStringContainsString('<div class="card account-wide" id="pickup-location">', $page);
    }

    public function test_the_footer_offers_selling_to_guests_and_opens_sign_up_on_seller(): void
    {
        $this->get(route('about'))->assertOk()
            ->assertSee('<footer class="site-footer">', false)
            ->assertSee('href="/register?role=seller"', false)
            ->assertSee('Cash on Delivery');

        $this->get('/register?role=seller')->assertOk()
            ->assertSee('data-role="seller"', false);
        $this->get('/register?role=admin')->assertOk()
            ->assertSee('data-role="buyer"', false);
    }

    public function test_buyers_get_the_same_dark_footer_with_their_own_links(): void
    {
        $this->actingAs($this->buyer())->get(route('buyer.wishlist'))->assertOk()
            ->assertSee('<footer class="site-footer">', false)
            ->assertDontSee('<nav class="footer-col"', false)
            ->assertSee('href="'.route('buyer.orders').'"', false)
            ->assertDontSee('href="/register?role=seller"', false);
    }
}
