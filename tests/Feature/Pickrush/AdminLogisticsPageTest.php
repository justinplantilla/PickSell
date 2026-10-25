<?php

namespace Tests\Feature\Pickrush;

use App\Models\User;
use App\Pickrush\Models\ParcelException;
use App\Pickrush\Services\ParcelRegistrar;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminLogisticsPageTest extends TestCase
{
    use BuildsNetwork, RefreshDatabase;

    public function test_admin_sees_pickrush_as_the_logistics_company_with_its_open_exceptions(): void
    {
        $this->buildNetwork();
        $parcel = app(ParcelRegistrar::class)->register([
            'shipper_name' => 'Tote House', 'pickup_municipality_code' => '041029000', 'pickup_barangay_code' => '041029001',
            'consignee_name' => 'Ana Reyes', 'delivery_municipality_code' => '041005000', 'cod_amount' => 900,
        ]);
        ParcelException::create([
            'hub_id' => $this->station('041029000')->id, 'parcel_id' => $parcel->id,
            'type' => 'damaged', 'note' => 'Box crushed on one corner',
        ]);
        $admin = User::create([
            'first_name' => 'Admin', 'last_name' => 'Logistics', 'email' => 'admin@logistics.test',
            'password' => bcrypt('password'), 'role' => 'admin', 'status' => 'approved', 'sex' => 'Male',
            'contact_no' => '09171234567', 'birthday' => '1990-01-01', 'age' => 35,
            'province' => 'Metro Manila', 'municipality' => 'Quezon City', 'barangay' => 'Bahay',
        ]);

        $this->actingAs($admin)->get('/admin/logistics')->assertOk()
            ->assertSeeInOrder(['Logistics company', 'Open Pickrush portal', '<strong>Pickrush</strong>'], false)
            ->assertSee('https://'.config('pickrush.domain'), false)
            ->assertSee('Open logistics exceptions (1)')
            ->assertSee($parcel->tracking_no)
            ->assertSee('Damaged packaging')
            ->assertSee('Box crushed on one corner');
    }
}
