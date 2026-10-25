<?php

namespace Tests\Feature\Pickrush;

use App\Pickrush\Enums\StaffRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LandingPageTest extends TestCase
{
    use BuildsNetwork, RefreshDatabase;

    public function test_guests_get_the_landing_page_with_tracking_and_staff_sign_in(): void
    {
        $this->get($this->pickrushUrl('/'))->assertOk()
            ->assertSee("PickSell's delivery network", false)
            ->assertSee('How a parcel gets to you')
            ->assertSee('action="'.route('pickrush.track').'"', false)
            ->assertSee('name="no"', false)
            ->assertSee('href="'.route('pickrush.login').'"', false)
            ->assertSee('aria-label="A Pickrush rider picks up a parcel at a PickSell shop, rides across town and delivers it to a house"', false)
            ->assertSeeInOrder(['Picked up', 'In transit', 'Out for delivery', 'Delivered'])
            ->assertSee('id="prk-bowl-dark"', false)
            ->assertSee('images/pickrush-logo.svg', false)
            ->assertDontSee('>PR<', false);
    }

    public function test_the_rider_app_button_shows_only_when_a_link_is_configured(): void
    {
        config(['pickrush.rider_app_url' => null]);
        $this->get($this->pickrushUrl('/'))->assertDontSee('Get the rider app');

        config(['pickrush.rider_app_url' => 'https://example.com/rider-app']);
        $this->get($this->pickrushUrl('/'))->assertSee('href="https://example.com/rider-app"', false);
    }

    public function test_signed_in_staff_skip_the_landing_page(): void
    {
        $this->buildNetwork();
        $manager = $this->staff(StaffRole::HubManager, $this->station('041029000'));

        $this->actingAs($manager, 'pickrush')->get($this->pickrushUrl('/'))
            ->assertRedirect(route('pickrush.station.dashboard'));
    }

    public function test_the_sign_in_page_uses_the_new_split_layout(): void
    {
        $this->get($this->pickrushUrl('/login'))->assertOk()
            ->assertSee('class="prl-panel"', false)
            ->assertSee('Staff sign-in')
            ->assertSee('data-password-toggle="password"', false)
            ->assertSee('href="'.route('pickrush.track').'"', false);
    }
}
