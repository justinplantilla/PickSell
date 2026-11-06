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
        $this->as($this->stationManager)->post($this->pickrushUrl('/fleet'), ['plate_no' => 'nab 1234', 'vehicle_type' => 'truck'])->assertSessionHasNoErrors();
        $this->as($this->stationManager)->post($this->pickrushUrl('/fleet'), ['plate_no' => 'nab 5678', 'vehicle_type' => 'truck'])
            ->assertSessionHas('errors', fn ($bag) => str_contains($bag->first('operation'), 'at most 1 active truck'));

        $first = Vehicle::withoutGlobalScopes()->sole();
        $this->assertSame(['NAB 1234', 'Pickrush Logistics'], [$first->plate_no, $first->registered_owner]);

        // Retire the first truck, then its replacement fits; the old one cannot come back while the new one is active.
        $this->as($this->stationManager)->put($this->pickrushUrl("/fleet/{$first->id}"), ['plate_no' => 'NAB 1234', 'vehicle_type' => 'truck'])->assertSessionHasNoErrors();
        $this->as($this->stationManager)->post($this->pickrushUrl('/fleet'), ['plate_no' => 'nab 5678', 'vehicle_type' => 'truck'])->assertSessionHasNoErrors();
        $this->as($this->stationManager)->put($this->pickrushUrl("/fleet/{$first->id}"), ['plate_no' => 'NAB 1234', 'vehicle_type' => 'truck', 'is_active' => 1])
            ->assertSessionHas('errors');
        $this->assertFalse($first->fresh()->is_active);

        foreach (['PRV 1', 'PRV 2', 'PRV 3'] as $plate) {
            $this->as($this->provinceManager)->post($this->pickrushUrl('/fleet'), ['plate_no' => $plate, 'vehicle_type' => 'truck'])->assertSessionHasNoErrors();
        }
        $this->as($this->provinceManager)->post($this->pickrushUrl('/fleet'), ['plate_no' => 'PRV 4', 'vehicle_type' => 'truck'])
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
        $this->as($this->provinceManager)->post($this->pickrushUrl('/fleet'), ['plate_no' => 'PRV 1', 'vehicle_type' => 'truck', 'default_driver_id' => $driver->id])
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
}
