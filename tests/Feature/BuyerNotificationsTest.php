<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BuyerNotificationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_buyer_can_fetch_notifications(): void
    {
        $buyer = User::create([
            'first_name' => 'Buyer',
            'last_name' => 'User',
            'email' => 'buyer-notify@example.com',
            'password' => bcrypt('password'),
            'role' => 'buyer',
            'status' => 'approved',
            'sex' => 'Male',
            'contact_no' => '09170000007',
            'birthday' => '1992-01-01',
            'age' => 34,
            'province' => 'Metro Manila',
            'municipality' => 'Quezon City',
            'barangay' => 'Bahay',
        ]);

        $buyer->notifications()->create([
            'id' => \Illuminate\Support\Str::uuid(),
            'type' => 'App\\Notifications\\BuyerOrderUpdate',
            'data' => json_encode([
                'order_id' => 1,
                'message' => 'Your order #ORD-123 has been shipped.',
            ]),
        ]);

        $this->actingAs($buyer)
            ->get('/buyer/notifications')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonFragment(['message' => 'Your order #ORD-123 has been shipped.']);
    }
}
