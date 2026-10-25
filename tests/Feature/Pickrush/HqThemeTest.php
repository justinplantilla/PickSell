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

    public function test_station_accounts_get_the_console_theme_with_every_menu_link(): void
    {
        $this->buildNetwork();
        $manager = $this->staff(StaffRole::HubManager, $this->station('041029000'));

        $page = $this->actingAs($manager, 'pickrush')->get(route('pickrush.station.dashboard'))->assertOk()
            ->assertSee('class="pr-hq pr-station"', false)
            ->assertSee('Station console')
            ->assertSee('pr-nav-group', false)
            ->assertSee(route('pickrush.account'), false)
            // Every dashboard figure is still there, in the new layout.
            ->assertSeeInOrder(['On the way here', 'Arrived today', 'Waiting for a rider', 'Out for delivery', 'Delivered today', 'Failed today', 'Seller pickups waiting'])
            ->assertSeeInOrder(['COD on the road', 'Cash at the station, not yet remitted', 'Riders on shift / active', 'Parcels needing an address fix']);

        foreach (Navigation::for($manager) as $item) {
            $page->assertSee('href="'.route($item['route']).'"', false);
        }
    }

    public function test_province_accounts_get_the_console_theme_with_every_menu_link(): void
    {
        $this->buildNetwork();
        $sorter = $this->staff(StaffRole::Sorter, $this->station('041029000')->parent);

        $page = $this->actingAs($sorter, 'pickrush')->get(route('pickrush.province.dashboard'))->assertOk()
            ->assertSee('class="pr-hq pr-province"', false)
            ->assertSee('Province console')
            ->assertSee('pr-nav-group', false)
            ->assertSee(route('pickrush.account'), false)
            // Every dashboard figure is still there, in the new layout.
            ->assertSeeInOrder(['Trips on the road to here', 'On hand', 'Not sorted yet', 'Aging (over', 'Open exceptions'])
            ->assertSeeInOrder(['Today', 'trips dispatched', 'Last 7 days', 'Average dwell time']);

        foreach (Navigation::for($sorter) as $item) {
            $page->assertSee('href="'.route($item['route']).'"', false);
        }
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
