<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BuyerChatTest extends TestCase
{
    use RefreshDatabase;

    public function test_buyer_can_open_chat_with_no_contacts_or_history(): void
    {
        $buyer = $this->user('buyer', 'buyer@example.test');

        $this->actingAs($buyer)
            ->get(route('buyer.chat'))
            ->assertOk();
    }

    public function test_buyer_can_open_chat_when_ordered_seller_is_the_only_contact(): void
    {
        $buyer = $this->user('buyer', 'buyer@example.test');
        $seller = $this->user('seller', 'seller@example.test');
        $product = Product::create([
            'seller_id' => $seller->id,
            'name' => 'Ordered item',
            'category' => 'Electronics',
            'price' => 100,
            'stock' => 5,
            'status' => 'active',
        ]);
        Order::create([
            'order_number' => 'BUYER-CHAT-1',
            'product_id' => $product->id,
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
            'product_name' => $product->name,
            'quantity' => 1,
            'amount' => $product->price,
            'commission' => 0,
            'status' => 'delivered',
        ]);

        $this->actingAs($buyer)
            ->get(route('buyer.chat'))
            ->assertOk()
            ->assertSee($seller->first_name);
    }

    private function user(string $role, string $email): User
    {
        return User::create([
            'first_name' => ucfirst($role),
            'last_name' => 'Chat',
            'email' => $email,
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
}
