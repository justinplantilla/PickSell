<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Notifications\BuyerOrderUpdate;
use App\Notifications\SellerShippingDeadline;
use App\Services\Orders\OrderLifecycleService;
use App\Services\Orders\ShippingDeadlines;
use App\Services\SellerPerformance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * From "preparing" the seller has 48 hours (a pre-order: its own days) to hand the parcel to the
 * courier; late orders are cancelled automatically and count against the seller. Buyers can
 * cancel only until the seller starts preparing.
 */
class ShippingDeadlinesTest extends TestCase
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

    public function test_preparing_starts_a_48_hour_clock_and_a_late_order_is_cancelled_against_the_seller(): void
    {
        Notification::fake();
        $order = $this->order();
        app(OrderLifecycleService::class)->transition($order, 'preparing', $this->seller->id, 'seller', null);
        $this->assertEqualsWithDelta(now()->addHours(48)->timestamp, $order->fresh()->ship_by->timestamp, 5);

        // 12 hours before: one reminder.
        $this->travel(37)->hours();
        $this->assertSame(1, app(ShippingDeadlines::class)->run()['reminded']);
        $this->assertSame(0, app(ShippingDeadlines::class)->run()['reminded']);
        Notification::assertSentTo($this->seller, SellerShippingDeadline::class);

        $this->travel(12)->hours();
        $this->assertSame(1, app(ShippingDeadlines::class)->run()['cancelled']);
        $order->refresh();
        $this->assertSame('cancelled', $order->status);
        $this->assertNotNull($order->late_cancelled_at);
        Notification::assertSentTo($this->buyer, BuyerOrderUpdate::class);

        $performance = app(SellerPerformance::class)->for($this->seller);
        $this->assertSame(1, $performance['late']);
        $this->assertContains('1 order(s) cancelled for shipping late', $performance['warnings']);
    }

    public function test_an_order_handed_over_in_time_is_not_cancelled_and_pre_orders_get_their_own_days(): void
    {
        $order = $this->order();
        app(OrderLifecycleService::class)->transition($order, 'preparing', $this->seller->id, 'seller', null);
        app(OrderLifecycleService::class)->transition($order->fresh(), 'ready_for_pickup', $this->seller->id, 'seller', null, ['handed_over_at' => now()]);
        $this->travel(3)->days();
        app(ShippingDeadlines::class)->run();
        $this->assertSame('ready_for_pickup', $order->fresh()->status);

        $preorder = $this->order(['preorder_days' => 10]);
        app(OrderLifecycleService::class)->transition($preorder, 'preparing', $this->seller->id, 'seller', null);
        $this->assertEqualsWithDelta(now()->addDays(10)->timestamp, $preorder->fresh()->ship_by->timestamp, 5);
        $this->travel(5)->days();
        app(ShippingDeadlines::class)->run();
        $this->assertSame('preparing', $preorder->fresh()->status);
    }

    public function test_buyers_cancel_only_until_the_seller_starts_preparing(): void
    {
        $order = $this->order();
        $this->actingAs($this->buyer)->get($this->url('/buyer/orders'))->assertSee('Cancel Order');

        app(OrderLifecycleService::class)->transition($order, 'preparing', $this->seller->id, 'seller', null);
        $this->actingAs($this->buyer)->get($this->url('/buyer/orders'))->assertDontSee('data-cancel-order', false)->assertSee('Ships by');
        $this->actingAs($this->buyer)->patch($this->url("/buyer/orders/{$order->id}/cancel"))->assertStatus(409);
        $this->assertSame('preparing', $order->fresh()->status);

        // The seller still can.
        $this->actingAs($this->seller)->patch($this->url("/seller/orders/{$order->id}/cancel"))->assertSessionHasNoErrors();
        $this->assertSame('cancelled', $order->fresh()->status);

        $other = $this->order();
        $this->actingAs($this->buyer)->patch($this->url("/buyer/orders/{$other->id}/cancel"))->assertSessionHasNoErrors();
        $this->assertSame('cancelled', $other->fresh()->status);
    }

    private function order(array $product = []): Order
    {
        $product = Product::create(array_merge(['seller_id' => $this->seller->id, 'name' => 'Canvas Tote', 'category' => 'Fashion', 'price' => 500, 'stock' => 10, 'status' => 'active', 'weight_kg' => 1], $product));

        return Order::create([
            'order_number' => 'ORD-'.strtoupper(\Illuminate\Support\Str::random(8)), 'product_id' => $product->id, 'buyer_id' => $this->buyer->id,
            'seller_id' => $this->seller->id, 'product_name' => $product->name, 'quantity' => 1, 'amount' => 500, 'commission' => 0, 'commission_rate' => 0, 'status' => 'placed',
        ]);
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
            'role' => $role, 'status' => 'approved', 'first_name' => ucfirst($role), 'last_name' => "Ship{$n}", 'sex' => 'Female',
            'email' => "{$role}{$n}@ship.test", 'password' => bcrypt('secret-password'), 'contact_no' => '09170000000',
            'birthday' => '1995-01-01', 'age' => 30, 'province' => 'Batangas', 'municipality' => 'City of Tanauan', 'barangay' => 'Altura Bata',
            'house_no' => '12', 'street' => 'Rizal St',
        ], $extra));
    }
}
