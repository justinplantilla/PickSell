<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderCancellationTest extends TestCase
{
    use RefreshDatabase;

    public function test_buyer_and_seller_can_cancel_only_before_pickup(): void
    {
        [$buyer, $seller] = $this->participants();
        $placed = $this->order($buyer, $seller, 'placed', 'CANCEL-BUYER');
        $preparing = $this->order($buyer, $seller, 'preparing', 'CANCEL-SELLER');

        $this->actingAs($buyer)
            ->patch(route('buyer.orders.cancel', $placed))
            ->assertRedirect();
        $this->actingAs($seller)
            ->patch(route('seller.orders.cancel', $preparing))
            ->assertRedirect();

        $this->assertSame('cancelled', $placed->fresh()->status);
        $this->assertSame('cancelled', $preparing->fresh()->status);
        $this->assertDatabaseHas('order_status_histories', [
            'order_id' => $placed->id,
            'source' => 'buyer',
            'to_status' => 'cancelled',
        ]);
        $this->assertDatabaseHas('order_status_histories', [
            'order_id' => $preparing->id,
            'source' => 'seller',
            'to_status' => 'cancelled',
        ]);
    }

    public function test_neither_party_can_cancel_after_pickup_or_cancel_another_partys_order(): void
    {
        [$buyer, $seller] = $this->participants();
        $readyForPickup = $this->order($buyer, $seller, 'ready_for_pickup', 'CANCEL-AFTER-PICKUP');
        $otherBuyer = $this->user('buyer', 'other-buyer');
        $otherSeller = $this->user('seller', 'other-seller');
        $otherOrder = $this->order($buyer, $seller, 'placed', 'CANCEL-OWNERSHIP');

        $this->actingAs($buyer)
            ->patch(route('buyer.orders.cancel', $readyForPickup))
            ->assertStatus(409);
        $this->actingAs($seller)
            ->patch(route('seller.orders.cancel', $readyForPickup))
            ->assertStatus(409);
        $this->actingAs($otherBuyer)
            ->patch(route('buyer.orders.cancel', $otherOrder))
            ->assertForbidden();
        $this->actingAs($otherSeller)
            ->patch(route('seller.orders.cancel', $otherOrder))
            ->assertForbidden();

        $this->assertSame('ready_for_pickup', $readyForPickup->fresh()->status);
        $this->assertSame('placed', $otherOrder->fresh()->status);
        $this->assertSame(0, OrderStatusHistory::where('to_status', 'cancelled')->count());
    }

    private function participants(): array
    {
        return [
            $this->user('buyer', 'buyer'),
            $this->user('seller', 'seller'),
        ];
    }

    private function order(User $buyer, User $seller, string $status, string $number): Order
    {
        return Order::create([
            'order_number' => $number,
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
            'product_name' => 'Cancellation test parcel',
            'quantity' => 1,
            'amount' => 100,
            'commission' => 10,
            'status' => $status,
        ]);
    }

    private function user(string $role, string $name): User
    {
        return User::create([
            'first_name' => ucfirst($name),
            'last_name' => 'Cancellation',
            'email' => "{$name}@cancellation.test",
            'password' => bcrypt('password'),
            'role' => $role,
            'status' => 'approved',
            'sex' => 'Female',
            'contact_no' => '09171234567',
            'birthday' => '1990-01-01',
            'age' => 36,
            'province' => 'Metro Manila',
            'municipality' => 'Pasig',
            'barangay' => 'San Miguel',
        ]);
    }
}
