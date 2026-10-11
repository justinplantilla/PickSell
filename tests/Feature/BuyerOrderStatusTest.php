<?php

namespace Tests\Feature;

use App\Models\DeliveryLog;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BuyerOrderStatusTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role, int $number): User
    {
        return User::create([
            'first_name' => ucfirst($role),
            'last_name' => "Status{$number}",
            'email' => "{$role}-status{$number}@example.test",
            'password' => bcrypt('password'),
            'role' => $role,
            'status' => 'approved',
            'sex' => 'Male',
            'contact_no' => '09171234567',
            'birthday' => '1990-01-01',
            'age' => 36,
            'province' => 'Metro Manila',
            'municipality' => 'Pasig',
            'barangay' => 'San Miguel',
        ]);
    }

    private function order(User $buyer, User $seller, string $status, int $number): Order
    {
        return Order::create([
            'order_number' => "ORD-STATUS-{$number}",
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
            'product_name' => 'Status test product',
            'quantity' => 1,
            'amount' => 100,
            'commission' => 10,
            'status' => $status,
        ]);
    }

    public function test_buyer_status_tabs_filter_canonical_lifecycle_states(): void
    {
        $buyer = $this->user('buyer', 1);
        $seller = $this->user('seller', 2);
        $legacyPending = $this->order($buyer, $seller, 'pending', 7);
        $preparing = $this->order($buyer, $seller, 'preparing', 1);
        $legacyProcessing = $this->order($buyer, $seller, 'processing', 8);
        $sorted = $this->order($buyer, $seller, 'sorted', 2);
        $outForDelivery = $this->order($buyer, $seller, 'out_for_delivery', 3);
        $legacyShipped = $this->order($buyer, $seller, 'shipped', 9);
        $failed = $this->order($buyer, $seller, 'delivery_failed', 4);
        $delivered = $this->order($buyer, $seller, 'delivered', 5);
        $returned = $this->order($buyer, $seller, 'returned', 6);

        $this->actingAs($buyer)
            ->get(route('buyer.orders', ['status' => 'pending']))
            ->assertOk()
            ->assertSee($legacyPending->order_number)
            ->assertDontSee($preparing->order_number);

        $this->get(route('buyer.orders', ['status' => 'processing']))
            ->assertOk()
            ->assertSee($preparing->order_number)
            ->assertSee($legacyProcessing->order_number)
            ->assertSee($sorted->order_number)
            ->assertDontSee($outForDelivery->order_number);

        $this->get(route('buyer.orders', ['status' => 'shipped']))
            ->assertOk()
            ->assertSee($outForDelivery->order_number)
            ->assertSee($legacyShipped->order_number)
            ->assertSee($failed->order_number)
            ->assertDontSee($delivered->order_number);

        $this->get(route('buyer.orders', ['status' => 'completed']))
            ->assertOk()
            ->assertSee($delivered->order_number)
            ->assertDontSee($returned->order_number);

        $this->get(route('buyer.orders', ['status' => 'returned']))
            ->assertOk()
            ->assertSee($returned->order_number)
            ->assertDontSee($delivered->order_number);
    }

    public function test_tracking_highlights_the_latest_event_without_reversing_history(): void
    {
        $buyer = $this->user('buyer', 1);
        $seller = $this->user('seller', 2);
        $order = $this->order($buyer, $seller, 'out_for_delivery', 1);

        DeliveryLog::create([
            'delivery_id' => $order->delivery->id,
            'actor_id' => null,
            'actor_role' => 'system',
            'to_status' => 'picked_up',
            'note' => 'Earlier scan',
        ]);
        DeliveryLog::create([
            'delivery_id' => $order->delivery->id,
            'actor_id' => null,
            'actor_role' => 'system',
            'to_status' => 'out_for_delivery',
            'note' => 'Latest scan',
        ]);

        $response = $this->actingAs($buyer)
            ->get(route('buyer.orders.tracking', $order))
            ->assertOk()
            ->assertSeeInOrder(['Earlier scan', 'Latest scan']);

        $this->assertMatchesRegularExpression(
            '~<li class="tracking-event is-latest"[^>]*>.*?Latest scan~s',
            $response->getContent(),
        );
    }
}
