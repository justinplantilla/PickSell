<?php

namespace Tests\Feature\Pickrush;

use App\Pickrush\Enums\ParcelStatus;
use App\Pickrush\Enums\StaffRole;
use App\Pickrush\Models\AuditLog;
use App\Pickrush\Models\CodRemittance;
use App\Pickrush\Models\Hub;
use App\Pickrush\Models\HubRemittance;
use App\Pickrush\Models\Parcel;
use App\Pickrush\Models\ParcelException;
use App\Pickrush\Models\Rider;
use App\Pickrush\Models\Staff;
use App\Pickrush\Services\Hq\PolicySettings;
use App\Pickrush\Services\ParcelLedger;
use App\Pickrush\Services\ParcelRegistrar;
use App\Pickrush\Services\RateCard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HqPortalTest extends TestCase
{
    use BuildsNetwork, RefreshDatabase;

    private Staff $admin;

    private Staff $finance;

    private Staff $support;

    private Hub $tanauan;

    private Hub $sanJose;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildNetwork();
        $this->admin = $this->staff(StaffRole::SuperAdmin);
        $this->finance = $this->staff(StaffRole::Finance);
        $this->support = $this->staff(StaffRole::Support);
        $this->tanauan = $this->station('041029000');
        $this->sanJose = $this->station('041005000');
    }

    private function as(Staff $staff): static
    {
        return $this->actingAs($staff, 'pickrush');
    }

    private function parcel(string $to = '041005000', array $overrides = []): Parcel
    {
        return app(ParcelRegistrar::class)->register(array_merge([
            'shipper_name' => 'Tote House', 'pickup_municipality_code' => '041029000', 'pickup_barangay_code' => '041029001',
            'consignee_name' => 'Ana Reyes', 'consignee_mobile' => '09171234567', 'delivery_municipality_code' => $to, 'cod_amount' => 1000,
        ], $overrides));
    }

    /** Fast-forward a parcel to a final scan at a station (the station flows are tested elsewhere). */
    private function finish(Parcel $parcel, Hub $station, ParcelStatus $final): void
    {
        $ledger = app(ParcelLedger::class);
        $ledger->record($parcel, ParcelStatus::AtOriginHub, $parcel->originHub);
        $ledger->record($parcel, ParcelStatus::InTransitToProvince, $parcel->originHub, ['to_hub' => $parcel->originHub->parent]);
        $ledger->record($parcel, ParcelStatus::AtProvinceHub, $parcel->originHub->parent);
        $ledger->record($parcel, ParcelStatus::InTransitToStation, $parcel->originHub->parent, ['to_hub' => $station]);
        $ledger->record($parcel, ParcelStatus::ArrivedAtStation, $station);
        if ($final === ParcelStatus::ReturnedToSender) {
            $ledger->record($parcel, ParcelStatus::ReturnedToSender, $station);

            return;
        }
        $ledger->record($parcel, ParcelStatus::AssignedToRider, $station);
        $ledger->record($parcel, ParcelStatus::OutForDelivery, $station);
        $ledger->record($parcel, $final, $station);
    }

    public function test_dashboard_and_reports_rank_stations_by_success_rate(): void
    {
        $this->finish($this->parcel(), $this->sanJose, ParcelStatus::Delivered);
        $this->finish($this->parcel(), $this->sanJose, ParcelStatus::Delivered);
        $this->finish($this->parcel(), $this->sanJose, ParcelStatus::ReturnedToSender);
        $this->finish($this->parcel('041029000'), $this->tanauan, ParcelStatus::Delivered);

        $this->as($this->admin)->get($this->pickrushUrl('/hq'))->assertOk()
            ->assertSee('75%')                               // 3 delivered of 4 finished
            ->assertSeeInOrder(['Tanauan City Station', '100%', 'San Jose Station', '66.7%']);

        $csv = $this->as($this->finance)->get($this->pickrushUrl('/hq/reports/export?period=week'))->assertOk()->streamedContent();
        $this->assertStringContainsString('"San Jose Station",Batangas,2,1,0,66.7', $csv);
        $this->assertSame(1, AuditLog::where('action', 'reports.exported')->count());
    }

    public function test_staff_roles_must_match_the_hub_type(): void
    {
        $form = fn (array $o) => array_merge(['full_name' => 'Ramon Cruz', 'email' => 'ramon@pickrush.test', 'password' => 'long-enough-1', 'is_active' => '1'], $o);

        $this->as($this->admin)->post($this->pickrushUrl('/hq/staff'), $form(['role' => 'sorter', 'hub_code' => $this->tanauan->code]))
            ->assertSessionHas('errors', fn ($b) => str_contains($b->first('operation'), 'cannot work at a station hub'));
        $this->as($this->admin)->post($this->pickrushUrl('/hq/staff'), $form(['role' => 'dispatcher', 'hub_code' => $this->tanauan->parent->code]))
            ->assertSessionHas('errors', fn ($b) => str_contains($b->first('operation'), 'cannot work at a province hub'));
        $this->as($this->admin)->post($this->pickrushUrl('/hq/staff'), $form(['role' => 'hub_manager', 'hub_code' => strtolower($this->tanauan->code)]))
            ->assertRedirect(route('pickrush.hq.staff.index'));
        $manager = Staff::where('email', 'ramon@pickrush.test')->sole();
        $this->assertSame([$this->tanauan->id, StaffRole::HubManager], [$manager->hub_id, $manager->role]);
        $this->assertNotContains('No active hub manager', $this->tanauan->fresh()->readinessGaps());

        // The new account can sign in.
        auth('pickrush')->logout();
        $this->post($this->pickrushUrl('/login'), ['email' => 'ramon@pickrush.test', 'password' => 'long-enough-1'])->assertRedirect(route('pickrush.dashboard'));

        $this->as($this->admin)->put($this->pickrushUrl("/hq/staff/{$this->admin->id}"), ['full_name' => 'Admin', 'email' => $this->admin->email, 'role' => 'super_admin'])
            ->assertSessionHas('errors', fn ($b) => str_contains($b->first('operation'), 'cannot change your own role or deactivate yourself'));
        $this->as($this->finance)->get($this->pickrushUrl('/hq/staff'))->assertForbidden();
    }

    public function test_hq_fixes_an_unmatched_address_and_the_parcel_moves_on(): void
    {
        $parcel = $this->parcel('999999999');
        $stationStaff = $this->staff(StaffRole::Dispatcher, $this->tanauan);
        $this->as($stationStaff)->post($this->pickrushUrl('/station/receiving'), ['tracking_no' => $parcel->tracking_no]);
        $this->assertSame(ParcelStatus::OnHold, $parcel->fresh()->current_status);

        $this->as($this->support)->get($this->pickrushUrl("/hq/address-fix?parcel={$parcel->tracking_no}&province=041000000&municipality=041029000"))
            ->assertOk()->assertSee("Fix {$parcel->tracking_no}")->assertSee('Altura Bata')->assertSee('Save address and reroute');
        $this->as($this->support)->put($this->pickrushUrl("/hq/address-fix/{$parcel->tracking_no}"), [
            'side' => 'delivery', 'barangay_code' => '041029001', 'address_line' => '12 Rizal St, Altura Bata, Tanauan',
        ])->assertSessionHasNoErrors();

        $parcel->refresh();
        $this->assertFalse($parcel->needs_address_fix);
        $this->assertSame([$this->tanauan->id, ParcelStatus::AtOriginHub], [$parcel->dest_hub_id, $parcel->current_status], 'released from hold');
        $this->assertSame('resolved', ParcelException::withoutGlobalScopes()->sole()->status);
        $this->assertSame(RateCard::quote($this->tanauan, $this->tanauan, null, 1000), (float) $parcel->shipping_fee, 'fee re-quoted for the real route');
        $this->as($this->finance)->get($this->pickrushUrl('/hq/address-fix'))->assertForbidden();
    }

    public function test_rates_and_policies_are_edited_by_the_super_admin_and_apply_everywhere(): void
    {
        $this->as($this->finance)->get($this->pickrushUrl('/hq/settings'))->assertOk()->assertSee('Only a super admin can change them.');
        $values = collect(PolicySettings::FIELDS)->mapWithKeys(fn ($f, $key) => [str_replace('.', '__', $key) => PolicySettings::value($key)])->all();
        $this->as($this->finance)->put($this->pickrushUrl('/hq/settings'), $values)->assertForbidden();

        $values['policies__max_delivery_attempts'] = 2;
        $values['rates__inter_province__base'] = 150;
        $this->as($this->admin)->put($this->pickrushUrl('/hq/settings'), $values)->assertSessionHas('success', '2 setting(s) changed.');

        config(['pickrush.policies.max_delivery_attempts' => 3, 'pickrush.rates.inter_province.base' => 120]);   // a fresh request…
        PolicySettings::load();                                                                                   // …loads the saved values
        $this->assertSame([2, 150], [config('pickrush.policies.max_delivery_attempts'), config('pickrush.rates.inter_province.base')]);
        $this->assertSame(150.0 + 15.0, RateCard::quote($this->tanauan, $this->station('137404000'), 0.5, 1000));
        $this->assertSame(2, AuditLog::where('action', 'settings.changed')->count());
    }

    public function test_rate_card_by_route_and_weight(): void
    {
        $qc = $this->station('137404000');
        $this->assertSame('same_city', RateCard::serviceType($this->tanauan, $this->tanauan));
        $this->assertSame('same_province', RateCard::serviceType($this->tanauan, $this->sanJose));
        $this->assertSame('inter_province', RateCard::serviceType($this->tanauan, $qc));
        // Same province: ₱85 base incl. 1 kg, +₱20 for each started kg above (2.3 kg → 2 extra), +1.5% of ₱1,000 COD.
        $this->assertSame(85.0 + 40.0 + 15.0, RateCard::quote($this->tanauan, $this->sanJose, 2.3, 1000));
    }

    public function test_finance_confirms_or_disputes_station_remittances(): void
    {
        $manager = $this->staff(StaffRole::HubManager, $this->sanJose);
        $remittance = HubRemittance::withoutGlobalScopes()->create(['station_hub_id' => $this->sanJose->id, 'expected_amount' => 5000, 'remitted_amount' => 4800,
            'variance' => -200, 'reference_no' => 'BDO-7788', 'submitted_by' => $manager->id]);

        $this->as($this->support)->post($this->pickrushUrl("/hq/finance/remittances/{$remittance->id}"), ['decision' => 'confirm'])->assertForbidden();
        $this->as($this->finance)->get($this->pickrushUrl('/hq/finance'))->assertOk()->assertSee('BDO-7788')->assertSee('₱-200.00');
        $this->as($this->finance)->post($this->pickrushUrl("/hq/finance/remittances/{$remittance->id}"), ['decision' => 'dispute'])
            ->assertSessionHas('errors', fn ($b) => str_contains($b->first('operation'), 'Say what does not match'));
        $this->as($this->finance)->post($this->pickrushUrl("/hq/finance/remittances/{$remittance->id}"), ['decision' => 'dispute', 'note' => 'Bank shows ₱4,800 only'])
            ->assertSessionHasNoErrors();
        $this->assertSame(['disputed', $this->finance->id], [$remittance->fresh()->status, $remittance->fresh()->confirmed_by]);
    }

    public function test_alerts_list_cash_problems_stuck_parcels_and_expiring_documents(): void
    {
        $manager = $this->staff(StaffRole::HubManager, $this->sanJose);
        $rider = Rider::withoutGlobalScopes()->create(['station_hub_id' => $this->sanJose->id, 'full_name' => 'Jun Rider', 'mobile_no' => '09180000001',
            'status' => 'active', 'cod_limit' => 5000, 'max_parcels' => 20, 'created_by' => $manager->id]);
        CodRemittance::withoutGlobalScopes()->create(['rider_id' => $rider->id, 'station_hub_id' => $this->sanJose->id, 'remit_date' => today(),
            'expected_amount' => 1000, 'received_amount' => 900, 'variance' => -100, 'status' => 'disputed', 'received_by' => $manager->id]);
        $rider->documents()->create(['doc_type' => 'drivers_license', 'file_path' => 'x.pdf', 'expiry_date' => today()->addDays(10), 'uploaded_by' => $manager->id]);
        $stuck = $this->parcel();
        Parcel::withoutGlobalScopes()->whereKey($stuck->id)->update(['updated_at' => now()->subDays(3)]);

        $this->as($this->support)->get($this->pickrushUrl('/hq/alerts'))->assertOk()
            ->assertSeeInOrder(['Cash shortages and overages', 'Jun Rider', 'Stuck parcels', $stuck->tracking_no, 'Rider documents expiring', "Driver's license"], false);
    }

    public function test_hubs_can_be_edited_and_switched_off_by_the_super_admin(): void
    {
        $this->as($this->support)->put($this->pickrushUrl("/hubs/{$this->sanJose->id}"), ['address' => 'x', 'is_active' => '1'])->assertForbidden();
        $this->as($this->admin)->put($this->pickrushUrl("/hubs/{$this->sanJose->id}"), ['address' => 'Poblacion, San Jose', 'lat' => 13.88, 'lng' => 121.1])
            ->assertSessionHasNoErrors();
        $this->assertSame(['Poblacion, San Jose', false], [$this->sanJose->fresh()->address, $this->sanJose->fresh()->is_active]);

        $this->as($this->admin)->put($this->pickrushUrl("/hubs/{$this->tanauan->parent_hub_id}"), [])
            ->assertSessionHas('errors', fn ($b) => str_contains($b->first('operation'), 'Deactivate its stations first'));
    }

    public function test_audit_log_viewer_and_own_password(): void
    {
        $this->as($this->admin)->put($this->pickrushUrl("/hubs/{$this->sanJose->id}"), ['address' => 'New address', 'is_active' => '1']);

        $this->as($this->finance)->get($this->pickrushUrl('/hq/audit?subject=Hub&action=updated'))->assertOk()->assertSee('Hub.updated')->assertSee('New address');
        $this->as($this->support)->get($this->pickrushUrl('/hq/audit'))->assertForbidden();

        $this->as($this->support)->put($this->pickrushUrl('/account/password'), ['current_password' => 'wrong', 'password' => 'brand-new-pass', 'password_confirmation' => 'brand-new-pass'])
            ->assertSessionHasErrors('current_password');
        $this->as($this->support)->put($this->pickrushUrl('/account/password'), ['current_password' => 'secret-password', 'password' => 'brand-new-pass', 'password_confirmation' => 'brand-new-pass'])
            ->assertSessionHasNoErrors();
        $this->assertTrue(\Hash::check('brand-new-pass', $this->support->fresh()->password));
    }

    public function test_every_hq_page_renders_for_each_hq_role(): void
    {
        $this->finish($this->parcel(), $this->sanJose, ParcelStatus::Delivered);
        $pages = ['/hq', '/hq?period=today', '/hq?period=month', '/hq/alerts', '/hq/reports', '/hq/finance', '/hq/settings', '/hubs', "/hubs/{$this->sanJose->id}", '/parcels', '/exceptions', '/account'];

        foreach ([$this->admin, $this->finance, $this->support] as $staff) {
            foreach ($pages as $page) {
                $this->as($staff)->get($this->pickrushUrl($page))->assertOk();
            }
        }
        foreach (['/hq/staff', '/hq/staff/create', "/hq/staff/{$this->finance->id}/edit", '/hq/address-fix', '/hq/audit'] as $page) {
            $this->as($this->admin)->get($this->pickrushUrl($page))->assertOk();
        }
        $this->as($this->admin)->get($this->pickrushUrl('/station'))->assertForbidden();
    }
}
