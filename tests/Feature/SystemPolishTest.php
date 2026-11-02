<?php

namespace Tests\Feature;

use App\Http\Middleware\LocalSessionCookie;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Tests\TestCase;

class SystemPolishTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role, string $email, array $extra = []): User
    {
        return User::create([
            'first_name' => ucfirst($role), 'last_name' => 'Polish', 'email' => $email, 'password' => bcrypt('password'),
            'role' => $role, 'status' => 'approved', 'sex' => 'Female', 'contact_no' => '09171234567',
            'birthday' => '1990-01-01', 'age' => 36, 'province' => 'Metro Manila', 'municipality' => 'Pasig', 'barangay' => 'San Miguel',
            ...$extra,
        ]);
    }

    public function test_missing_pages_show_the_branded_error_page(): void
    {
        $this->get('/no-such-page-anywhere')
            ->assertNotFound()
            ->assertSee('Page not found')
            ->assertSee('Go to PickSell')
            ->assertDontSee('onclick=', false);
    }

    /** Runs LocalSessionCookie for a request to $url and returns the session cookie settings it leaves behind. */
    private function cookieSettingsFor(string $url): array
    {
        config(['session.domain' => '.pick-sell.shop', 'session.secure' => true]);
        (new LocalSessionCookie)->handle(Request::create($url), fn () => new Response);

        return [config('session.domain'), config('session.secure')];
    }

    public function test_a_local_host_gets_a_session_cookie_the_browser_will_keep(): void
    {
        $this->assertSame([null, false], $this->cookieSettingsFor('http://127.0.0.1:8000/login'));
        $this->assertSame([null, false], $this->cookieSettingsFor('http://picksell.test/login'));
    }

    public function test_the_production_host_keeps_its_cookie_settings(): void
    {
        $this->assertSame(['.pick-sell.shop', true], $this->cookieSettingsFor('https://pick-sell.shop/login'));
        $this->assertSame(['.pick-sell.shop', true], $this->cookieSettingsFor('https://logistics.pick-sell.shop/login'));
    }

    public function test_featured_products_without_a_photo_show_a_placeholder(): void
    {
        $seller = $this->user('seller', 'polish-seller@example.test', ['business_name' => 'Polish Shop']);
        Product::create(['seller_id' => $seller->id, 'name' => 'Woven Basket', 'category' => 'Home', 'price' => 300, 'stock' => 4, 'status' => 'active']);

        $this->get('/')
            ->assertOk()
            ->assertSee('class="landing-product-placeholder"', false)
            ->assertDontSee('src="/storage/"', false);
    }

    public function test_buyer_order_badges_use_a_styled_tone_and_a_readable_label(): void
    {
        $buyer = $this->user('buyer', 'polish-buyer@example.test');
        $seller = $this->user('seller', 'polish-seller2@example.test');
        Order::create(['order_number' => 'ORD-POLISH-1', 'buyer_id' => $buyer->id, 'seller_id' => $seller->id, 'product_name' => 'Basket',
            'quantity' => 1, 'amount' => 300, 'commission' => 30, 'status' => 'out_for_delivery']);

        $this->actingAs($buyer)->get(route('buyer.orders'))
            ->assertOk()
            ->assertSee('badge badge-shipped', false)
            ->assertSee('Out for delivery');
    }

    public function test_icon_only_sidebar_links_keep_a_tooltip(): void
    {
        $seller = $this->user('seller', 'polish-seller3@example.test', ['business_name' => 'Tooltip Shop']);

        $this->actingAs($seller)->get(route('seller.dashboard'))
            ->assertOk()
            ->assertSee('title="Inventory"', false)
            ->assertSee('data-sidebar-toggle', false)
            ->assertSee('apexcharts@8.3.1', false);
    }
}
