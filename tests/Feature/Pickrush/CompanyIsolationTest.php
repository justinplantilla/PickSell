<?php

namespace Tests\Feature\Pickrush;

use App\Pickrush\Enums\HubType;
use App\Pickrush\Enums\StaffRole;
use App\Pickrush\Models\AuditLog;
use App\Pickrush\Models\CodRemittance;
use App\Pickrush\Models\Company;
use App\Pickrush\Models\Hub;
use App\Pickrush\Models\Parcel;
use App\Pickrush\Models\Rider;
use App\Pickrush\Services\HubRouter;
use App\Pickrush\Services\ParcelRegistrar;
use App\Pickrush\Support\Actor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanyIsolationTest extends TestCase
{
    use BuildsNetwork, RefreshDatabase;

    private Company $other;

    private Hub $otherStation;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildNetwork();

        $this->other = Company::create(['name' => 'Bilis Hatid Express', 'slug' => 'bilis-hatid', 'tracking_prefix' => 'BH', 'status' => 'approved']);
        $province = Hub::create(['company_id' => $this->other->id, 'code' => 'P-041000000', 'name' => 'Bilis Batangas Hub', 'type' => HubType::Province,
            'psgc_code' => '041000000', 'region_code' => '040000000', 'province_name' => 'Batangas']);
        // Same town and code as Pickrush's Tanauan station: unique per company only.
        $this->otherStation = Hub::create(['code' => 'S-041029000', 'name' => 'Bilis Tanauan Station', 'type' => HubType::Station, 'parent_hub_id' => $province->id,
            'psgc_code' => '041029000', 'region_code' => '040000000', 'province_name' => 'Batangas', 'municipality_name' => 'Tanauan']);
    }

    public function test_existing_records_belong_to_pickrush(): void
    {
        $pickrush = Company::findOrFail(Company::defaultId());

        $this->assertSame('PR', $pickrush->tracking_prefix);
        $this->assertTrue($pickrush->is_default);
        $this->assertSame($this->other->id, $this->otherStation->company_id, 'a station takes its province hub\'s company');
        $this->assertSame($pickrush->id, $this->station('041029000')->company_id);
        $this->assertSame($pickrush->id, $this->staff(StaffRole::SuperAdmin)->company_id);
    }

    public function test_hq_staff_see_only_their_own_company(): void
    {
        $pickrushStation = $this->station('041029000');
        $pickrushManager = $this->staff(StaffRole::HubManager, $pickrushStation);
        $admin = $this->staff(StaffRole::SuperAdmin, null, ['company_id' => $this->other->id, 'email' => 'owner@bilishatid.test']);

        $this->actingAs($admin, 'pickrush');

        $this->get($this->pickrushUrl('/hubs'))->assertOk()->assertSee('Bilis Tanauan Station')->assertDontSee($pickrushStation->name);
        $this->get($this->pickrushUrl("/hubs/{$pickrushStation->id}"))->assertNotFound();
        $this->get($this->pickrushUrl('/hq/staff'))->assertOk()->assertDontSee($pickrushManager->email);
        $this->get($this->pickrushUrl("/hq/staff/{$pickrushManager->id}/edit"))->assertNotFound();

        // Anything this admin creates is theirs, and the audit log follows the company.
        $hire = $this->staff(StaffRole::Support, null, ['email' => 'support@bilishatid.test']);
        $this->assertSame($this->other->id, $hire->company_id);
        $this->assertTrue(AuditLog::where('subject_type', 'Staff')->where('subject_id', $hire->id)->exists());
        $this->assertFalse(AuditLog::where('subject_type', 'Staff')->where('subject_id', $pickrushManager->id)->exists());
    }

    public function test_hq_dashboard_and_alerts_leave_out_other_companies(): void
    {
        $stuck = Actor::actingAs(Actor::picksell(), fn () => app(ParcelRegistrar::class)->register([
            'source' => 'picksell', 'external_ref' => '9', 'shipper_name' => 'Shop', 'consignee_name' => 'Buyer',
            'pickup_municipality_code' => '041029000', 'delivery_municipality_code' => '043405000',
        ]));
        Parcel::withoutGlobalScopes()->whereKey($stuck->id)->update(['updated_at' => now()->subDays(5), 'needs_address_fix' => true]);
        // A Pickrush rider with an expiring document and a cash variance: alerts Pickrush HQ only.
        $manager = $this->staff(StaffRole::HubManager, $this->station('041029000'));
        $rider = Rider::withoutGlobalScopes()->create(['station_hub_id' => $this->station('041029000')->id, 'full_name' => 'Jun Rider', 'mobile_no' => '09180000001',
            'status' => 'active', 'cod_limit' => 5000, 'max_parcels' => 20, 'created_by' => $manager->id]);
        $rider->documents()->create(['doc_type' => 'drivers_license', 'file_path' => 'x.pdf', 'expiry_date' => today()->addDays(10), 'uploaded_by' => $manager->id]);
        CodRemittance::withoutGlobalScopes()->create(['rider_id' => $rider->id, 'station_hub_id' => $this->station('041029000')->id, 'remit_date' => today(),
            'expected_amount' => 1000, 'received_amount' => 900, 'variance' => -100, 'status' => 'disputed', 'received_by' => $manager->id]);
        $admin = $this->staff(StaffRole::SuperAdmin, null, ['company_id' => $this->other->id, 'email' => 'owner2@bilishatid.test']);

        $this->actingAs($admin, 'pickrush');
        $this->get($this->pickrushUrl('/hq'))->assertOk()->assertDontSee($stuck->tracking_no);
        $this->get($this->pickrushUrl('/hq/alerts'))->assertOk()->assertDontSee($stuck->tracking_no)->assertDontSee('Jun Rider');
        $this->get($this->pickrushUrl('/hq/finance'))->assertOk();

        // Pickrush's own HQ still sees it.
        $this->app['auth']->forgetGuards();
        $this->actingAs($this->staff(StaffRole::SuperAdmin), 'pickrush')->get($this->pickrushUrl('/hq/alerts'))->assertSee($stuck->tracking_no)->assertSee('Jun Rider');
    }

    public function test_routing_stays_inside_the_parcels_company(): void
    {
        $router = app(HubRouter::class);

        $this->assertTrue($router->stationFor('041029000')->is($this->station('041029000')), 'no company given: Pickrush');
        $this->assertTrue($router->stationFor('041029000', $this->other->id)->is($this->otherStation));
        $this->assertNull($router->stationFor('043405000', $this->other->id), 'the other company has no Calamba station');

        $parcel = Actor::actingAs(Actor::picksell(), fn () => app(ParcelRegistrar::class)->register([
            'source' => 'picksell', 'external_ref' => '1', 'shipper_name' => 'Shop', 'consignee_name' => 'Buyer',
            'pickup_municipality_code' => '041029000', 'delivery_municipality_code' => '043405000',
        ]));

        $this->assertSame(Company::defaultId(), $parcel->company_id);
        $this->assertTrue($parcel->originHub->is($this->station('041029000')));
        $this->assertSame(0, Parcel::ofCompany($this->other->id)->count());
    }
}
