<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use App\Notifications\NewSellerOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SellerWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_seller_can_download_waybill_label(): void
    {
        $seller = User::create([
            'first_name' => 'Seller',
            'last_name' => 'User',
            'email' => 'seller@example.com',
            'password' => bcrypt('password'),
            'role' => 'seller',
            'status' => 'approved',
            'sex' => 'Male',
            'contact_no' => '09171234567',
            'birthday' => '1990-01-01',
            'age' => 35,
            'province' => 'Metro Manila',
            'municipality' => 'Quezon City',
            'barangay' => 'Bahay',
        ]);

        $buyer = User::create([
            'first_name' => 'Buyer',
            'last_name' => 'User',
            'email' => 'buyer@example.com',
            'password' => bcrypt('password'),
            'role' => 'buyer',
            'status' => 'approved',
            'sex' => 'Female',
            'contact_no' => '09170000001',
            'birthday' => '1994-01-01',
            'age' => 32,
            'province' => 'Metro Manila',
            'municipality' => 'Pasig',
            'barangay' => 'San Miguel',
        ]);

        $order = Order::create([
            'order_number' => 'ORD-SELLER-1',
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
            'product_name' => 'Premium Sneakers',
            'quantity' => 1,
            'amount' => 1200,
            'commission' => 120,
            'status' => 'shipped',
            'waybill_number' => 'WB-0001',
            'tracking_status' => 'Out for delivery',
        ]);

        $this->actingAs($seller)
            ->get('/seller/orders/' . $order->id . '/waybill')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_seller_can_confirm_delivery(): void
    {
        $seller = User::create([
            'first_name' => 'Seller',
            'last_name' => 'User',
            'email' => 'seller2@example.com',
            'password' => bcrypt('password'),
            'role' => 'seller',
            'status' => 'approved',
            'sex' => 'Male',
            'contact_no' => '09171234568',
            'birthday' => '1990-01-01',
            'age' => 35,
            'province' => 'Metro Manila',
            'municipality' => 'Quezon City',
            'barangay' => 'Bahay',
        ]);

        $buyer = User::create([
            'first_name' => 'Buyer',
            'last_name' => 'User',
            'email' => 'buyer2@example.com',
            'password' => bcrypt('password'),
            'role' => 'buyer',
            'status' => 'approved',
            'sex' => 'Female',
            'contact_no' => '09170000002',
            'birthday' => '1994-01-01',
            'age' => 32,
            'province' => 'Metro Manila',
            'municipality' => 'Pasig',
            'barangay' => 'San Miguel',
        ]);

        $order = Order::create([
            'order_number' => 'ORD-SELLER-2',
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
            'product_name' => 'Desk Lamp',
            'quantity' => 2,
            'amount' => 800,
            'commission' => 80,
            'status' => 'completed',
            'waybill_number' => 'WB-0002',
            'tracking_status' => 'Delivered',
            'delivered_at' => now(),
        ]);

        $this->actingAs($seller)
            ->patch('/seller/orders/' . $order->id . '/confirm-delivery')
            ->assertRedirect();

        $order->refresh();
        $this->assertSame('Seller confirmed delivery', $order->tracking_status);
        $this->assertNotNull($order->confirmed_by_seller_at);
    }

    public function test_new_order_notifies_seller(): void
    {
        $seller = User::create([
            'first_name' => 'Seller',
            'last_name' => 'User',
            'email' => 'seller3@example.com',
            'password' => bcrypt('password'),
            'role' => 'seller',
            'status' => 'approved',
            'sex' => 'Male',
            'contact_no' => '09171234569',
            'birthday' => '1990-01-01',
            'age' => 35,
            'province' => 'Metro Manila',
            'municipality' => 'Quezon City',
            'barangay' => 'Bahay',
        ]);

        $order = Order::make([
            'order_number' => 'ORD-SELLER-3',
            'product_name' => 'Travel Backpack',
            'quantity' => 1,
            'amount' => 1500,
            'commission' => 150,
            'status' => 'pending',
        ]);

        $seller->notify(new NewSellerOrder($order));

        $this->assertNotNull($seller->notifications()->first());
    }
}
