<?php

namespace Tests\Feature;

use App\Models\Message;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\Finance\FinancialLedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Seller insight pages: what discounts took off earnings, date-to-date ranges on the dashboard
 * and analytics, and the asked-about product shown inside the chat thread.
 */
class SellerInsightsTest extends TestCase
{
    use RefreshDatabase;

    private User $seller;

    private User $buyer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seller = $this->user('seller', ['business_name' => 'Tote House']);
        $this->buyer = $this->user('buyer');
    }

    public function test_earnings_show_what_discounts_took_off(): void
    {
        $order = $this->order(['amount' => 400, 'product_discount' => 80, 'store_discount' => 20, 'status' => 'completed', 'commission' => 40, 'commission_rate' => 10]);
        app(FinancialLedgerService::class)->postCompletedOrder($order);

        $this->actingAs($this->seller)->get($this->url('/seller/earnings'))->assertOk()
            ->assertSee('Given away in discounts')->assertSee('-₱100.00')
            ->assertSee('₱80.00 product discounts &amp; sales', false)->assertSee('₱20.00 store vouchers')
            ->assertSee('List price')->assertSee('₱500.00');
    }

    public function test_dashboard_and_analytics_take_a_date_to_date_range(): void
    {
        $this->order(['status' => 'completed', 'amount' => 300, 'created_at' => '2026-10-05 10:00:00']);
        $this->order(['status' => 'completed', 'amount' => 999, 'created_at' => '2026-08-01 10:00:00']);

        $this->actingAs($this->seller)->get($this->url('/seller/dashboard?from=2026-10-01&to=2026-10-10'))->assertOk()
            ->assertSee('from Oct 01, 2026 – Oct 10, 2026')->assertSee('vs the same number of days before');

        $this->actingAs($this->seller)->get($this->url('/seller/analytics?from=2026-10-01&to=2026-10-10'))->assertOk()
            ->assertSee('Showing Oct 01, 2026 – Oct 10, 2026')->assertSee('₱300.00')->assertDontSee('₱999.00');

        // A reversed or malformed range falls back to the preset.
        $this->actingAs($this->seller)->get($this->url('/seller/analytics?from=2026-10-10&to=2026-10-01'))->assertOk()->assertDontSee('Showing Oct 10, 2026');
        $this->actingAs($this->seller)->get($this->url('/seller/dashboard?from=nope&to=2026-10-01'))->assertOk()->assertSee('across the last 30 days');
    }

    public function test_the_asked_about_product_sits_inside_the_conversation(): void
    {
        $tote = Product::create(['seller_id' => $this->seller->id, 'name' => 'Canvas Tote', 'category' => 'Fashion', 'price' => 500, 'stock' => 5, 'status' => 'active', 'weight_kg' => 1]);
        Message::create(['sender_id' => $this->buyer->id, 'receiver_id' => $this->seller->id, 'body' => 'Is the tote waterproof?', 'product_id' => $tote->id]);
        Message::create(['sender_id' => $this->seller->id, 'receiver_id' => $this->buyer->id, 'body' => 'Yes, it is coated.']);

        $this->actingAs($this->seller)->get($this->url("/seller/chat?user={$this->buyer->id}"))->assertOk()
            ->assertSeeInOrder(['chat-thread-product', 'Asking about', 'Canvas Tote', 'Is the tote waterproof?', 'Yes, it is coated.'], false);
        $this->actingAs($this->buyer)->get($this->url("/buyer/chat?user={$this->seller->id}"))->assertOk()
            ->assertSeeInOrder(['chat-thread-product', 'Canvas Tote', 'Is the tote waterproof?'], false);
    }

    private function order(array $attributes = []): Order
    {
        $product = Product::create(['seller_id' => $this->seller->id, 'name' => 'Tote '.uniqid(), 'category' => 'Fashion', 'price' => 500, 'stock' => 10, 'status' => 'active', 'weight_kg' => 1]);
        $created = $attributes['created_at'] ?? null;
        unset($attributes['created_at']);
        $order = Order::create(array_merge([
            'order_number' => 'ORD-'.strtoupper(\Illuminate\Support\Str::random(8)), 'product_id' => $product->id, 'buyer_id' => $this->buyer->id,
            'seller_id' => $this->seller->id, 'product_name' => $product->name, 'quantity' => 1, 'amount' => 500, 'commission' => 0, 'commission_rate' => 0, 'status' => 'placed',
        ], $attributes));
        if ($created) {
            $order->forceFill(['created_at' => $created])->saveQuietly();
        }

        return $order->fresh();
    }

    private function url(string $path): string
    {
        return rtrim(config('app.url'), '/').$path;
    }

    private function user(string $role, array $extra = []): User
    {
        static $n = 0;
        $n++;

        return User::create(array_merge([
            'role' => $role, 'status' => 'approved', 'first_name' => ucfirst($role), 'last_name' => "Insight{$n}", 'sex' => 'Female',
            'email' => "{$role}{$n}@insight.test", 'password' => bcrypt('secret-password'), 'contact_no' => '09170000000',
            'birthday' => '1995-01-01', 'age' => 30, 'province' => 'Batangas', 'municipality' => 'City of Tanauan', 'barangay' => 'Altura Bata',
            'house_no' => '12', 'street' => 'Rizal St',
        ], $extra));
    }
}
