<?php

namespace Tests\Feature\Pickrush;

use App\Pickrush\Enums\ParcelStatus;
use App\Pickrush\Enums\StaffRole;
use App\Pickrush\Models\Driver;
use App\Pickrush\Models\Hub;
use App\Pickrush\Models\Manifest;
use App\Pickrush\Models\Staff;
use App\Pickrush\Models\Vehicle;
use App\Pickrush\Services\ManifestService;
use App\Pickrush\Services\ParcelLedger;
use App\Pickrush\Services\ParcelRegistrar;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FleetTest extends TestCase
{
    use BuildsNetwork, RefreshDatabase;

    private Hub $tanauan;

    private Hub $batangas;

    private Staff $stationManager;

    private Staff $provinceManager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildNetwork();
        $this->tanauan = $this->station('041029000');
        $this->batangas = $this->tanauan->parent;
        $this->stationManager = $this->staff(StaffRole::HubManager, $this->tanauan);
        $this->provinceManager = $this->staff(StaffRole::HubManager, $this->batangas);
    }

    private function as(Staff $staff): static
    {
        return $this->actingAs($staff, 'pickrush');
    }

    /** A real 1×1 PNG (no GD needed). */
    private static function photo(string $name): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII='));
    }

    private function driverForm(array $overrides = []): array
    {
        static $n = 0;
        $n++;

        return array_merge([
            'first_name' => 'Mang',
            'last_name' => "Driver{$n}",
            'birthdate' => '1988-06-15',
            'mobile' => sprintf('917%07d', $n),
            'license_no' => sprintf('n01-22-%06d', $n),
            'license_codes' => ['B', 'C'],
            'license_expiry' => today()->addYear()->toDateString(),
            'license_front' => self::photo('front.png'),
            'license_back' => self::photo('back.png'),
        ], $overrides);
    }

    /** A draft trip from Tanauan up to Batangas with one parcel on it. */
    private function draftTrip(): Manifest
    {
        $this->as($this->stationManager);
        $parcel = app(ParcelRegistrar::class)->register(['shipper_name' => 'Tote House', 'pickup_municipality_code' => '041029000',
            'consignee_name' => 'Ana Reyes', 'delivery_municipality_code' => '137404000', 'cod_amount' => 0]);
        app(ParcelLedger::class)->record($parcel, ParcelStatus::AtOriginHub, $this->tanauan);
        $trip = app(ManifestService::class)->create($this->tanauan, $this->batangas, $this->stationManager);
        app(ManifestService::class)->add($trip, $parcel->tracking_no);

        return $trip;
    }

    public function test_a_station_keeps_one_active_truck_and_a_province_hub_three(): void
    {
        $this->as($this->stationManager)->post($this->pickrushUrl('/fleet'), ['plate_no' => 'nab 1234', 'vehicle_type' => 'truck'] + $this->truckPapers())->assertSessionHasNoErrors();
        $this->as($this->stationManager)->post($this->pickrushUrl('/fleet'), ['plate_no' => 'nab 5678', 'vehicle_type' => 'truck'] + $this->truckPapers())
            ->assertSessionHas('errors', fn ($bag) => str_contains($bag->first('operation'), 'at most 1 active truck'));

        $first = Vehicle::withoutGlobalScopes()->sole();
        $this->assertSame(['NAB 1234', 'Pickrush Logistics'], [$first->plate_no, $first->registered_owner]);

        // Retire the first truck, then its replacement fits; the old one cannot come back while the new one is active.
        $this->as($this->stationManager)->put($this->pickrushUrl("/fleet/{$first->id}"), ['plate_no' => 'NAB 1234', 'vehicle_type' => 'truck'])->assertSessionHasNoErrors();
        $this->as($this->stationManager)->post($this->pickrushUrl('/fleet'), ['plate_no' => 'nab 5678', 'vehicle_type' => 'truck'] + $this->truckPapers())->assertSessionHasNoErrors();
        $this->as($this->stationManager)->put($this->pickrushUrl("/fleet/{$first->id}"), ['plate_no' => 'NAB 1234', 'vehicle_type' => 'truck', 'is_active' => 1])
            ->assertSessionHas('errors');
        $this->assertFalse($first->fresh()->is_active);

        foreach (['PRV 1', 'PRV 2', 'PRV 3'] as $plate) {
            $this->as($this->provinceManager)->post($this->pickrushUrl('/fleet'), ['plate_no' => $plate, 'vehicle_type' => 'truck'] + $this->truckPapers())->assertSessionHasNoErrors();
        }
        $this->as($this->provinceManager)->post($this->pickrushUrl('/fleet'), ['plate_no' => 'PRV 4', 'vehicle_type' => 'truck'] + $this->truckPapers())
            ->assertSessionHas('errors', fn ($bag) => str_contains($bag->first('operation'), 'at most 3 active trucks'));
    }

    public function test_the_manager_registers_a_driver_with_license_photos_only_their_hub_sees(): void
    {
        Storage::fake('local');

        $this->as($this->stationManager)->post($this->pickrushUrl('/fleet/drivers'), $this->driverForm(['license_front' => null]))
            ->assertSessionHasErrors('license_front');
        $this->as($this->staff(StaffRole::Dispatcher, $this->tanauan))->post($this->pickrushUrl('/fleet/drivers'), $this->driverForm())->assertForbidden();

        $this->as($this->stationManager)->post($this->pickrushUrl('/fleet/drivers'), $this->driverForm(['first_name' => 'Ramon', 'last_name' => 'Dizon']))->assertSessionHasNoErrors();
        $driver = Driver::withoutGlobalScopes()->sole();
        $this->assertSame([$this->tanauan->id, 'B,C', 'Ramon Dizon'], [$driver->hub_id, $driver->license_codes, $driver->full_name]);
        $this->assertMatchesRegularExpression('/^09\d{9}$/', $driver->mobile_no, 'stored like a rider\'s number');
        $this->assertMatchesRegularExpression('/^N01-22-\d{6}$/', $driver->license_no, 'uppercased');

        // Same formats as the rider application.
        $this->as($this->stationManager)->post($this->pickrushUrl('/fleet/drivers'), $this->driverForm([
            'first_name' => 'R', 'birthdate' => today()->subYears(18)->addDay()->toDateString(), 'mobile' => '0917123456', 'license_no' => 'N0122123456', 'license_expiry' => today()->toDateString(),
        ]))->assertSessionHasErrors(['first_name', 'birthdate' => 'The driver must be at least 18 years old.', 'mobile', 'license_no', 'license_expiry']);
        $this->as($this->stationManager)->post($this->pickrushUrl('/fleet/drivers'), $this->driverForm(['mobile' => substr($driver->mobile_no, 1)]))
            ->assertSessionHasErrors(['mobile' => 'This mobile number is already registered.']);
        Storage::disk('local')->assertExists([$driver->license_front_path, $driver->license_back_path]);

        $this->as($this->stationManager)->get($this->pickrushUrl("/fleet/drivers/{$driver->id}/license/front"))->assertOk();
        $this->as($this->provinceManager)->get($this->pickrushUrl("/fleet/drivers/{$driver->id}/license/front"))->assertNotFound();

        // The truck's default driver must be one of the hub's own drivers.
        $this->as($this->provinceManager)->post($this->pickrushUrl('/fleet'), ['plate_no' => 'PRV 1', 'vehicle_type' => 'truck', 'default_driver_id' => $driver->id] + $this->truckPapers())
            ->assertSessionHasErrors('default_driver_id');
        $this->as($this->stationManager)->get($this->pickrushUrl('/fleet'))->assertOk()->assertSee('Ramon Dizon')->assertSee('Valid');
    }

    public function test_a_trip_leaves_only_with_an_available_truck_and_a_licensed_driver_of_its_own_hub(): void
    {
        [$truck, $driver] = $this->fleet($this->tanauan);
        [$otherTruck, $otherDriver] = $this->fleet($this->batangas);
        $trip = $this->draftTrip();
        $dispatch = fn (array $data) => $this->as($this->stationManager)->post($this->pickrushUrl("/station/trips/{$trip->manifest_no}/dispatch"), $data);

        $this->as($this->stationManager)->get($this->pickrushUrl("/station/trips/{$trip->manifest_no}"))->assertOk()
            ->assertSee($truck->plate_no)->assertSee($driver->full_name)->assertDontSee($otherTruck->plate_no);

        $dispatch(['vehicle_id' => $otherTruck->id, 'driver_id' => $driver->id])
            ->assertSessionHas('errors', fn ($bag) => str_contains($bag->first('operation'), 'registered trucks and drivers'));

        $driver->update(['license_expiry' => today()->subDay()]);
        $dispatch(['vehicle_id' => $truck->id, 'driver_id' => $driver->id])
            ->assertSessionHas('errors', fn ($bag) => str_contains($bag->first('operation'), 'license expired'));
        $this->assertSame('draft', $trip->fresh()->status);

        $driver->update(['license_expiry' => today()->addYear()]);
        $dispatch(['vehicle_id' => $truck->id, 'driver_id' => $driver->id])->assertSessionHasNoErrors();
        $this->assertSame([$truck->id, $driver->id], [$trip->fresh()->vehicle_id, $trip->fresh()->driver_id]);

        // Both are out until the province hub receives the trip.
        $second = $this->draftTrip();
        $this->as($this->stationManager)->post($this->pickrushUrl("/station/trips/{$second->manifest_no}/dispatch"), ['vehicle_id' => $truck->id, 'driver_id' => $driver->id])
            ->assertSessionHas('errors', fn ($bag) => str_contains($bag->first('operation'), 'still out on a trip'));
        $this->as($this->stationManager)->get($this->pickrushUrl('/fleet'))->assertOk()->assertSee('On a trip');
    }

    // Truck papers and renewals --------------------------------------------------------------

    public function test_a_truck_is_added_with_its_or_cr_and_a_renewal_keeps_the_old_papers(): void
    {
        Storage::fake('local');
        $add = fn (array $papers) => $this->as($this->stationManager)->post($this->pickrushUrl('/fleet'), ['plate_no' => 'nab 1234', 'vehicle_type' => 'truck'] + $papers);

        $add($this->truckPapers(['or_photo' => null, 'cr_no' => '12', 'mv_file_no' => '0401-12', 'registration_expiry' => today()->toDateString()]))
            ->assertSessionHasErrors(['or_photo', 'cr_no' => 'The CR no. is 7 to 12 digits.', 'mv_file_no', 'registration_expiry' => 'The registration is expired. Renew it with LTO first.']);
        $add($this->truckPapers(['registration_expiry' => today()->addYears(3)->addDay()->toDateString()]))->assertSessionHasErrors(['registration_expiry' => 'A registration runs 3 years at most.']);
        $add($this->truckPapers(['mv_file_no' => '040112345678901', 'chassis_no' => 'jalfrr90l 1234567']))->assertSessionHasNoErrors();

        $truck = Vehicle::withoutGlobalScopes()->sole();
        $this->assertSame(['0401-12345678901', 'JALFRR90L1234567'], [$truck->mv_file_no, $truck->chassis_no], 'normalized like the CR prints them');
        $this->assertTrue($truck->canTravel());
        Storage::disk('local')->assertExists([$truck->or_photo_path, $truck->cr_photo_path]);
        $this->as($this->stationManager)->get($this->pickrushUrl("/fleet/{$truck->id}/papers/or"))->assertOk();
        $this->as($this->provinceManager)->get($this->pickrushUrl("/fleet/{$truck->id}/papers/cr"))->assertNotFound();

        // Another truck cannot carry the same CR.
        $this->as($this->provinceManager)->post($this->pickrushUrl('/fleet'), ['plate_no' => 'PRV 1', 'vehicle_type' => 'truck'] + $this->truckPapers(['cr_no' => $truck->cr_no]))
            ->assertSessionHasErrors(['cr_no' => 'This CR no. is already registered to another truck.']);

        // Due within 30 days: flagged, still able to travel.
        $truck->update(['registration_expiry' => today()->addDays(12)]);
        $this->as($this->stationManager)->get($this->pickrushUrl('/fleet'))->assertOk()->assertSee('Papers to renew')->assertSee('Expires in 12 days');
        $this->as($this->stationManager)->get($this->pickrushUrl('/station'))->assertOk()->assertSee('Truck and driver papers to renew');
        $this->assertTrue($truck->fresh()->canTravel());

        // The renewal: a new OR and expiry; the photos on file stay unless new ones are uploaded.
        $oldOr = $truck->or_no;
        $oldPhoto = $truck->or_photo_path;
        $papers = array_merge($this->truckPapers(), ['cr_no' => $truck->cr_no, 'mv_file_no' => $truck->mv_file_no, 'engine_no' => $truck->engine_no, 'chassis_no' => $truck->chassis_no,
            'or_no' => '987654321', 'registration_expiry' => today()->addYear()->toDateString(), 'cr_photo' => null]);
        $this->as($this->staff(StaffRole::Dispatcher, $this->tanauan))->put($this->pickrushUrl("/fleet/{$truck->id}/papers"), $papers)->assertForbidden();
        $this->as($this->stationManager)->put($this->pickrushUrl("/fleet/{$truck->id}/papers"), $papers)->assertSessionHasNoErrors();

        $truck->refresh();
        $this->assertSame(['987654321', today()->addYear()->toDateString()], [$truck->or_no, $truck->registration_expiry->toDateString()]);
        $this->assertNotSame($oldPhoto, $truck->or_photo_path);
        $renewal = $truck->renewals()->sole();
        $this->assertSame(['or_cr', $oldOr, $oldPhoto, $this->stationManager->id], [$renewal->document, $renewal->previous['or_no'], $renewal->previous['or_photo_path'], $renewal->renewed_by]);
        Storage::disk('local')->assertExists($oldPhoto);   // the old OR is kept
        $this->as($this->stationManager)->get($this->pickrushUrl('/fleet'))->assertOk()->assertSee('History (1)')->assertSee("replaced OR {$oldOr}")->assertDontSee('Papers to renew');
    }

    public function test_a_driver_license_is_renewed_and_the_old_one_kept_on_record(): void
    {
        Storage::fake('local');
        [, $driver] = $this->fleet($this->tanauan);
        $driver->update(['license_expiry' => today()->addDays(5)]);
        $this->as($this->stationManager)->get($this->pickrushUrl('/fleet'))->assertOk()->assertSee('Expires in 5 days');
        $renew = fn (array $data) => $this->as($this->stationManager)->put($this->pickrushUrl("/fleet/drivers/{$driver->id}/license"), $data);

        // No photos on file yet (a seeded driver): the renewal needs them.
        $renew(['license_no' => $driver->license_no, 'license_codes' => ['B', 'C'], 'license_expiry' => today()->addYears(5)->toDateString()])
            ->assertSessionHasErrors(['license_front', 'license_back']);
        $renew(['license_no' => $driver->license_no, 'license_codes' => ['B', 'C'], 'license_expiry' => today()->addYears(5)->toDateString(),
            'license_front' => self::photo('f.png'), 'license_back' => self::photo('b.png')])->assertSessionHasNoErrors();

        $driver->refresh();
        $this->assertSame([today()->addYears(5)->toDateString(), 'B,C'], [$driver->license_expiry->toDateString(), $driver->license_codes]);
        $this->assertSame(today()->addDays(5)->toDateString(), substr($driver->renewals()->sole()->previous['license_expiry'], 0, 10));

        // Editing the driver's details leaves the license alone.
        $this->as($this->stationManager)->put($this->pickrushUrl("/fleet/drivers/{$driver->id}"), ['first_name' => 'Ramon', 'last_name' => 'Dizon', 'birthdate' => '1985-03-01',
            'mobile' => substr($driver->mobile_no, 1), 'is_active' => 1])->assertSessionHasNoErrors();
        $this->assertSame(['Ramon Dizon', today()->addYears(5)->toDateString()], [$driver->fresh()->full_name, $driver->fresh()->license_expiry->toDateString()]);
        $this->assertSame(1, $driver->renewals()->count());
    }

    public function test_a_truck_without_papers_or_with_an_expired_or_cannot_leave(): void
    {
        [$truck, $driver] = $this->fleet($this->tanauan);
        $trip = $this->draftTrip();
        $dispatch = fn () => $this->as($this->stationManager)->post($this->pickrushUrl("/station/trips/{$trip->manifest_no}/dispatch"),
            ['vehicle_id' => $truck->id, 'driver_id' => $driver->id]);

        $truck->update(['cr_no' => null]);
        $dispatch()->assertSessionHas('errors', fn ($bag) => str_contains($bag->first('operation'), 'no OR/CR on file'));
        $this->as($this->stationManager)->get($this->pickrushUrl("/station/trips/{$trip->manifest_no}"))->assertSee('· no OR/CR');

        $truck->update(['cr_no' => '123456789', 'registration_expiry' => today()->subDay()]);
        $dispatch()->assertSessionHas('errors', fn ($bag) => str_contains($bag->first('operation'), 'registration (OR) expired'));

        $truck->update(['registration_expiry' => today()->addDays(10)]);
        $this->as($this->stationManager)->get($this->pickrushUrl("/station/trips/{$trip->manifest_no}"))->assertSee('· OR expires in 10 days');
        $dispatch()->assertSessionHasNoErrors();
        $this->assertSame('dispatched', $trip->fresh()->status);
        $this->assertEquals(now()->addHours(6)->startOfMinute(), $trip->fresh()->due_at->startOfMinute(), 'a station to its province hub has 6 hours');
    }

    // Arrival ---------------------------------------------------------------------------------

    private ?Staff $sorter = null;

    private function batangasSorter(): Staff
    {
        return $this->sorter ??= $this->staff(StaffRole::Sorter, $this->batangas);
    }

    /** A trip from Tanauan dispatched over HTTP, on its way to Batangas. */
    private function tripOnTheRoad(): Manifest
    {
        [$truck, $driver] = $this->fleet($this->tanauan);
        $trip = $this->draftTrip();
        $this->as($this->stationManager)->post($this->pickrushUrl("/station/trips/{$trip->manifest_no}/dispatch"), ['vehicle_id' => $truck->id, 'driver_id' => $driver->id])
            ->assertSessionHasNoErrors();

        return $trip->fresh();
    }

    public function test_the_receiving_hub_marks_the_truck_arrived(): void
    {
        $trip = $this->tripOnTheRoad();
        $url = fn (string $path = '') => $this->pickrushUrl("/province/trips/{$trip->manifest_no}{$path}");

        $this->as($this->batangasSorter())->get($url())->assertOk()->assertSee('Mark truck arrived')->assertSee('Scan each parcel off the vehicle');
        $this->as($this->batangasSorter())->post($url('/arrived'))->assertSessionHas('success', fn ($m) => str_contains($m, 'Scan each parcel'));
        $trip->refresh();
        $this->assertSame($this->batangasSorter()->id, $trip->arrived_by);
        $this->assertNotNull($trip->arrived_at);
        $this->as($this->batangasSorter())->post($url('/arrived'))
            ->assertSessionHas('errors', fn ($bag) => str_contains($bag->first('operation'), 'already marked arrived'));
        $this->as($this->stationManager)->post($this->pickrushUrl("/station/trips/{$trip->manifest_no}/arrived"))
            ->assertSessionHas('errors', fn ($bag) => str_contains($bag->first('operation'), 'not arriving at your hub'));
    }

    public function test_the_first_scan_off_the_truck_records_its_arrival(): void
    {
        $trip = $this->tripOnTheRoad();
        $parcel = $trip->items()->sole()->parcel;

        $this->travel(2)->hours();
        $this->as($this->batangasSorter())->post($this->pickrushUrl("/province/trips/{$trip->manifest_no}/receive"), ['tracking_no' => $parcel->tracking_no])
            ->assertSessionHasNoErrors();
        $trip->refresh();
        $this->assertSame([ParcelStatus::AtProvinceHub, 'closed', $this->batangasSorter()->id], [$parcel->fresh()->current_status, $trip->status, $trip->arrived_by]);
        $this->assertEquals(now()->startOfMinute(), $trip->arrived_at->startOfMinute());
    }

    public function test_a_trip_not_arrived_by_its_due_time_is_overdue(): void
    {
        $trip = $this->tripOnTheRoad();
        $this->assertFalse($trip->isOverdue());

        $this->travel(7)->hours();
        $this->assertTrue($trip->fresh()->isOverdue());
        $this->assertSame([$trip->id], Manifest::withoutGlobalScopes()->overdue()->pluck('id')->all());
        $this->as($this->stationManager)->get($this->pickrushUrl('/station'))->assertOk()->assertSee('Trips overdue')->assertSee($trip->manifest_no);
        $this->as($this->batangasSorter())->get($this->pickrushUrl('/province'))->assertOk()->assertSee('Trips overdue');
        $this->as($this->batangasSorter())->get($this->pickrushUrl("/province/trips/{$trip->manifest_no}"))->assertOk()->assertSee('Overdue');
        $this->actingAs($this->staff(StaffRole::SuperAdmin), 'pickrush')->get($this->pickrushUrl('/hq/alerts'))->assertOk()->assertSee('Trucks overdue')->assertSee($trip->manifest_no);

        // Marked arrived, late or not, it is no longer overdue.
        app(ManifestService::class)->truckArrived($trip, $this->batangasSorter());
        $this->assertFalse($trip->fresh()->isOverdue());
        $this->assertSame(0, Manifest::withoutGlobalScopes()->overdue()->count());
    }

    public function test_hq_sets_the_trip_hours(): void
    {
        config(['pickrush.fleet.trip_hours.local' => 2]);
        $trip = $this->tripOnTheRoad();
        $this->assertEquals(now()->addHours(2)->startOfMinute(), $trip->due_at->startOfMinute());
        $this->assertSame(24, Manifest::tripHours($this->batangas, $this->station('137404000')->parent));
    }
}
