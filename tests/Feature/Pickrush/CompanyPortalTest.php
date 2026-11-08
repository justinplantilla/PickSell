<?php

namespace Tests\Feature\Pickrush;

use App\Pickrush\Enums\StaffRole;
use App\Pickrush\Models\Company;
use App\Pickrush\Models\Hub;
use App\Pickrush\Models\ParcelClass;
use App\Pickrush\Models\Staff;
use App\Pickrush\Services\ParcelRegistrar;
use App\Pickrush\Services\Shipping\RateCardCopier;
use App\Pickrush\Services\Shipping\RouteRateGenerator;
use App\Pickrush\Services\Shipping\ShippingCalculator;
use App\Pickrush\Support\Actor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CompanyPortalTest extends TestCase
{
    use BuildsNetwork, RefreshDatabase;

    private Company $company;

    private Staff $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildNetwork();
        DB::table('pr_shipping_region_provinces')->insertOrIgnore([['name' => 'Batangas', 'region_code' => 'LUZON'], ['name' => 'Laguna', 'region_code' => 'LUZON']]);

        $this->company = Company::create(['name' => 'Bilis Hatid Express Inc.', 'slug' => 'bilis-hatid', 'tracking_prefix' => 'BH', 'status' => 'approved', 'approved_at' => now()]);
        app(RateCardCopier::class)->copy($this->company);
        $this->owner = $this->staff(StaffRole::SuperAdmin, null, ['company_id' => $this->company->id, 'email' => 'owner@bilishatid.test']);
    }

    public function test_an_approved_company_starts_with_a_copy_of_pickrushs_rates(): void
    {
        foreach (['pr_shipping_levels', 'pr_shipping_level_fees', 'pr_shipping_route_levels'] as $table) {
            $this->assertSame(DB::table($table)->where('company_id', Company::defaultId())->count(), DB::table($table)->where('company_id', $this->company->id)->count(), $table);
        }
        $this->assertFalse(app(RateCardCopier::class)->copy($this->company), 'a company keeps the card it has');

        $items = [['weight_kg' => '1', 'quantity' => 1]];
        $calculator = app(ShippingCalculator::class);
        $pickrush = $calculator->quote($items, 'Laguna', 'Batangas');
        $theirs = $calculator->quote($items, 'Laguna', 'Batangas', companyId: $this->company->id);
        $this->assertTrue($theirs->ok());
        $this->assertSame($pickrush->deliveryFee, $theirs->deliveryFee);

        // Their price change is theirs alone.
        $level = DB::table('pr_shipping_route_levels')->where('company_id', $this->company->id)->where('origin_region', 'LUZON')->where('destination_region', 'LUZON')->value('level');
        app(RouteRateGenerator::class)->forCompany($this->company->id)->updateLevels([$level => ['fees' => ['1.00' => '999.00', '2.00' => '1000.00', '3.00' => '1001.00', '5.00' => '1002.00', '10.00' => '1003.00'], 'per_kg' => '50']], today()->toDateString());

        $this->assertSame('999.00', $calculator->quote($items, 'Laguna', 'Batangas', companyId: $this->company->id)->deliveryFee);
        $this->assertSame($pickrush->deliveryFee, $calculator->quote($items, 'Laguna', 'Batangas')->deliveryFee);
    }

    public function test_the_shipping_page_shows_the_companys_own_card_and_guards_network_settings(): void
    {
        $this->actingAs($this->owner, 'pickrush');

        $this->get($this->pickrushUrl('/hq/shipping'))->assertOk()->assertSee('What PickSell buyers pay Bilis Hatid Express Inc. to ship');
        $class = ParcelClass::first();
        $this->put($this->pickrushUrl("/hq/shipping/classes/{$class->code}"), ['min_kg_exclusive' => 0, 'max_kg_inclusive' => 3, 'rider_pay' => 99])->assertForbidden();
        $this->post($this->pickrushUrl('/hq/shipping/provinces'), ['name' => 'Atlantis', 'region_code' => 'NCR'])->assertForbidden();
        $this->get($this->pickrushUrl('/hq/settings'))->assertOk()->assertSee('apply to every logistics company');
        $this->put($this->pickrushUrl('/hq/settings'), [])->assertForbidden();
    }

    public function test_the_owner_opens_province_and_station_hubs(): void
    {
        $this->actingAs($this->owner, 'pickrush');
        $this->get($this->pickrushUrl('/hubs'))->assertOk()->assertSee('You have no hubs yet')->assertDontSee('Tanauan City Station');

        $this->post($this->pickrushUrl('/hubs'), ['type' => 'station', 'municipality_code' => '041029000'])
            ->assertSessionHasErrors('hub');   // no province hub yet
        $this->post($this->pickrushUrl('/hubs'), ['type' => 'province', 'province_code' => '041000000'])->assertRedirect();
        $this->post($this->pickrushUrl('/hubs'), ['type' => 'province', 'province_code' => '041000000'])->assertSessionHasErrors('hub');
        $this->get($this->pickrushUrl('/hubs/create'))->assertOk()
            ->assertSee('data-map-pin="province-pin"', false)->assertSee('data-center-pin="station-pin"', false)
            ->assertSee('data-place="City of Tanauan, Batangas"', false);
        $this->post($this->pickrushUrl('/hubs'), ['type' => 'station', 'municipality_code' => '041029000', 'lat' => '40', 'lng' => '121'])
            ->assertSessionHasErrors('lat');   // outside the Philippines
        $this->post($this->pickrushUrl('/hubs'), ['type' => 'station', 'municipality_code' => '041029000', 'address' => 'Poblacion',
            'lat' => '14.0868000', 'lng' => '121.1495000'])->assertRedirect();

        $station = Hub::ofCompany($this->company->id)->stations()->where('psgc_code', '041029000')->firstOrFail();
        $this->assertSame('Tanauan City Station', $station->name);
        $this->assertEqualsWithDelta(14.0868, (float) $station->lat, 0.0001);
        $this->get($this->pickrushUrl("/hubs/{$station->id}"))->assertOk()
            ->assertSee('data-map-pin="hub-pin"', false)->assertSee('.map-pin-canvas { height: 320px;', false)->assertDontSee('id="lat" name="lat" type="number"', false);
        $this->assertSame('Batangas Province Hub', $station->parent->name);
        $this->assertSame($this->company->id, $station->parent->company_id);
        $this->assertSame(1, Hub::ofCompany(Company::defaultId())->stations()->where('psgc_code', '041029000')->count(), "Pickrush's station is untouched");

        $this->get($this->pickrushUrl('/hubs'))->assertSee('Tanauan City Station')->assertSee('Batangas Province Hub')->assertDontSee('Laguna');

        // Only a super admin opens hubs.
        $manager = $this->staff(StaffRole::HubManager, $station, ['email' => 'manager@bilishatid.test']);
        $this->actingAs($manager, 'pickrush')->post($this->pickrushUrl('/hubs'), ['type' => 'province', 'province_code' => '043400000'])->assertForbidden();
    }

    public function test_parcels_carry_the_carriers_prefix_and_stations(): void
    {
        $province = app(\App\Pickrush\Services\Hq\HubCreator::class)->province($this->company->id, '041000000');
        $station = app(\App\Pickrush\Services\Hq\HubCreator::class)->station($this->company->id, '041029000');

        $parcel = Actor::actingAs(Actor::picksell(), fn () => app(ParcelRegistrar::class)->register([
            'company_id' => $this->company->id, 'source' => 'picksell', 'external_ref' => '7', 'shipper_name' => 'Shop', 'consignee_name' => 'Buyer',
            'pickup_municipality_code' => '041029000', 'delivery_municipality_code' => '041029000',
        ]));

        $this->assertStringStartsWith('BH', $parcel->tracking_no);
        $this->assertTrue(\App\Pickrush\Services\TrackingNumber::isValid($parcel->tracking_no));
        $this->assertTrue($parcel->originHub->is($station));
        $this->assertSame($this->company->id, $parcel->company_id);
        $this->get($this->pickrushUrl('/track?no='.$parcel->tracking_no))->assertOk()->assertSee('Bilis Hatid Express Inc.');
    }

    public function test_the_portal_carries_the_companys_name(): void
    {
        $this->actingAs($this->owner, 'pickrush')->get($this->pickrushUrl('/hq'))->assertOk()
            ->assertSee('<title>HQ dashboard | Bilis Hatid Express Inc.</title>', false)
            ->assertSee('Bilis Hatid Express Inc. network')
            ->assertSee('Sign out of Bilis Hatid Express Inc.?')
            ->assertDontSee('Pickrush network');
    }
}
