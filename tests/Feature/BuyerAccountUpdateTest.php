<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class BuyerAccountUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_buyer_can_edit_registration_fields_on_profile(): void
    {
        $user = User::create([
            'role' => 'buyer',
            'status' => 'approved',
            'last_name' => 'Dela Cruz',
            'first_name' => 'Juan',
            'middle_initial' => 'A',
            'sex' => 'Male',
            'email' => 'juan@example.com',
            'password' => Hash::make('secret123'),
            'contact_no' => '09123456789',
            'birthday' => '1995-01-01',
            'age' => 31,
            'province' => 'Cebu',
            'municipality' => 'Lapu-Lapu',
            'barangay' => 'Pajo',
            'street' => 'Old Street',
            'house_no' => '123',
            'id_upload' => 'uploads/ids/original.jpg',
        ]);

        $this->actingAs($user)
            ->patch('/buyer/account', [
                'first_name' => 'Juan Carlos',
                'last_name' => 'Reyes',
                'middle_initial' => 'B',
                'sex' => 'Male',
                'email' => 'juan@example.com',
                'contact_no' => '09987654321',
                'birthday' => '1990-02-02',
                'province' => 'Metro Manila',
                'municipality' => 'Makati',
                'barangay' => 'San Antonio',
                'street' => 'New Avenue',
                'house_no' => '456',
            ])
            ->assertSessionHas('success');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'first_name' => 'Juan Carlos',
            'last_name' => 'Reyes',
            'middle_initial' => 'B',
            'sex' => 'Male',
            'contact_no' => '09987654321',
            'province' => 'Metro Manila',
            'municipality' => 'Makati',
            'barangay' => 'San Antonio',
            'street' => 'New Avenue',
            'house_no' => '456',
        ]);
    }

    public function test_buyer_profile_can_be_updated_via_api_route(): void
    {
        $user = User::create([
            'role' => 'buyer',
            'status' => 'approved',
            'last_name' => 'Dela Cruz',
            'first_name' => 'Juan',
            'middle_initial' => 'A',
            'sex' => 'Male',
            'email' => 'juanapi@example.com',
            'password' => Hash::make('secret123'),
            'contact_no' => '09123456789',
            'birthday' => '1995-01-01',
            'age' => 31,
            'province' => 'Cebu',
            'municipality' => 'Lapu-Lapu',
            'barangay' => 'Pajo',
            'street' => 'Old Street',
            'house_no' => '123',
            'id_upload' => 'uploads/ids/original.jpg',
        ]);

        $response = $this->actingAs($user, 'web')
            ->json('PATCH', '/api/buyer/account', [
                'first_name' => 'Juan Carlos',
                'last_name' => 'Reyes',
                'middle_initial' => 'B',
                'sex' => 'Male',
                'email' => 'juanapi@example.com',
                'contact_no' => '09987654321',
                'birthday' => '1990-02-02',
                'province' => 'Metro Manila',
                'municipality' => 'Makati',
                'barangay' => 'San Antonio',
                'street' => 'New Avenue',
                'house_no' => '456',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'first_name' => 'Juan Carlos',
            'last_name' => 'Reyes',
            'middle_initial' => 'B',
            'contact_no' => '09987654321',
            'province' => 'Metro Manila',
            'municipality' => 'Makati',
            'barangay' => 'San Antonio',
        ]);
    }
}
