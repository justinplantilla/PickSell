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

    public function test_the_welcome_splash_plays_once_after_log_in(): void
    {
        User::create([
            'first_name' => 'Bea',
            'last_name' => 'Buyer',
            'email' => 'bea@example.com',
            'password' => bcrypt('password123'),
            'role' => 'buyer',
            'status' => 'approved',
            'sex' => 'Female',
            'contact_no' => '09171234567',
            'birthday' => '1995-01-01',
            'age' => 31,
            'province' => 'Metro Manila',
            'municipality' => 'Quezon City',
            'barangay' => 'Bahay',
        ]);

        $this->post('/login', ['email' => 'bea@example.com', 'password' => 'password123'])
            ->assertRedirect('/buyer/dashboard');

        $this->get('/buyer/dashboard')->assertOk()->assertSee('data-welcome-splash', false)->assertSee('Welcome back, Bea');
        $this->get('/buyer/dashboard')->assertOk()->assertDontSee('data-welcome-splash', false);
    }

    public function test_a_failed_log_in_does_not_queue_the_welcome_splash(): void
    {
        $this->from('/login')->post('/login', ['email' => 'nobody@example.com', 'password' => 'wrong-password']);

        $this->assertFalse(session()->has('picksell_welcome'));
    }
}
