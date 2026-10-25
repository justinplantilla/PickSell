<?php

namespace Tests\Feature\Pickrush;

use App\Models\User;
use App\Pickrush\Enums\StaffRole;
use App\Pickrush\Models\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PortalAccessTest extends TestCase
{
    use BuildsNetwork, RefreshDatabase;

    public function test_the_logistics_host_serves_only_pickrush(): void
    {
        $this->get($this->pickrushUrl('/'))->assertOk()->assertSee('From the seller');
        $this->get($this->pickrushUrl('/login'))->assertOk()->assertSee('Staff sign-in')->assertSee('Pickrush Logistics');

        foreach (['/shop', '/about', '/register', '/buyer/dashboard', '/admin/dashboard'] as $marketplacePage) {
            $this->get($this->pickrushUrl($marketplacePage))->assertNotFound();
        }

        // The marketplace host is untouched.
        $this->get(config('app.url').'/login')->assertOk()->assertSee('Log in to your PickSell account');
        $this->get(config('app.url').'/hubs')->assertNotFound();
    }

    public function test_the_logistics_portal_uses_its_own_session_cookie(): void
    {
        $response = $this->get($this->pickrushUrl('/login'));

        $this->assertNotNull($response->getCookie(config('pickrush.session_cookie'), false));
    }

    public function test_staff_sign_in_and_out(): void
    {
        $this->buildNetwork();
        $manager = $this->staff(StaffRole::HubManager, $this->station('041029000'));

        $this->post($this->pickrushUrl('/login'), ['email' => strtoupper($manager->email), 'password' => 'secret-password'])
            ->assertRedirect(route('pickrush.dashboard'));
        $this->assertAuthenticatedAs($manager, 'pickrush');
        $this->assertGuest('web');
        $this->assertNotNull($manager->fresh()->last_login_at);

        $this->get($this->pickrushUrl('/'))->assertRedirect(route('pickrush.station.dashboard'));
        $this->get(route('pickrush.station.dashboard'))->assertOk()->assertSee('Tanauan City Station')->assertSee('Hub manager');

        $this->post($this->pickrushUrl('/logout'))->assertRedirect($this->pickrushUrl('/login'));
        $this->assertGuest('pickrush');
        $this->assertSame(['Staff.signed_in', 'Staff.signed_out'], AuditLog::where('action', 'like', 'Staff.signed_%')->pluck('action')->all());
    }

    public function test_inactive_and_marketplace_accounts_cannot_sign_in(): void
    {
        $this->buildNetwork();
        $inactive = $this->staff(StaffRole::Dispatcher, $this->station('041029000'), ['is_active' => false]);
        $marketplaceAdmin = User::create([
            'role' => 'admin', 'status' => 'approved', 'first_name' => 'Market', 'last_name' => 'Admin', 'sex' => 'Male',
            'email' => 'admin@picksell.test', 'password' => bcrypt('secret-password'), 'contact_no' => '09170000000',
            'birthday' => '1990-01-01', 'age' => 35, 'province' => 'Batangas', 'municipality' => 'Tanauan', 'barangay' => 'Altura',
        ]);

        $this->post($this->pickrushUrl('/login'), ['email' => $inactive->email, 'password' => 'secret-password'])
            ->assertSessionHasErrors(['email' => 'This account is inactive. Ask HQ to activate it.']);
        $this->post($this->pickrushUrl('/login'), ['email' => $marketplaceAdmin->email, 'password' => 'secret-password'])
            ->assertSessionHasErrors(['email' => 'These credentials do not match a Pickrush account.']);
        $this->assertGuest('pickrush');
    }

    public function test_a_session_ends_when_hq_deactivates_the_account(): void
    {
        $this->buildNetwork();
        $dispatcher = $this->staff(StaffRole::Dispatcher, $this->station('041029000'));
        $this->actingAs($dispatcher, 'pickrush')->get(route('pickrush.station.dashboard'))->assertOk();

        $dispatcher->update(['is_active' => false]);

        $this->get(route('pickrush.station.dashboard'))->assertRedirect($this->pickrushUrl('/login'));
        $this->assertGuest('pickrush');
    }

    public function test_hub_directory_is_for_hq_roles_only(): void
    {
        $this->buildNetwork();
        $tanauan = $this->station('041029000');
        $this->staff(StaffRole::HubManager, $this->station('043405000'));

        $this->actingAs($this->staff(StaffRole::Dispatcher, $tanauan), 'pickrush')
            ->get($this->pickrushUrl('/hubs'))->assertForbidden();

        $admin = $this->staff(StaffRole::SuperAdmin);
        $this->actingAs($admin, 'pickrush')->get($this->pickrushUrl('/hubs'))->assertOk()
            ->assertSee('Batangas Province Hub')->assertSee('Metro Manila Eastern District Hub')->assertSee('Quezon City Station');
        $this->actingAs($admin, 'pickrush')->get($this->pickrushUrl('/hubs?readiness=no_manager&type=station'))->assertOk()
            ->assertSee('Tanauan City Station')->assertDontSee('Calamba City Station')->assertDontSee('P-041000000');
        $this->actingAs($admin, 'pickrush')->get($this->pickrushUrl('/hubs/'.$tanauan->parent_hub_id))->assertOk()
            ->assertSee('Stations sorting through this hub (2)')->assertSee('San Jose Station');
        $this->actingAs($admin, 'pickrush')->get($this->pickrushUrl('/'))->assertRedirect(route('pickrush.hq.dashboard'));
        $this->actingAs($admin, 'pickrush')->get(route('pickrush.hq.dashboard'))->assertOk()->assertSee('3 province hubs');
    }
}
