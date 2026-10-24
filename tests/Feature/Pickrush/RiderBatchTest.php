<?php

namespace Tests\Feature\Pickrush;

use App\Pickrush\Enums\ParcelStatus;
use App\Pickrush\Enums\StaffRole;
use App\Pickrush\Models\Assignment;
use App\Pickrush\Models\Hub;
use App\Pickrush\Models\Parcel;
use App\Pickrush\Models\Rider;
use App\Pickrush\Models\RiderBatch;
use App\Pickrush\Models\RiderNotification;
use App\Pickrush\Models\RiderReport;
use App\Pickrush\Models\RiderToken;
use App\Pickrush\Models\Staff;
use App\Pickrush\Services\ParcelRegistrar;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Pickups sent to a rider as a batch: notified in the rider app, accepted or declined, collected. */
class RiderBatchTest extends TestCase
{
    use BuildsNetwork, RefreshDatabase;

    private Hub $tanauan;

    private Staff $dispatcher;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildNetwork();
        $this->tanauan = $this->station('041029000');
        $this->staff(StaffRole::HubManager, $this->tanauan);
        $this->dispatcher = $this->staff(StaffRole::Dispatcher, $this->tanauan);
    }

    private function rider(array $overrides = []): Rider
    {
        static $n = 0;
        $n++;

        return Rider::withoutGlobalScopes()->create(array_merge([
            'station_hub_id' => $this->tanauan->id, 'full_name' => "Rider {$n}", 'mobile_no' => '0918'.str_pad((string) $n, 7, '0', STR_PAD_LEFT),
            'vehicle_type' => 'motorcycle', 'status' => 'active', 'shift_status' => 'on_shift',
            'cod_limit' => 10000, 'max_parcels' => 30, 'created_by' => $this->dispatcher->id,
        ], $overrides));
    }

    private function parcel(string $seller, string $address = 'Altura Bata, Tanauan', array $overrides = []): Parcel
    {
        return app(ParcelRegistrar::class)->register($overrides + [
            'shipper_name' => $seller, 'shipper_mobile' => '09170000000', 'pickup_address' => $address,
            'pickup_barangay_code' => '041029001', 'pickup_municipality_code' => '041029000',
            'consignee_name' => 'Ana Reyes', 'delivery_address' => 'Aguila, San Jose',
            'delivery_barangay_code' => '041005001', 'delivery_municipality_code' => '041005000',
            'item_description' => 'Tote bag × 1', 'cod_amount' => 500,
        ]);
    }

    private function api(Rider $rider): static
    {
        return $this->withHeaders(['Authorization' => 'Bearer '.RiderToken::issue($rider), 'Accept' => 'application/json']);
    }

    private function assignPickups(array $parcels, Rider $rider): void
    {
        $this->actingAs($this->dispatcher, 'pickrush')
            ->post($this->pickrushUrl('/station/dispatch'), ['parcel_ids' => collect($parcels)->pluck('id')->all(), 'rider_id' => $rider->id])
            ->assertSessionHasNoErrors();
    }

    public function test_the_dispatcher_filters_pickups_by_seller_and_groups_them_into_stops(): void
    {
        $this->parcel('Tote House');
        $this->parcel('Tote House');
        $this->parcel('Kape Barako Co.');

        $this->actingAs($this->dispatcher, 'pickrush')->get($this->pickrushUrl('/station/dispatch?view=pickups'))
            ->assertOk()
            ->assertSeeInOrder(['Kape Barako Co.', '1 parcel', 'Tote House', '2 parcels'])
            ->assertSee('Sellers waiting');

        $this->actingAs($this->dispatcher, 'pickrush')->get($this->pickrushUrl('/station/dispatch?view=pickups&seller=tote'))
            ->assertOk()
            ->assertSee('Seller pickups (2)')
            ->assertSee('Tote House')
            ->assertDontSee('Kape Barako Co. · 1', false);
    }

    public function test_pickups_given_to_a_rider_arrive_as_one_batch_with_a_notification(): void
    {
        $rider = $this->rider();
        $parcels = [$this->parcel('Tote House'), $this->parcel('Tote House'), $this->parcel('Kape Barako Co.', 'Bagbag, Tanauan')];

        $this->assignPickups($parcels, $rider);

        $batch = RiderBatch::sole();
        $this->assertSame('offered', $batch->status);
        $this->assertSame(3, Assignment::where('batch_id', $batch->id)->count());
        $this->assertSame('pickup_offered', RiderNotification::where('rider_id', $rider->id)->sole()->type);

        $api = $this->api($rider);
        $api->getJson('/api/rider/pulse')->assertOk()
            ->assertJsonPath('unread_notifications', 1)
            ->assertJsonPath('offered_batch_ids', [$batch->id]);

        $api->getJson('/api/rider/dashboard')->assertOk()
            ->assertJsonPath('batches.0.batch_no', $batch->batch_no)
            ->assertJsonPath('batches.0.status', 'offered')
            ->assertJsonPath('batches.0.parcel_count', 3)
            ->assertJsonCount(2, 'batches.0.stops')
            ->assertJsonPath('batches.0.stops.0.seller_name', 'Tote House')
            ->assertJsonPath('batches.0.stops.0.parcel_count', 2)
            ->assertJsonPath('batches.0.drop_off.name', $this->tanauan->name);

        $this->actingAs($this->dispatcher, 'pickrush')->get($this->pickrushUrl('/station/dispatch?view=assigned'))
            ->assertSee('Waiting for rider to accept');
    }

    public function test_the_rider_arrives_scans_each_waybill_and_the_batch_ends_at_the_station(): void
    {
        $this->tanauan->update(['lat' => 14.0863, 'lng' => 121.1497]);
        $rider = $this->rider();
        $tote = $this->parcel('Tote House', overrides: ['pickup_lat' => 14.0900, 'pickup_lng' => 121.1500]);
        $kape = $this->parcel('Kape Barako Co.', 'Bagbag, Tanauan');          // no shop pin
        $this->assignPickups([$tote, $kape], $rider);
        $batch = RiderBatch::sole();
        $api = $this->api($rider);
        $toteTask = Assignment::where('parcel_id', $tote->id)->sole();

        $api->postJson("/api/rider/batches/{$batch->id}/pickups", ['tracking_nos' => [$tote->tracking_no]])
            ->assertStatus(422)->assertJsonPath('message', 'Accept the batch before collecting parcels.');
        $api->postJson("/api/rider/batches/{$batch->id}/accept")->assertOk()
            ->assertJsonPath('batch.stops.0.lat', 14.09)
            ->assertJsonPath('batch.stops.1.lat', null)
            ->assertJsonPath('batch.drop_off.lat', 14.0863);

        // With the arrival radius enforced, about 1 km from the shop pin is refused.
        config(['pickrush.enforce_arrival_radius' => true]);
        $api->postJson("/api/rider/batches/{$batch->id}/stops/arrive", ['assignment_ids' => [$toteTask->id], 'lat' => 14.0990, 'lng' => 121.1500])
            ->assertStatus(422)->assertJsonPath('message', fn ($m) => str_contains($m, 'unlocks within 150 m'));

        // Not enforced (the default): accepted anywhere, and the distance is kept for the station.
        config(['pickrush.enforce_arrival_radius' => false]);
        $api->postJson("/api/rider/batches/{$batch->id}/stops/arrive", ['assignment_ids' => [$toteTask->id], 'lat' => 14.0990, 'lng' => 121.1500, 'accuracy' => 12])
            ->assertOk()
            ->assertJsonPath('batch.arrival_locked', false)
            ->assertJsonPath('batch.stops.0.arrived_at', fn ($at) => $at !== null);
        $this->assertEqualsWithDelta(1000, $toteTask->fresh()->arrival_distance_m, 20);

        $api->postJson("/api/rider/batches/{$batch->id}/pickups", ['tracking_nos' => ['PR-NOT-OURS']])
            ->assertStatus(422)->assertJsonPath('message', fn ($m) => str_contains($m, 'Not part of this batch'));
        $api->postJson("/api/rider/batches/{$batch->id}/pickups", ['tracking_nos' => [strtolower($tote->tracking_no)], 'request_key' => 'abc'])
            ->assertOk()
            ->assertJsonPath('batch.stage', 'pickup')
            ->assertJsonPath('batch.stops.0.status', 'picked_up')
            ->assertJsonPath('batch.stops.0.items.0.status', 'picked_up')
            ->assertJsonPath('batch.stops.1.status', 'pending');
        $this->assertSame(ParcelStatus::PickedUp, $tote->fresh()->current_status);

        // A retried request changes nothing.
        $api->postJson("/api/rider/batches/{$batch->id}/pickups", ['tracking_nos' => [$tote->tracking_no], 'request_key' => 'abc'])->assertOk();
        $this->assertSame(1, $tote->events()->where('status', ParcelStatus::PickedUp)->count());

        // Not at the station yet while a stop is left.
        $api->postJson("/api/rider/batches/{$batch->id}/hub/arrive", ['lat' => 14.0863, 'lng' => 121.1497])->assertStatus(422);

        $kapeTask = Assignment::where('parcel_id', $kape->id)->sole();
        $api->postJson("/api/rider/batches/{$batch->id}/stops/arrive", ['assignment_ids' => [$kapeTask->id], 'lat' => 14.2, 'lng' => 121.2])->assertOk();
        $this->assertNull($kapeTask->fresh()->arrival_distance_m, 'no shop pin: the spot is recorded, not checked');
        $api->postJson("/api/rider/batches/{$batch->id}/pickups", ['tracking_nos' => [$kape->tracking_no]])->assertOk();
        $this->assertSame('accepted', $batch->fresh()->status, 'still carrying the parcels to the station');

        $api->postJson("/api/rider/batches/{$batch->id}/hub/arrive", ['lat' => 14.0866, 'lng' => 121.1497])->assertOk()
            ->assertJsonPath('batch.drop_off.arrived_at', fn ($at) => $at !== null);

        foreach ([$tote, $kape] as $parcel) {
            $this->actingAs($this->dispatcher, 'pickrush')->post($this->pickrushUrl('/station/receiving'), ['tracking_no' => $parcel->tracking_no])
                ->assertSessionHasNoErrors();
        }

        $api->getJson("/api/rider/batches/{$batch->id}")->assertOk()
            ->assertJsonPath('batch.status', 'completed')
            ->assertJsonPath('batch.stage', 'dropped_off')
            ->assertJsonPath('batch.stops.0.items.0.status', 'received');
        $this->api($rider)->getJson('/api/rider/dashboard')->assertOk()
            ->assertJsonCount(0, 'batches')
            ->assertJsonPath('today.pickups', 2)
            ->assertJsonPath('today.earnings', fn ($earned) => (float) $earned === 2 * (float) config('pickrush.rider_pay.pickup_per_parcel'))
            ->assertJsonPath('activity.0.type', 'dropped_off')
            ->assertJsonPath('activity.0.count', 2);
    }

    public function test_parcels_the_seller_could_not_hand_over_go_back_to_the_queue_with_the_reason(): void
    {
        $rider = $this->rider();
        $ready = $this->parcel('Tote House');
        $notReady = $this->parcel('Tote House');
        $this->assignPickups([$ready, $notReady], $rider);
        $batch = RiderBatch::sole();
        $api = $this->api($rider);
        $api->postJson("/api/rider/batches/{$batch->id}/accept")->assertOk();

        $api->postJson("/api/rider/batches/{$batch->id}/pickups", ['tracking_nos' => [$ready->tracking_no], 'not_collected' => [['tracking_no' => $notReady->tracking_no, 'reason' => 'other']]])
            ->assertStatus(422)->assertJsonPath('message', fn ($m) => str_contains($m, 'Write a note'));

        $api->postJson("/api/rider/batches/{$batch->id}/pickups", [
            'tracking_nos' => [$ready->tracking_no],
            'not_collected' => [['tracking_no' => $notReady->tracking_no, 'reason' => 'not_ready']],
        ])->assertOk()
            ->assertJsonPath('batch.parcel_count', 1)
            ->assertJsonPath('batch.not_collected_count', 1)
            ->assertJsonPath('batch.stops.0.status', 'picked_up')
            ->assertJsonPath('batch.stops.0.items.1.status', 'not_collected');

        $this->assertSame(ParcelStatus::WaitingPickup, $notReady->fresh()->current_status);
        $this->actingAs($this->dispatcher, 'pickrush')->get($this->pickrushUrl('/station/dispatch?view=pickups'))
            ->assertSee($notReady->tracking_no)
            ->assertSee('Not collected: Parcel not ready yet');
    }

    public function test_the_rider_reports_a_problem_and_the_station_handles_it(): void
    {
        $rider = $this->rider();
        $this->assignPickups([$this->parcel('Tote House')], $rider);
        $batch = RiderBatch::sole();

        $this->api($rider)->getJson('/api/rider/options')->assertOk()->assertJsonPath('pickup_failure_reasons.0.key', 'not_ready');
        $this->api($rider)->postJson("/api/rider/batches/{$batch->id}/reports", ['type' => 'vehicle', 'note' => 'Flat tire', 'lat' => 14.09, 'lng' => 121.15])->assertOk();

        $this->actingAs($this->dispatcher, 'pickrush')->get($this->pickrushUrl('/station'))
            ->assertSee("{$rider->full_name} reported: Vehicle problem")
            ->assertSee('Flat tire');
        $report = RiderReport::sole();
        $this->actingAs($this->dispatcher, 'pickrush')->post($this->pickrushUrl("/station/rider-reports/{$report->id}/resolve"))->assertSessionHasNoErrors();
        $this->assertNotNull($report->fresh()->resolved_at);
    }

    public function test_receiving_offers_the_phone_camera_scanner(): void
    {
        $this->actingAs($this->dispatcher, 'pickrush')->get($this->pickrushUrl('/station/receiving'))->assertOk()
            ->assertSee('data-scan-camera-open', false)
            ->assertSee('html5-qrcode@2.3.8', false)
            // camera=() would make the browser refuse without ever asking.
            ->assertHeader('Permissions-Policy', 'camera=(self), microphone=(), geolocation=(self)');
    }

    public function test_the_hub_manager_pins_the_station(): void
    {
        $manager = Staff::where('role', StaffRole::HubManager)->where('hub_id', $this->tanauan->id)->sole();
        $this->actingAs($this->dispatcher, 'pickrush')->get($this->pickrushUrl('/station/location'))->assertOk()->assertSee('Only the hub manager');
        $this->actingAs($this->dispatcher, 'pickrush')->put($this->pickrushUrl('/station/location'), ['address_line' => 'X', 'lat' => 14.08, 'lng' => 121.14])->assertForbidden();

        // The address starts at the station's own city; its barangays are offered.
        $this->actingAs($manager, 'pickrush')->get($this->pickrushUrl('/station/location'))->assertOk()
            ->assertSee('Batangas')
            ->assertSee('City of Tanauan (this station)')
            ->assertSee('San Jose')                     // another city of the same province
            ->assertDontSee('City of Calamba')          // Laguna: another province
            ->assertSee('Altura Bata');
        $this->actingAs($manager, 'pickrush')->getJson($this->pickrushUrl('/station/location/barangays?city=041029000'))
            ->assertOk()->assertJsonPath('barangays.0.name', 'Altura Bata');
        $this->actingAs($manager, 'pickrush')->getJson($this->pickrushUrl('/station/location/barangays?city=043405000'))->assertStatus(422);

        $this->actingAs($manager, 'pickrush')->put($this->pickrushUrl('/station/location'), [
            'address_city_code' => '041029000', 'address_barangay_code' => '043405001',   // a Calamba barangay
            'address_line' => 'Rider Zone', 'lat' => 14.0863, 'lng' => 121.1497,
        ])->assertSessionHasErrors('address_barangay_code');

        $this->actingAs($manager, 'pickrush')->put($this->pickrushUrl('/station/location'), [
            'address_city_code' => '041029000', 'address_barangay_code' => '041029001',
            'address_line' => 'Rider Zone, J.P. Laurel Hwy', 'lat' => 14.0863, 'lng' => 121.1497,
        ])->assertSessionHasNoErrors();
        $station = $this->tanauan->fresh();
        $this->assertSame('Rider Zone, J.P. Laurel Hwy, Brgy. Altura Bata, City of Tanauan, Batangas', $station->address);
        $this->assertEqualsWithDelta(14.0863, (float) $station->lat, 0.0000001);

        $this->actingAs($manager, 'pickrush')->get($this->pickrushUrl('/station/location'))
            ->assertSee('value="Rider Zone, J.P. Laurel Hwy"', false);
    }

    public function test_a_declined_batch_puts_the_pickups_back_in_the_queue(): void
    {
        $rider = $this->rider();
        $parcel = $this->parcel('Tote House');
        $this->assignPickups([$parcel], $rider);
        $batch = RiderBatch::sole();

        $this->api($rider)->postJson("/api/rider/batches/{$batch->id}/decline", ['reason' => 'Motor broke down'])
            ->assertOk()->assertJsonPath('batch.status', 'declined');

        $this->assertSame('reassigned', Assignment::sole()->status);
        $this->actingAs($this->dispatcher, 'pickrush')->get($this->pickrushUrl('/station/dispatch?view=pickups'))
            ->assertSee($parcel->tracking_no)
            ->assertSee("Declined by {$rider->full_name}");

        $this->api($rider)->postJson("/api/rider/batches/{$batch->id}/accept")->assertStatus(422);
    }

    public function test_taking_back_the_last_pickup_cancels_the_batch_and_tells_the_rider(): void
    {
        $rider = $this->rider();
        $this->assignPickups([$this->parcel('Tote House')], $rider);
        $batch = RiderBatch::sole();

        $this->actingAs($this->dispatcher, 'pickrush')
            ->post($this->pickrushUrl('/station/assignments/'.Assignment::sole()->id.'/unassign'), ['reason' => 'Seller will drop off'])
            ->assertSessionHasNoErrors();

        $this->assertSame('cancelled', $batch->fresh()->status);
        $this->assertSame('task_removed', RiderNotification::latest('id')->first()->type);
        $this->api($rider)->getJson('/api/rider/batches')->assertOk()->assertJsonCount(0, 'batches');
    }

    public function test_riders_only_see_their_own_batches_and_must_be_active(): void
    {
        $rider = $this->rider();
        $other = $this->rider();
        $this->assignPickups([$this->parcel('Tote House')], $rider);
        $batch = RiderBatch::sole();

        $this->api($other)->getJson("/api/rider/batches/{$batch->id}")->assertNotFound();
        $this->api($other)->postJson("/api/rider/batches/{$batch->id}/accept")->assertNotFound();

        $applicant = $this->rider(['status' => 'pending']);
        $this->api($applicant)->getJson('/api/rider/dashboard')->assertForbidden();
    }

    public function test_the_rider_goes_offline_and_reads_notifications(): void
    {
        $rider = $this->rider();
        $this->assignPickups([$this->parcel('Tote House')], $rider);
        $api = $this->api($rider);

        $api->getJson('/api/rider/notifications')->assertOk()->assertJsonPath('notifications.0.read', false);
        $api->postJson('/api/rider/notifications/read')->assertOk();
        $api->getJson('/api/rider/pulse')->assertJsonPath('unread_notifications', 0);

        $api->patchJson('/api/rider/shift', ['shift_status' => 'off_duty'])->assertOk()->assertJsonPath('shift_status', 'off_duty');
        $this->assertFalse($rider->fresh()->isOnShift());
    }
}
