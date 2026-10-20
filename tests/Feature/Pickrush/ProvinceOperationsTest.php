<?php

namespace Tests\Feature\Pickrush;

use App\Pickrush\Enums\ParcelStatus;
use App\Pickrush\Enums\StaffRole;
use App\Pickrush\Models\Hub;
use App\Pickrush\Models\Manifest;
use App\Pickrush\Models\Parcel;
use App\Pickrush\Models\ParcelException;
use App\Pickrush\Models\Staff;
use App\Pickrush\Models\Vehicle;
use App\Pickrush\Services\ManifestService;
use App\Pickrush\Services\ParcelLedger;
use App\Pickrush\Services\ParcelRegistrar;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProvinceOperationsTest extends TestCase
{
    use BuildsNetwork, RefreshDatabase;

    private Hub $tanauan;

    private Hub $batangas;

    private Hub $laguna;

    private Staff $stationStaff;

    private Staff $manager;

    private Staff $sorter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildNetwork();
        $this->tanauan = $this->station('041029000');
        $this->batangas = $this->tanauan->parent;
        $this->laguna = $this->station('043405000')->parent;
        $this->stationStaff = $this->staff(StaffRole::HubManager, $this->tanauan);
        $this->manager = $this->staff(StaffRole::HubManager, $this->batangas);
        $this->sorter = $this->staff(StaffRole::Sorter, $this->batangas);
    }

    private function as(Staff $staff): static
    {
        return $this->actingAs($staff, 'pickrush');
    }

    private function parcel(string $toMunicipality = '041005000', array $overrides = []): Parcel
    {
        return app(ParcelRegistrar::class)->register(array_merge([
            'shipper_name' => 'Tote House', 'pickup_municipality_code' => '041029000', 'pickup_barangay_code' => '041029001',
            'consignee_name' => 'Ana Reyes', 'delivery_municipality_code' => $toMunicipality, 'cod_amount' => 900,
        ], $overrides));
    }

    /** Tanauan station sends parcels up to Batangas on one trip; returns the dispatched trip. */
    private function tripUp(Parcel ...$parcels): Manifest
    {
        $manifests = app(ManifestService::class);
        $this->as($this->stationStaff);
        foreach ($parcels as $parcel) {
            app(ParcelLedger::class)->record($parcel, ParcelStatus::AtOriginHub, $this->tanauan);
        }
        $trip = $manifests->create($this->tanauan, $this->batangas, $this->stationStaff);
        foreach ($parcels as $parcel) {
            $manifests->add($trip, $parcel->tracking_no);
        }
        $manifests->dispatch($trip, 'NAA 1111', 'Mang Jose', $this->stationStaff);

        return $trip;
    }

    public function test_inbound_check_reports_missing_parcels_against_the_sending_hub(): void
    {
        $arrived = $this->parcel();
        $missing = $this->parcel();
        $trip = $this->tripUp($arrived, $missing);

        $this->as($this->sorter)->get($this->pickrushUrl('/province/inbound'))->assertOk()->assertSee($trip->manifest_no);
        $this->as($this->sorter)->post($this->pickrushUrl("/province/trips/{$trip->manifest_no}/receive"), ['tracking_no' => $arrived->tracking_no])->assertSessionHasNoErrors();
        $this->as($this->sorter)->post($this->pickrushUrl("/province/trips/{$trip->manifest_no}/close"))
            ->assertSessionHas('success', 'Trip closed. 1 parcels are missing; an exception was opened for each.');

        $exception = ParcelException::withoutGlobalScopes()->sole();
        $this->assertSame(['missing', $this->batangas->id, $this->tanauan->id, $missing->id], [$exception->type, $exception->hub_id, $exception->against_hub_id, $exception->parcel_id]);
        $this->as($this->stationStaff)->get($this->pickrushUrl('/exceptions'))->assertOk()->assertSee('Reported against this hub')->assertSee($missing->tracking_no);

        // It turns up on the floor later: scanning it in resolves the exception.
        $this->as($this->sorter)->post($this->pickrushUrl('/province/inbound'), ['tracking_no' => $missing->tracking_no])->assertSessionHasNoErrors();
        $this->assertSame(['resolved', 'found'], [$exception->fresh()->status, $exception->fresh()->resolution]);
        $this->assertSame(ParcelStatus::AtProvinceHub, $missing->fresh()->current_status);
    }

    public function test_sort_scan_shows_the_bin_and_receives_parcels_straight_off_the_truck(): void
    {
        $local = $this->parcel('041005000');           // San Jose, Batangas: down to a station
        $interProvince = $this->parcel('137404000');   // Quezon City: across to Metro Manila
        $this->tripUp($local, $interProvince);

        $this->as($this->sorter)->post($this->pickrushUrl('/province/sorting'), ['tracking_no' => $local->tracking_no])
            ->assertSessionHas('sort_result', fn ($r) => $r['bin'] === 'SAN JOSE' && $r['received'] && ! $r['error']);
        $this->as($this->sorter)->post($this->pickrushUrl('/province/sorting'), ['tracking_no' => $interProvince->tracking_no])
            ->assertSessionHas('sort_result', fn ($r) => $r['bin'] === 'METRO MANILA EASTERN' && $r['destination'] === 'Metro Manila Eastern District Hub');

        $this->assertNotNull($local->fresh()->sorted_at);
        $this->as($this->sorter)->get($this->pickrushUrl('/province/sorting'))->assertOk()->assertSee('SAN JOSE')->assertSee('1 sorted of 1');
    }

    public function test_a_parcel_with_no_route_goes_to_the_exception_bin_until_fixed(): void
    {
        $parcel = $this->parcel('041005000');
        $this->tripUp($parcel);
        $sanJose = $this->station('041005000');
        $sanJose->update(['is_active' => false]);     // HQ closed the station for now

        $this->as($this->sorter)->post($this->pickrushUrl('/province/sorting'), ['tracking_no' => $parcel->tracking_no])
            ->assertSessionHas('sort_result', fn ($r) => $r['bin'] === 'EXCEPTION' && $r['error']);
        $this->assertSame(ParcelStatus::OnHold, $parcel->fresh()->current_status);
        $exception = ParcelException::withoutGlobalScopes()->where('type', 'no_route')->sole();
        $this->assertEmpty(app(ManifestService::class)->readyFor($this->batangas, $sanJose)->get(), 'a held parcel cannot be loaded');

        // The station reopens; the hub releases the parcel and it sorts normally.
        $sanJose->update(['is_active' => true]);
        $this->as($this->sorter)->post($this->pickrushUrl("/exceptions/{$exception->id}/resolve"), ['action' => 'continue', 'note' => 'San Jose station is open again'])->assertSessionHasNoErrors();
        $this->assertSame(ParcelStatus::AtProvinceHub, $parcel->fresh()->current_status);
        $this->as($this->sorter)->post($this->pickrushUrl('/province/sorting'), ['tracking_no' => $parcel->tracking_no])
            ->assertSessionHas('sort_result', fn ($r) => $r['bin'] === 'SAN JOSE');
    }

    public function test_outbound_trips_go_to_own_stations_or_other_province_hubs(): void
    {
        $local = $this->parcel('041005000');
        $interProvince = $this->parcel('137404000');
        $trip = $this->tripUp($local, $interProvince);
        $this->as($this->sorter);
        foreach ([$local, $interProvince] as $parcel) {
            app(ManifestService::class)->receive($trip, $parcel->tracking_no, $this->sorter);
        }
        Vehicle::withoutGlobalScopes()->create(['hub_id' => $this->batangas->id, 'plate_no' => 'TRK 900', 'vehicle_type' => 'truck', 'driver_name' => 'Mang Pedro']);

        $this->as($this->sorter)->post($this->pickrushUrl('/province/trips'), ['to_hub_id' => $this->station('043405000')->id])
            ->assertSessionHasErrors('operation');   // Calamba is another province's station
        $this->as($this->sorter)->post($this->pickrushUrl('/province/trips'), ['to_hub_id' => $interProvince->fresh()->next_hub_id])->assertRedirect();
        $across = Manifest::where('from_hub_id', $this->batangas->id)->sole();
        $this->as($this->sorter)->get($this->pickrushUrl("/province/trips/{$across->manifest_no}"))->assertOk()->assertSee('TRK 900');
        $this->as($this->sorter)->post($this->pickrushUrl("/province/trips/{$across->manifest_no}/parcels"), ['tracking_no' => $local->tracking_no])
            ->assertSessionHasErrors('operation');   // San Jose's parcel does not go to Metro Manila
        $this->as($this->sorter)->post($this->pickrushUrl("/province/trips/{$across->manifest_no}/parcels/all"))->assertSessionHas('success', '1 parcels added.');
        $this->as($this->sorter)->post($this->pickrushUrl("/province/trips/{$across->manifest_no}/dispatch"), ['vehicle_plate' => 'TRK 900', 'driver_name' => 'Mang Pedro'])
            ->assertSessionHasNoErrors();

        $this->assertSame([ParcelStatus::InTransitToProvince, 'Metro Manila Eastern District Hub'], [$interProvince->fresh()->current_status, $interProvince->fresh()->nextHub->name]);
        $this->as($this->sorter)->get($this->pickrushUrl('/fleet'))->assertOk()->assertSee('On a trip');
    }

    public function test_a_mis_sorted_parcel_is_received_rerouted_and_logged_against_the_sender(): void
    {
        $parcel = $this->parcel('041005000');
        $trip = $this->tripUp($parcel);                // heading to Batangas…
        $lagunaSorter = $this->staff(StaffRole::Sorter, $this->laguna);

        $this->as($lagunaSorter)->post($this->pickrushUrl('/province/inbound'), ['tracking_no' => $parcel->tracking_no])
            ->assertSessionHas('errors', fn ($bag) => str_contains($bag->first('operation'), 'report it as mis-sorted'));
        $this->as($lagunaSorter)->post($this->pickrushUrl('/province/inbound/mis-sorted'), ['tracking_no' => $parcel->tracking_no])->assertSessionHasNoErrors();

        $parcel->refresh();
        $this->assertSame([ParcelStatus::AtProvinceHub, $this->laguna->id, $this->batangas->id], [$parcel->current_status, $parcel->current_hub_id, $parcel->next_hub_id],
            '…but landed in Laguna; it is sent back toward Batangas');
        $exception = ParcelException::withoutGlobalScopes()->sole();
        $this->assertSame(['mis_sorted', $this->tanauan->id, $trip->id], [$exception->type, $exception->against_hub_id, $exception->manifest_id]);
    }

    public function test_damaged_parcels_are_held_and_only_managers_mark_parcels_lost(): void
    {
        $damaged = $this->parcel();
        $lost = $this->parcel();
        $trip = $this->tripUp($damaged, $lost);
        $this->as($this->sorter);
        app(ManifestService::class)->receive($trip, $damaged->tracking_no, $this->sorter);
        app(ManifestService::class)->close($trip->fresh(), $this->sorter);

        $this->as($this->sorter)->post($this->pickrushUrl('/exceptions'), ['type' => 'damaged', 'tracking_no' => $damaged->tracking_no, 'note' => 'Box crushed, item intact'])
            ->assertSessionHasNoErrors();
        $this->assertSame(ParcelStatus::OnHold, $damaged->fresh()->current_status);
        $report = ParcelException::withoutGlobalScopes()->where('type', 'damaged')->sole();
        $this->as($this->sorter)->post($this->pickrushUrl("/exceptions/{$report->id}/resolve"), ['action' => 'continue', 'note' => 'Repacked in a new box'])->assertSessionHasNoErrors();
        $this->assertSame(ParcelStatus::AtProvinceHub, $damaged->fresh()->current_status);

        $missing = ParcelException::withoutGlobalScopes()->where('type', 'missing')->sole();
        $this->as($this->sorter)->post($this->pickrushUrl("/exceptions/{$missing->id}/resolve"), ['action' => 'mark_lost', 'note' => 'Not on the truck'])
            ->assertSessionHas('errors', fn ($bag) => str_contains($bag->first('operation'), 'Only the hub manager'));
        $this->as($this->manager)->post($this->pickrushUrl("/exceptions/{$missing->id}/resolve"), ['action' => 'mark_lost', 'note' => 'Driver confirmed it never loaded'])->assertSessionHasNoErrors();
        $this->assertSame(ParcelStatus::Lost, $lost->fresh()->current_status);
    }

    public function test_a_parcel_with_an_unmatched_address_is_held_at_the_origin_station(): void
    {
        $parcel = $this->parcel('999999999');

        $this->as($this->stationStaff)->post($this->pickrushUrl('/station/receiving'), ['tracking_no' => $parcel->tracking_no])
            ->assertSessionHas('success', fn ($m) => str_contains($m, 'needs fixing'));
        $this->assertSame(ParcelStatus::OnHold, $parcel->fresh()->current_status);
        $this->assertSame('no_route', ParcelException::withoutGlobalScopes()->sole()->type);
    }

    public function test_inventory_shows_dwell_time_and_aging(): void
    {
        $old = $this->parcel();
        $new = $this->parcel();
        $this->travel(-30)->hours();
        $trip = $this->tripUp($old);
        $this->as($this->sorter);
        app(ManifestService::class)->receive($trip, $old->tracking_no, $this->sorter);
        $this->travelBack();
        $trip2 = $this->tripUp($new);
        $this->as($this->sorter);
        app(ManifestService::class)->receive($trip2, $new->tracking_no, $this->sorter);

        $this->as($this->sorter)->get($this->pickrushUrl('/province/inventory?aging=1'))->assertOk()->assertSee($old->tracking_no)->assertDontSee($new->tracking_no)->assertSee('30 h');
        $this->as($this->sorter)->get($this->pickrushUrl('/province'))->assertOk()->assertSee('Aging (over 24h)');
    }

    public function test_only_province_staff_use_the_province_portal_and_only_managers_edit_the_fleet(): void
    {
        $this->as($this->stationStaff)->get($this->pickrushUrl('/province'))->assertForbidden();
        $this->as($this->staff(StaffRole::Finance))->get($this->pickrushUrl('/province/sorting'))->assertForbidden();
        $this->as($this->sorter)->get($this->pickrushUrl('/'))->assertRedirect(route('pickrush.province.dashboard'));

        $this->as($this->sorter)->post($this->pickrushUrl('/fleet'), ['plate_no' => 'abc 1', 'vehicle_type' => 'van'])->assertForbidden();
        $this->as($this->manager)->post($this->pickrushUrl('/fleet'), ['plate_no' => 'abc 1', 'vehicle_type' => 'van'])->assertSessionHasNoErrors();
        $this->assertSame('ABC 1', Vehicle::withoutGlobalScopes()->sole()->plate_no);

        foreach (['/province', '/province/inbound', '/province/sorting', '/province/trips', '/province/inventory', '/exceptions', '/exceptions?status=resolved', '/fleet'] as $page) {
            $this->as($this->sorter)->get($this->pickrushUrl($page))->assertOk();
        }
    }
}
