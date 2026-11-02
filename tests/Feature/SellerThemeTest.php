<?php

namespace Tests\Feature;

use App\Models\Message;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SellerThemeTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role, string $email, array $extra = []): User
    {
        return User::create([
            'first_name' => ucfirst($role), 'last_name' => 'Theme', 'email' => $email, 'password' => bcrypt('password'),
            'role' => $role, 'status' => 'approved', 'sex' => 'Female', 'contact_no' => '09171234567',
            'birthday' => '1990-01-01', 'age' => 36, 'province' => 'Metro Manila', 'municipality' => 'Pasig', 'barangay' => 'San Miguel',
            ...$extra,
        ]);
    }

    public function test_every_seller_page_wears_the_console_theme_and_keeps_its_data(): void
    {
        $seller = $this->user('seller', 'theme-seller@example.test', ['business_name' => 'Theme Tote House']);
        $buyer = $this->user('buyer', 'theme-buyer@example.test');
        $product = Product::create(['seller_id' => $seller->id, 'name' => 'Themed Canvas Tote', 'category' => 'Fashion', 'price' => 450, 'stock' => 7, 'status' => 'active']);
        $order = Order::create([
            'order_number' => 'ORD-THEME-1', 'buyer_id' => $buyer->id, 'seller_id' => $seller->id, 'product_id' => $product->id,
            'product_name' => 'Themed Canvas Tote', 'quantity' => 1, 'amount' => 450, 'commission' => 45, 'status' => 'completed',
        ]);
        Message::create(['sender_id' => $buyer->id, 'receiver_id' => $seller->id, 'body' => 'Is the tote washable?', 'read' => false]);

        $pages = [
            route('seller.dashboard') => ['ORD-THEME-1', 'Theme Tote House'],
            route('seller.inventory') => ['Themed Canvas Tote'],
            route('seller.orders') => ['ORD-THEME-1', 'Buyer Theme'],
            route('seller.orders.show', $order) => ['ORD-THEME-1', 'Themed Canvas Tote'],
            route('seller.returns') => [],
            route('seller.reports') => [],
            route('seller.earnings') => ['Earnings'],   // rows come from the ledger, which this order has no postings in
            route('seller.chat', ['user' => $buyer->id]) => ['Is the tote washable?'],
            route('seller.account') => ['Theme Tote House', 'Shop Pin for Pickups'],
        ];

        $this->actingAs($seller);
        foreach ($pages as $url => $data) {
            $response = $this->get($url)->assertOk();
            $response->assertSee('class="ad-theme sl-theme', false)
                ->assertSee('<dialog id="logoutModal" class="ad-dialog"', false)
                ->assertSee('class="dm-toggle" data-theme-toggle', false)
                ->assertSee('id="sellerReturnsBadge"', false);
            foreach ($data as $text) {
                $response->assertSee($text);
            }
        }
    }
}
