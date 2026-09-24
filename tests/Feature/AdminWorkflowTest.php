<?php

namespace Tests\Feature;

use App\Models\Complaint;
use App\Models\PlatformSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_settings_are_persisted(): void
    {
        $admin = User::create([
            'first_name' => 'Admin',
            'last_name' => 'User',
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
            'status' => 'approved',
            'sex' => 'Male',
            'contact_no' => '09171234567',
            'birthday' => '1990-01-01',
            'age' => 35,
            'province' => 'Metro Manila',
            'municipality' => 'Quezon City',
            'barangay' => 'Bahay',
        ]);

        $this->actingAs($admin)
            ->post('/admin/settings', [
                'platform_name' => 'PickSell Pro',
                'support_email' => 'support@example.com',
                'commission_rate' => '12.5',
                'max_file_upload_mb' => '10',
            ])
            ->assertRedirect();

        $this->assertSame('PickSell Pro', PlatformSetting::get('platform_name'));
        $this->assertSame('support@example.com', PlatformSetting::get('support_email'));
        $this->assertSame('12.5', PlatformSetting::get('commission_rate'));
        $this->assertSame('10', PlatformSetting::get('max_file_upload_mb'));
    }

    public function test_complaint_update_notifies_filer_and_accused(): void
    {
        $admin = User::create([
            'first_name' => 'Admin',
            'last_name' => 'User',
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
            'status' => 'approved',
            'sex' => 'Male',
            'contact_no' => '09171234567',
            'birthday' => '1990-01-01',
            'age' => 35,
            'province' => 'Metro Manila',
            'municipality' => 'Quezon City',
            'barangay' => 'Bahay',
        ]);

        $filer = User::create([
            'first_name' => 'Buyer',
            'last_name' => 'One',
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

        $accused = User::create([
            'first_name' => 'Seller',
            'last_name' => 'One',
            'email' => 'seller@example.com',
            'password' => bcrypt('password'),
            'role' => 'seller',
            'status' => 'approved',
            'sex' => 'Male',
            'contact_no' => '09170000002',
            'birthday' => '1989-01-01',
            'age' => 37,
            'province' => 'Metro Manila',
            'municipality' => 'Makati',
            'barangay' => 'Poblacion',
        ]);

        $complaint = Complaint::create([
            'filed_by' => $filer->id,
            'against_user_id' => $accused->id,
            'subject' => 'Late delivery',
            'details' => 'The parcel arrived too late.',
            'status' => 'open',
        ]);

        $this->actingAs($admin)
            ->patch('/admin/complaints/' . $complaint->id, [
                'status' => 'resolved',
                'admin_notes' => 'Issue resolved after coordination.',
            ])
            ->assertRedirect();

        $this->assertGreaterThan(0, $filer->notifications()->count());
        $this->assertGreaterThan(0, $accused->notifications()->count());
    }

    public function test_admin_chat_lists_approved_users_even_without_history(): void
    {
        $admin = User::create([
            'first_name' => 'Admin',
            'last_name' => 'User',
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
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
            'last_name' => 'Contact',
            'email' => 'buyer-contact@example.com',
            'password' => bcrypt('password'),
            'role' => 'buyer',
            'status' => 'approved',
            'sex' => 'Female',
            'contact_no' => '09170000003',
            'birthday' => '1995-01-01',
            'age' => 31,
            'province' => 'Metro Manila',
            'municipality' => 'Taguig',
            'barangay' => 'Lower Bicutan',
        ]);

        $this->actingAs($admin)
            ->get('/admin/chat')
            ->assertOk()
            ->assertSee('Buyer Contact');

        $this->assertDatabaseHas('users', ['id' => $buyer->id, 'status' => 'approved']);
    }
}
