<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\BuyerApi\Concerns\BuildsMarketplace;
use Tests\TestCase;

/** A seller's address and shop pin save from My Account, whichever of its two forms is used. */
class SellerAccountLocationTest extends TestCase
{
    use BuildsMarketplace, RefreshDatabase;

    private function profile(array $extra = []): array
    {
        return $extra + [
            'first_name' => 'Ana', 'last_name' => 'Cruz', 'sex' => 'Female', 'birthday' => '1990-01-01',
            'email' => 'ana.seller@example.com', 'contact_no' => '09170000000',
            'province' => 'Laguna', 'municipality' => 'Santa Rosa', 'barangay' => 'Balibago',
        ];
    }

    public function test_a_new_address_is_saved(): void
    {
        $seller = $this->seller();

        $this->actingAs($seller)->patch(route('seller.account.update'), $this->profile())->assertSessionHasNoErrors();

        $this->assertSame(['Laguna', 'Santa Rosa', 'Balibago'], array_values($seller->fresh()->only('province', 'municipality', 'barangay')));
    }

    public function test_a_pin_moved_but_saved_with_the_profile_form_is_kept(): void
    {
        $seller = $this->seller();

        $this->actingAs($seller)->patch(route('seller.account.update'), $this->profile(['pickup_lat' => 14.2905, 'pickup_lng' => 121.106]))
            ->assertSessionHasNoErrors()->assertSessionHas('success', 'Account and shop pin updated.');

        $fresh = $seller->fresh();
        $this->assertEqualsWithDelta(14.2905, (float) $fresh->pickup_lat, 1e-6);
        $this->assertEqualsWithDelta(121.106, (float) $fresh->pickup_lng, 1e-6);
    }

    public function test_a_pin_outside_the_philippines_is_refused(): void
    {
        $seller = $this->seller();

        $this->actingAs($seller)->patch(route('seller.account.update'), $this->profile(['pickup_lat' => 35.6, 'pickup_lng' => 139.7]))
            ->assertSessionHasErrors(['pickup_lat', 'pickup_lng']);
        $this->assertNull($seller->fresh()->pickup_lat);
    }

    public function test_the_page_wires_the_address_to_the_pin(): void
    {
        $this->actingAs($this->seller())->get(route('seller.account'))->assertOk()
            ->assertSee('data-seller-profile-form', false)
            ->assertSee('data-pin-address-note', false)
            ->assertSee('seller-account', false);
    }
}
