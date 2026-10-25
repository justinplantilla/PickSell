<?php

namespace Tests\Feature\Pickrush;

use App\Pickrush\Enums\StaffRole;
use App\Pickrush\Support\Navigation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HqThemeTest extends TestCase
{
    use BuildsNetwork, RefreshDatabase;

    public function test_hq_accounts_get_the_console_theme_with_every_menu_link(): void
    {
        $this->buildNetwork();
        $admin = $this->staff(StaffRole::SuperAdmin);

        $page = $this->actingAs($admin, 'pickrush')->get(route('pickrush.hq.dashboard'))->assertOk()
            ->assertSee('class="pr-hq"', false)
            ->assertSee('pr-nav-group', false)
            ->assertSee(route('pickrush.account'), false)
            ->assertSee(route('pickrush.logout'), false);

        foreach (Navigation::for($admin) as $item) {
            $page->assertSee('href="'.route($item['route']).'"', false);
        }
    }

    public function test_station_and_province_portals_keep_the_original_look(): void
    {
        $this->buildNetwork();
        $manager = $this->staff(StaffRole::HubManager, $this->station('041029000'));

        $this->actingAs($manager, 'pickrush')->get(route('pickrush.station.dashboard'))->assertOk()
            ->assertDontSee('class="pr-hq"', false)
            ->assertDontSee('pr-nav-group', false)
            ->assertSee('Sign out');
    }

    public function test_signing_out_asks_for_confirmation_in_every_portal(): void
    {
        $this->buildNetwork();
        $logoutForm = 'action="'.route('pickrush.logout').'" data-confirm-logout';

        foreach ([
            [$this->staff(StaffRole::SuperAdmin), 'pickrush.hq.dashboard'],
            [$this->staff(StaffRole::HubManager, $this->station('041029000')), 'pickrush.station.dashboard'],
        ] as [$staff, $route]) {
            $this->actingAs($staff, 'pickrush')->get(route($route))->assertOk()
                ->assertSee($logoutForm, false)
                ->assertSee('id="logoutDialog"', false)
                ->assertSee('Sign out of Pickrush?');
        }

        // The confirmation is client-side only: the logout route still works on a plain POST.
        $this->post($this->pickrushUrl('/logout'))->assertRedirect($this->pickrushUrl('/login'));
        $this->assertGuest('pickrush');
    }
}
