<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthStatusLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_deactivated_users_cannot_log_in(): void
    {
        User::create([
            'first_name' => 'Deactivated',
            'last_name' => 'User',
            'email' => 'deactivated@example.com',
            'password' => bcrypt('password123'),
            'role' => 'buyer',
            'status' => 'deactivated',
            'sex' => 'Female',
            'contact_no' => '09171234567',
            'birthday' => '1995-01-01',
            'age' => 31,
            'province' => 'Metro Manila',
            'municipality' => 'Quezon City',
            'barangay' => 'Bahay',
        ]);

        $this->from('/login')
            ->post('/login', [
                'email' => 'deactivated@example.com',
                'password' => 'password123',
            ])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }
}
