<?php

namespace Tests\Feature;

use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_statuses_include_business_lifecycle_values(): void
    {
        $statuses = Order::statusLifecycle();

        $this->assertContains('placed', $statuses);
        $this->assertContains('confirmed', $statuses);
        $this->assertContains('preparing', $statuses);
        $this->assertContains('ready_for_pickup', $statuses);
        $this->assertContains('picked_up', $statuses);
        $this->assertContains('at_sorting_center', $statuses);
        $this->assertContains('sorted', $statuses);
        $this->assertContains('assigned_to_rider', $statuses);
        $this->assertContains('out_for_delivery', $statuses);
        $this->assertContains('delivered', $statuses);
        $this->assertContains('completed', $statuses);
        $this->assertContains('delivery_failed', $statuses);
        $this->assertContains('returned', $statuses);
        $this->assertContains('cancelled', $statuses);
    }

    public function test_order_transition_uses_business_lifecycle_values(): void
    {
        $seller = \App\Models\User::create([
            'first_name' => 'Seller',
            'last_name' => 'User',
            'email' => 'seller.lifecycle@example.com',
            'password' => bcrypt('password'),
            'role' => 'seller',
            'status' => 'approved',
            'sex' => 'Male',
            'contact_no' => '09170000011',
            'birthday' => '1990-01-01',
            'age' => 36,
            'province' => 'Metro Manila',
            'municipality' => 'Quezon City',
            'barangay' => 'Bahay',
        ]);

        $buyer = \App\Models\User::create([
            'first_name' => 'Buyer',
            'last_name' => 'User',
            'email' => 'buyer.lifecycle@example.com',
            'password' => bcrypt('password'),
            'role' => 'buyer',
            'status' => 'approved',
            'sex' => 'Female',
            'contact_no' => '09170000012',
            'birthday' => '1994-01-01',
            'age' => 32,
            'province' => 'Metro Manila',
            'municipality' => 'Pasig',
            'barangay' => 'San Miguel',
        ]);

        $order = Order::create([
            'order_number' => 'ORD-LIFECYCLE-1',
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
            'product_name' => 'Lifecycle Product',
            'quantity' => 1,
            'amount' => 1500,
            'commission' => 150,
            'status' => 'placed',
            'tracking_status' => 'Awaiting seller preparation',
        ]);

        $this->actingAs($seller)
            ->patch('/seller/orders/' . $order->id . '/pack')
            ->assertRedirect();

        $order->refresh();
        $this->assertSame('preparing', $order->status);

        $admin = \App\Models\User::create([
            'first_name' => 'Admin', 'last_name' => 'User', 'email' => 'admin.lifecycle@example.com',
            'password' => bcrypt('password'), 'role' => 'admin', 'status' => 'approved', 'sex' => 'Male',
            'contact_no' => '09170000014', 'birthday' => '1985-01-01', 'age' => 41,
            'province' => 'Metro Manila', 'municipality' => 'Quezon City', 'barangay' => 'Bahay',
        ]);

        $this->actingAs($seller)->post('/seller/orders/' . $order->id . '/waybill')->assertRedirect();
        $this->assertSame('preparing', $order->fresh()->status, 'printing the waybill does not call a rider');
        $this->assertSame(0, $buyer->notifications()->count());

        $this->actingAs($seller)
            ->patch('/seller/orders/' . $order->id . '/handover')
            ->assertRedirect();

        $order->refresh();
        $this->assertSame('ready_for_pickup', $order->status);
        $this->assertSame(1, $buyer->notifications()->count(), 'The buyer is told the parcel was handed over.');

        // Pickrush carries the parcel from here; its bridge moves the order to delivered.
        foreach (['picked_up', 'at_sorting_center', 'sorted', 'assigned_to_rider', 'out_for_delivery', 'delivered'] as $status) {
            $order->update(['status' => $status]);
        }
        $this->assertNotNull($order->fresh()->delivered_at);

        $this->actingAs($seller)
            ->patch('/seller/orders/' . $order->id . '/confirm-delivery')
            ->assertRedirect();
        $this->assertSame('completed', $order->fresh()->status);
        $this->assertSame(
            ['out_for_delivery', 'delivered', 'completed'],
            $order->statusHistories()->whereIn('to_status', ['out_for_delivery', 'delivered', 'completed'])
                ->orderBy('id')->pluck('to_status')->all(),
        );
    }
}
