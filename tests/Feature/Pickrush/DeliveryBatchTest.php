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
use App\Pickrush\Models\RiderToken;
use App\Pickrush\Models\Staff;
use App\Pickrush\Services\ParcelRegistrar;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Deliveries sent to a rider as a batch: accept, scan every parcel onto the vehicle, start the
 * route, deliver (COD + proof photo) or fail each parcel, then hand back the cash and the parcels
 * not delivered at the station.
 */
class DeliveryBatchTest extends TestCase
{
    use BuildsNetwork, RefreshDatabase;

    private Hub $tanauan;

    private Staff $manager;

    private Staff $dispatcher;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->buildNetwork();
        $this->tanauan = $this->station('041029000');
        $this->manager = $this->staff(StaffRole::HubManager, $this->tanauan);
        $this->dispatcher = $this->staff(StaffRole::Dispatcher, $this->tanauan);
    }

    private function rider(): Rider
    {
        static $n = 0;
        $n++;

        $rider = Rider::withoutGlobalScopes()->create([
            'station_hub_id' => $this->tanauan->id, 'full_name' => "Driver {$n}", 'mobile_no' => '0919'.str_pad((string) $n, 7, '0', STR_PAD_LEFT),
            'vehicle_type' => 'motorcycle', 'status' => 'active', 'shift_status' => 'on_shift', 'last_seen_at' => now(),
            'cod_limit' => 10000, 'max_parcels' => 30, 'created_by' => $this->manager->id,
        ]);
        RiderToken::issue($rider);   // logged in to the rider app

        return $rider;
    }

    /** A same-city parcel dropped off at the station: ready for a delivery rider. */
    private function readyParcel(string $buyer, float $cod): Parcel
    {
        $parcel = app(ParcelRegistrar::class)->register([
            'shipper_name' => 'Tote House', 'pickup_address' => 'Altura Bata, Tanauan',
            'pickup_barangay_code' => '041029001', 'pickup_municipality_code' => '041029000',
            'consignee_name' => $buyer, 'consignee_mobile' => '09171234567', 'delivery_address' => "{$buyer}'s house, Altura Bata, Tanauan",
            'delivery_barangay_code' => '041029001', 'delivery_municipality_code' => '041029000',
            'item_description' => 'Canvas tote × 1', 'cod_amount' => $cod,
        ]);
        $this->actingAs($this->dispatcher, 'pickrush')->post($this->pickrushUrl('/station/receiving'), ['tracking_no' => $parcel->tracking_no])
            ->assertSessionHasNoErrors();

        return $parcel->fresh();
    }

    private function api(Rider $rider): static
    {
        return $this->withHeaders(['Authorization' => 'Bearer '.RiderToken::issue($rider), 'Accept' => 'application/json']);
    }

    private function dispatchTo(array $parcels, Rider $rider): RiderBatch
    {
        $this->actingAs($this->dispatcher, 'pickrush')
            ->post($this->pickrushUrl('/station/dispatch'), ['parcel_ids' => collect($parcels)->pluck('id')->all(), 'rider_id' => $rider->id])
            ->assertSessionHasNoErrors();

        return RiderBatch::where('rider_id', $rider->id)->latest('id')->firstOrFail();
    }

    private function photo(): UploadedFile
    {
        return UploadedFile::fake()->create('proof.jpg', 120, 'image/jpeg');
    }

    public function test_delivery_stops_carry_the_buyer_pin_for_navigation(): void
    {
        $rider = $this->rider();
        $ana = $this->readyParcel('Ana Reyes', 500);
        $ana->forceFill(['delivery_lat' => 14.0870, 'delivery_lng' => 121.1490])->save();
        $ben = $this->readyParcel('Ben Cruz', 700);
        $this->dispatchTo([$ana, $ben], $rider);

        $this->api($rider)->getJson('/api/rider/dashboard')->assertOk()
            ->assertJsonPath('batches.0.stops.0.name', 'Ana Reyes')
            ->assertJsonPath('batches.0.stops.0.lat', 14.087)
            ->assertJsonPath('batches.0.stops.0.lng', 121.149)
            ->assertJsonPath('batches.0.stops.1.lat', null);
    }

    public function test_the_buyers_number_reaches_the_rider_only_after_accepting(): void
    {
        $rider = $this->rider();
        $batch = $this->dispatchTo([$this->readyParcel('Ana Reyes', 500)], $rider);
        $api = $this->api($rider);

        $api->getJson("/api/rider/batches/{$batch->id}")->assertOk()->assertJsonPath('batch.stops.0.mobile', null);

        $api->postJson("/api/rider/batches/{$batch->id}/accept")->assertOk();
        $api->getJson("/api/rider/batches/{$batch->id}")->assertOk()->assertJsonPath('batch.stops.0.mobile', '09171234567');
    }

    public function test_a_delivery_batch_goes_from_loading_to_delivered_and_ends_with_cash_and_returns_at_the_station(): void
    {
        $rider = $this->rider();
        $ana = $this->readyParcel('Ana Reyes', 500);
        $ben = $this->readyParcel('Ben Cruz', 700);
        $batch = $this->dispatchTo([$ana, $ben], $rider);

        $this->assertSame('delivery', $batch->type);
        $this->assertStringStartsWith('DV-', $batch->batch_no);
        $this->assertSame('delivery_offered', RiderNotification::sole()->type);
        $api = $this->api($rider);

        $api->getJson('/api/rider/dashboard')->assertOk()
            ->assertJsonPath('batches.0.type', 'delivery')
            ->assertJsonPath('batches.0.cod_total', fn ($v) => (float) $v === 1200.0)
            ->assertJsonCount(2, 'batches.0.stops')
            ->assertJsonPath('batches.0.stops.0.name', 'Ana Reyes')
            ->assertJsonPath('batches.0.stops.0.items.0.status', 'to_load');

        // Loading at the station: every parcel scanned before the route starts.
        $api->postJson("/api/rider/batches/{$batch->id}/load", ['tracking_no' => $ana->tracking_no])->assertStatus(422);   // not accepted yet
        $api->postJson("/api/rider/batches/{$batch->id}/accept")->assertOk();
        $api->postJson("/api/rider/batches/{$batch->id}/load", ['tracking_no' => 'PR-NOT-OURS'])->assertStatus(422);
        $api->postJson("/api/rider/batches/{$batch->id}/load", ['tracking_no' => strtolower($ana->tracking_no)])->assertOk()
            ->assertJsonPath('batch.loaded_count', 1)
            ->assertJsonPath('batch.stage', 'pickup');
        $api->postJson("/api/rider/batches/{$batch->id}/start")->assertStatus(422)
            ->assertJsonPath('message', fn ($m) => str_contains($m, '1 parcel not scanned'));
        $api->postJson("/api/rider/batches/{$batch->id}/load", ['tracking_no' => $ben->tracking_no])->assertOk();
        $api->postJson("/api/rider/batches/{$batch->id}/start", ['request_key' => 'go'])->assertOk()
            ->assertJsonPath('batch.started_at', fn ($at) => $at !== null)
            ->assertJsonPath('batch.stops.0.items.0.status', 'out_for_delivery');
        $this->assertSame(ParcelStatus::OutForDelivery, $ana->fresh()->current_status);

        // Stop 1: delivered with COD and a proof photo.
        $anaTask = Assignment::where('parcel_id', $ana->id)->sole();
        $api->postJson("/api/rider/batches/{$batch->id}/stops/arrive", ['assignment_ids' => [$anaTask->id], 'lat' => 14.09, 'lng' => 121.15])->assertOk();
        $api->post("/api/rider/batches/{$batch->id}/deliveries/{$anaTask->id}/deliver", ['tracking_no' => $ben->tracking_no, 'cod_confirmed' => 500, 'photo' => $this->photo()])
            ->assertStatus(422)->assertJsonPath('message', fn ($m) => str_contains($m, 'Wrong parcel'));
        $api->post("/api/rider/batches/{$batch->id}/deliveries/{$anaTask->id}/deliver", ['tracking_no' => $ana->tracking_no, 'cod_confirmed' => 450, 'photo' => $this->photo()])
            ->assertStatus(422)->assertJsonPath('message', fn ($m) => str_contains($m, '₱500.00'));
        $api->post("/api/rider/batches/{$batch->id}/deliveries/{$anaTask->id}/deliver", ['tracking_no' => $ana->tracking_no, 'cod_confirmed' => 500, 'photo' => $this->photo(), 'request_key' => 'a1'])
            ->assertOk()
            ->assertJsonPath('batch.stops.0.status', 'delivered')
            ->assertJsonPath('batch.stage', 'deliver');
        $this->assertSame(ParcelStatus::Delivered, $ana->fresh()->current_status);
        Storage::disk('local')->assertExists($anaTask->fresh()->proof_photo_path);

        // Stop 2: nobody home.
        $benTask = Assignment::where('parcel_id', $ben->id)->sole();
        $api->post("/api/rider/batches/{$batch->id}/deliveries/{$benTask->id}/fail", ['reason' => 'buyer_unavailable', 'photo' => $this->photo()])
            ->assertOk()
            ->assertJsonPath('batch.stops.1.status', 'failed')
            ->assertJsonPath('batch.to_return_count', 1)
            ->assertJsonPath('batch.to_remit', fn ($v) => (float) $v === 500.0)
            ->assertJsonPath('batch.stage', 'dropped_off');
        $this->assertSame(ParcelStatus::DeliveryFailed, $ben->fresh()->current_status);

        // Still on the rider's vehicle: not in the queue, and cannot be given to someone else.
        $this->actingAs($this->dispatcher, 'pickrush')->get($this->pickrushUrl('/station/dispatch'))->assertDontSee($ben->tracking_no);
        $this->actingAs($this->dispatcher, 'pickrush')->post($this->pickrushUrl('/station/dispatch'), ['parcel_ids' => [$ben->id], 'rider_id' => $this->rider()->id])
            ->assertSessionHas('errors', fn ($bag) => str_contains($bag->first('operation'), 'still with the rider'));

        $this->actingAs($this->dispatcher, 'pickrush')->get($this->pickrushUrl('/station/failed'))->assertSee("Still with {$rider->full_name}");

        // Back at the station: hand back the parcel, then the cash.
        $api->postJson("/api/rider/batches/{$batch->id}/hub/arrive", ['lat' => 14.0863, 'lng' => 121.1497])->assertOk();
        $this->actingAs($this->dispatcher, 'pickrush')->post($this->pickrushUrl('/station/receiving'), ['tracking_no' => $ben->tracking_no])
            ->assertSessionHas('success', fn ($m) => str_contains($m, 'handed back by'));
        $api->getJson("/api/rider/batches/{$batch->id}")->assertOk()
            ->assertJsonPath('batch.status', 'accepted')
            ->assertJsonPath('batch.stops.1.items.0.status', 'returned')
            ->assertJsonPath('batch.to_return_count', 0);

        $this->actingAs($this->manager, 'pickrush')->post($this->pickrushUrl("/station/cash/riders/{$rider->id}"), ['received_amount' => 500])
            ->assertSessionHasNoErrors();
        $api->getJson("/api/rider/batches/{$batch->id}")->assertOk()
            ->assertJsonPath('batch.status', 'completed')
            ->assertJsonPath('batch.remitted', true);

        // The failed parcel is back in the queue for another attempt.
        $this->actingAs($this->dispatcher, 'pickrush')->get($this->pickrushUrl('/station/dispatch'))->assertSee($ben->tracking_no);
        $api->getJson('/api/rider/dashboard')->assertOk()->assertJsonPath('today.delivered', 1);

        // Activity: deliveries only on the delivery tab, newest first; nothing on the pickup tab.
        $api->getJson('/api/rider/activity?kind=delivery')->assertOk()
            ->assertJsonPath('activity.0.type', 'failed')
            ->assertJsonPath('activity.1.type', 'delivered')
            ->assertJsonPath('next', null);
        $api->getJson('/api/rider/activity?kind=pickup')->assertOk()->assertJsonCount(0, 'activity');
        $api->getJson('/api/rider/activity?kind=everything')->assertStatus(422);
        $api->getJson('/api/rider/dashboard')->assertOk()->assertJsonPath('activity.0.type', 'failed');

        // Earnings page: only the delivered parcel earns; the cash was remitted.
        $rate = (float) config('pickrush.shipping.min_rider_pay');      // a parcel without a class pays the minimum
        $api->getJson('/api/rider/earnings')->assertOk()
            ->assertJsonPath('today.deliveries', 1)
            ->assertJsonPath('today.pickups', 0)
            ->assertJsonPath('today.earnings', fn ($v) => (float) $v === $rate)
            ->assertJsonPath('month.deliveries', 1)
            ->assertJsonCount(7, 'days')
            ->assertJsonPath('days.6.date', today()->toDateString())
            ->assertJsonPath('days.6.deliveries', 1)
            ->assertJsonPath('batches.0.batch_no', $batch->batch_no)
            ->assertJsonPath('batches.0.parcels', 1)
            ->assertJsonPath('cod.in_hand', fn ($v) => (float) $v === 0.0)
            ->assertJsonPath('cod.remitted_this_month', fn ($v) => (float) $v === 500.0)
            ->assertJsonPath('cod.last_remittance.amount', fn ($v) => (float) $v === 500.0);
    }

    public function test_a_failed_parcel_cannot_be_returned_to_the_seller_until_the_rider_hands_it_back(): void
    {
        $rider = $this->rider();
        $parcel = $this->readyParcel('Ben Cruz', 0);
        $batch = $this->dispatchTo([$parcel], $rider);
        $api = $this->api($rider);
        $api->postJson("/api/rider/batches/{$batch->id}/accept")->assertOk();
        $api->postJson("/api/rider/batches/{$batch->id}/load", ['tracking_no' => $parcel->tracking_no])->assertOk();
        $api->postJson("/api/rider/batches/{$batch->id}/start")->assertOk();
        $task = Assignment::where('parcel_id', $parcel->id)->sole();
        $api->post("/api/rider/batches/{$batch->id}/deliveries/{$task->id}/fail", ['reason' => 'buyer_unavailable', 'photo' => $this->photo()])->assertOk();

        // Still on the rider's vehicle: no return yet.
        $this->actingAs($this->dispatcher, 'pickrush')->post($this->pickrushUrl("/station/failed/{$parcel->tracking_no}/return"), ['reason' => 'Buyer refused'])
            ->assertSessionHas('errors', fn ($bag) => str_contains($bag->first('operation'), 'still with the rider'));
        $this->assertSame(ParcelStatus::DeliveryFailed, $parcel->fresh()->current_status);

        // Handed back at Receiving: the batch completes, and now the return can start.
        $this->actingAs($this->dispatcher, 'pickrush')->post($this->pickrushUrl('/station/receiving'), ['tracking_no' => $parcel->tracking_no])
            ->assertSessionHas('success', fn ($m) => str_contains($m, 'handed back by'));
        $this->assertSame('completed', $batch->fresh()->status);
        $this->actingAs($this->dispatcher, 'pickrush')->post($this->pickrushUrl("/station/failed/{$parcel->tracking_no}/return"), ['reason' => 'Buyer refused'])
            ->assertSessionHasNoErrors();
        $this->assertSame(ParcelStatus::ReturningToSender, $parcel->fresh()->current_status);
    }

    public function test_a_parcel_already_returning_to_the_seller_can_still_be_handed_back_by_the_rider(): void
    {
        $rider = $this->rider();
        $parcel = $this->readyParcel('Ben Cruz', 0);
        $batch = $this->dispatchTo([$parcel], $rider);
        $api = $this->api($rider);
        $api->postJson("/api/rider/batches/{$batch->id}/accept")->assertOk();
        $api->postJson("/api/rider/batches/{$batch->id}/load", ['tracking_no' => $parcel->tracking_no])->assertOk();
        $api->postJson("/api/rider/batches/{$batch->id}/start")->assertOk();
        $task = Assignment::where('parcel_id', $parcel->id)->sole();
        $api->post("/api/rider/batches/{$batch->id}/deliveries/{$task->id}/fail", ['reason' => 'buyer_unavailable', 'photo' => $this->photo()])->assertOk();
        // Data from before the fix: the return was started while the rider still had it.
        app(\App\Pickrush\Services\ParcelLedger::class)->record($parcel, ParcelStatus::ReturningToSender, $this->tanauan, ['note' => 'Buyer refused']);

        $this->actingAs($this->dispatcher, 'pickrush')->post($this->pickrushUrl('/station/receiving'), ['tracking_no' => $parcel->tracking_no])
            ->assertSessionHas('success', fn ($m) => str_contains($m, 'handed back by'));
        $this->assertNotNull($task->fresh()->returned_at);
        $this->assertSame('completed', $batch->fresh()->status);
    }

    public function test_the_activity_history_pages_back_with_a_cursor(): void
    {
        $rider = $this->rider();
        $parcels = collect(range(1, 33))->map(fn (int $i) => $this->readyParcel("Buyer {$i}", 0));
        $ledger = app(\App\Pickrush\Services\ParcelLedger::class);
        foreach ($parcels as $i => $parcel) {
            $ledger->record($parcel, ParcelStatus::AssignedToRider, $this->tanauan, ['rider_id' => $rider->id]);
            $ledger->record($parcel, ParcelStatus::OutForDelivery, $this->tanauan, ['rider_id' => $rider->id, 'occurred_at' => now()->subMinutes(100 - $i)]);
        }
        $api = $this->api($rider);

        $first = $api->getJson('/api/rider/activity?kind=delivery')->assertOk()->assertJsonCount(30, 'activity');
        $this->assertNotNull($next = $first->json('next'));
        $second = $api->getJson('/api/rider/activity?kind=delivery&before='.urlencode($next))->assertOk()
            ->assertJsonCount(3, 'activity')
            ->assertJsonPath('next', null);

        $titles = collect([...$first->json('activity'), ...$second->json('activity')])->pluck('title');
        $this->assertCount(33, $titles->unique());   // nothing shown twice, nothing skipped
    }

    public function test_declining_a_delivery_batch_puts_the_parcels_back_in_the_station_queue(): void
    {
        $rider = $this->rider();
        $parcel = $this->readyParcel('Ana Reyes', 500);
        $batch = $this->dispatchTo([$parcel], $rider);
        $this->assertSame(ParcelStatus::AssignedToRider, $parcel->fresh()->current_status);

        $this->api($rider)->postJson("/api/rider/batches/{$batch->id}/decline", ['reason' => 'Flat tire'])->assertOk();

        $this->assertSame(ParcelStatus::ArrivedAtStation, $parcel->fresh()->current_status);
        $this->actingAs($this->dispatcher, 'pickrush')->get($this->pickrushUrl('/station/dispatch'))->assertSee($parcel->tracking_no);
    }

    public function test_a_parcel_not_found_at_loading_can_be_left_with_a_reason(): void
    {
        $rider = $this->rider();
        $loaded = $this->readyParcel('Ana Reyes', 500);
        $missing = $this->readyParcel('Ben Cruz', 700);
        $batch = $this->dispatchTo([$loaded, $missing], $rider);
        $api = $this->api($rider);
        $api->postJson("/api/rider/batches/{$batch->id}/accept")->assertOk();
        $api->postJson("/api/rider/batches/{$batch->id}/load", ['tracking_no' => $loaded->tracking_no])->assertOk();

        $api->postJson("/api/rider/batches/{$batch->id}/start", ['leave_behind_reason' => 'Not on the shelf'])->assertOk()
            ->assertJsonPath('batch.parcel_count', 1);

        $this->assertSame(ParcelStatus::OutForDelivery, $loaded->fresh()->current_status);
        $this->assertSame(ParcelStatus::ArrivedAtStation, $missing->fresh()->current_status);
        $this->assertSame('not_loaded', Assignment::where('parcel_id', $missing->id)->sole()->fail_reason);
    }
}
