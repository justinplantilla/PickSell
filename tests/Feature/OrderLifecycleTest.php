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

        $this->actingAs($seller)
            ->patch('/seller/orders/' . $order->id . '/handover', [
                'waybill_number' => 'WB-LIFECYCLE-1',
            ])
            ->assertRedirect();

        $order->refresh();
        $this->assertSame('ready_for_pickup', $order->status);

        $courier = \App\Models\User::create([
            'first_name' => 'Courier',
            'last_name' => 'User',
            'email' => 'courier.lifecycle@example.com',
            'password' => bcrypt('password'),
            'role' => 'courier',
            'status' => 'approved',
            'sex' => 'Male',
            'contact_no' => '09170000013',
            'birthday' => '1988-01-01',
            'age' => 38,
            'province' => 'Metro Manila',
            'municipality' => 'Makati',
            'barangay' => 'Poblacion',
            'delivery_area' => 'Makati',
        ]);

        $order->update([
            'courier_id' => $courier->id,
            'status' => 'assigned_to_rider',
            'tracking_status' => 'Assigned to rider',
        ]);

        $this->actingAs($courier)
            ->patch('/courier/orders/' . $order->id . '/status', [
                'status' => 'out_for_delivery',
            ])
            ->assertRedirect();

        $order->refresh();
        $this->assertSame('out_for_delivery', $order->status);
    }
}
