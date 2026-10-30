<?php

namespace Tests\Feature\Pickrush;

use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\Product;
use App\Models\User;
use App\Notifications\BuyerOrderUpdate;
use App\Pickrush\Bridge\PicksellBridge;
use App\Pickrush\Enums\ParcelStatus;
use App\Pickrush\Enums\StaffRole;
use App\Pickrush\Models\Hub;
use App\Pickrush\Models\Parcel;
use App\Pickrush\Models\Rider;
use App\Pickrush\Models\RiderBatch;
use App\Pickrush\Models\RiderToken;
use App\Pickrush\Models\Staff;
use App\Pickrush\Services\ManifestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The whole journey, through the same doors people use: a seller hands over Picksell orders,
 * a rider picks them up with the app, the station receives them, a rider delivers them (one
 * fails first and is retried), the cash is remitted, and the buyer and seller see each step.
 */
class EndToEndTest extends TestCase
{
    use BuildsNetwork, RefreshDatabase;

    private Hub $tanauan;

    private Staff $manager;

    private Staff $dispatcher;

    private User $seller;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        Storage::fake('local');
        $this->buildNetwork();
        DB::table('geo_barangays')->insert([
            ['code' => '041029002', 'municipality_code' => '041029000', 'name' => 'Altura Matanda'],
            ['code' => '041005001', 'municipality_code' => '041005000', 'name' => 'Aguila'],
        ]);
        $this->tanauan = $this->station('041029000');
        $this->tanauan->update(['lat' => 14.0863, 'lng' => 121.1497, 'address' => 'J.P. Laurel Hwy, Tanauan']);
        $this->manager = $this->staff(StaffRole::HubManager, $this->tanauan, ['mobile_no' => '09170000001']);
        $this->dispatcher = $this->staff(StaffRole::Dispatcher, $this->tanauan);
        $this->seller = $this->user('seller', 'City of Tanauan', 'Altura Bata', [
            'business_name' => 'Tote House', 'pickup_lat' => 14.0900, 'pickup_lng' => 121.1500,
        ]);
    }

    // People ------------------------------------------------------------------------------------

    private function user(string $role, string $municipality, string $barangay, array $extra = []): User
    {
        static $n = 0;
        $n++;

        return User::create(array_merge([
            'role' => $role, 'status' => 'approved', 'first_name' => ucfirst($role), 'last_name' => "E2E{$n}", 'sex' => 'Female',
            'email' => "{$role}{$n}@e2e.test", 'password' => bcrypt('secret-password'), 'contact_no' => '0917000000'.$n,
            'birthday' => '1995-01-01', 'age' => 30, 'province' => 'Batangas', 'municipality' => $municipality, 'barangay' => $barangay,
            'house_no' => (string) (10 + $n), 'street' => 'Rizal St',
        ], $extra));
    }

    private function rider(Hub $station, string $name): Rider
    {
        static $n = 0;
        $n++;

        return Rider::withoutGlobalScopes()->create([
            'station_hub_id' => $station->id, 'full_name' => $name, 'mobile_no' => '0920'.str_pad((string) $n, 7, '0', STR_PAD_LEFT),
            'vehicle_type' => 'motorcycle', 'status' => 'active', 'shift_status' => 'on_shift',
            'cod_limit' => 10000, 'max_parcels' => 30, 'created_by' => $this->manager->id,
        ]);
    }

    /** The seller packs an order and hands it over: Pickrush books the parcel (the waybill). */
    private function handedOverOrder(User $buyer, float $amount): array
    {
        $product = Product::create(['seller_id' => $this->seller->id, 'name' => 'Canvas Tote', 'category' => 'Fashion', 'price' => $amount, 'stock' => 10, 'status' => 'active']);
        $order = Order::create([
            'order_number' => 'PS-E2E-'.uniqid(), 'product_id' => $product->id, 'buyer_id' => $buyer->id, 'seller_id' => $this->seller->id,
            'product_name' => 'Canvas Tote', 'quantity' => 1, 'amount' => $amount, 'commission' => round($amount * 0.1, 2), 'status' => 'preparing',
        ]);
        $this->actingAs($this->seller)->patch(config('app.url')."/seller/orders/{$order->id}/handover")->assertRedirect();

        return [$order->fresh(), PicksellBridge::parcelFor($order)];
    }

    private function app(Rider $rider): static
    {
        return $this->withHeaders(['Authorization' => 'Bearer '.RiderToken::issue($rider), 'Accept' => 'application/json']);
    }

    private function asStaff(Staff $staff): static
    {
        return $this->actingAs($staff, 'pickrush');
    }

    private function photo(): UploadedFile
    {
        return UploadedFile::fake()->create('proof.jpg', 120, 'image/jpeg');
    }

    /** What the buyer was told about this order, in order. */
    private function buyerHeard(User $buyer): array
    {
        return Notification::sent($buyer, BuyerOrderUpdate::class)
            ->map(fn ($n) => preg_replace('/ Tracking no\. \S+\.$/', '', $n->message))   // the message ends with the waybill
            ->values()->all();
    }

    // The journey -------------------------------------------------------------------------------

    public function test_same_city_orders_go_from_seller_to_buyers_with_a_failed_attempt_retried(): void
    {
        $ana = $this->user('buyer', 'City of Tanauan', 'Altura Bata');
        $ben = $this->user('buyer', 'City of Tanauan', 'Altura Matanda');
        [$anaOrder, $anaParcel] = $this->handedOverOrder($ana, 650);
        [$benOrder, $benParcel] = $this->handedOverOrder($ben, 1200);

        // 1. Seller handover: the waybill is the Pickrush tracking number; waiting for a rider.
        $this->assertSame('ready_for_pickup', $anaOrder->status);
        $this->assertSame($anaParcel->tracking_no, $anaOrder->waybill_number);
        $this->assertSame(ParcelStatus::WaitingPickup, $anaParcel->current_status);
        $this->assertEqualsWithDelta(14.09, (float) $anaParcel->pickup_lat, 1e-6, 'the shop pin travels with the parcel');

        // 2. The station finds the seller's pickups and sends them to Xtian as one batch.
        $xtian = $this->rider($this->tanauan, 'Xtian Sorro');
        $this->asStaff($this->dispatcher)->get($this->pickrushUrl('/station/dispatch?view=pickups&seller=tote'))
            ->assertOk()->assertSee('Seller pickups (2)');
        $this->asStaff($this->dispatcher)->post($this->pickrushUrl('/station/dispatch'), ['parcel_ids' => [$anaParcel->id, $benParcel->id], 'rider_id' => $xtian->id])
            ->assertSessionHasNoErrors();
        $pickup = RiderBatch::where('type', 'pickup')->sole();
        $rider = $this->app($xtian);

        // 3. Rider app: notified, accepts, arrives at the shop, scans both waybills.
        $rider->getJson('/api/rider/pulse')->assertJsonPath('offered_batch_ids', [$pickup->id]);
        $rider->postJson("/api/rider/batches/{$pickup->id}/accept")->assertOk();
        $stop = $rider->getJson("/api/rider/batches/{$pickup->id}")->json('batch.stops.0');
        $this->assertSame('Tote House', $stop['name']);
        $rider->postJson("/api/rider/batches/{$pickup->id}/stops/arrive", [
            'assignment_ids' => array_column($stop['items'], 'assignment_id'), 'lat' => 14.0901, 'lng' => 121.1500, 'accuracy' => 10,
        ])->assertOk();
        $rider->postJson("/api/rider/batches/{$pickup->id}/pickups", ['tracking_nos' => [$anaParcel->tracking_no, $benParcel->tracking_no], 'request_key' => 'pk1'])
            ->assertOk()->assertJsonPath('batch.picked_count', 2);
        $this->assertSame(['picked_up', 'picked_up'], [$anaOrder->fresh()->status, $benOrder->fresh()->status]);

        // 4. Back at the station: arrives at the hub, staff scan both in. Pickup batch done.
        $rider->postJson("/api/rider/batches/{$pickup->id}/hub/arrive", ['lat' => 14.0864, 'lng' => 121.1497])->assertOk();
        foreach ([$anaParcel, $benParcel] as $parcel) {
            $this->asStaff($this->dispatcher)->post($this->pickrushUrl('/station/receiving'), ['tracking_no' => $parcel->tracking_no])
                ->assertSessionHas('success', fn ($m) => str_contains($m, 'Same-city parcel'));
        }
        $this->assertSame('completed', $pickup->fresh()->status);
        $this->assertSame('at_sorting_center', $anaOrder->fresh()->status);

        // 5. Same city: straight to delivery. Both parcels go to Xtian as one delivery batch.
        $this->asStaff($this->dispatcher)->post($this->pickrushUrl('/station/dispatch'), ['parcel_ids' => [$anaParcel->id, $benParcel->id], 'rider_id' => $xtian->id])
            ->assertSessionHasNoErrors();
        $delivery = RiderBatch::where('type', 'delivery')->sole();
        $this->assertSame('assigned_to_rider', $anaOrder->fresh()->status);

        // 6. Rider app: accept, scan both onto the vehicle, start the route.
        $rider->postJson("/api/rider/batches/{$delivery->id}/accept")->assertOk();
        foreach ([$anaParcel, $benParcel] as $parcel) {
            $rider->postJson("/api/rider/batches/{$delivery->id}/load", ['tracking_no' => $parcel->tracking_no])->assertOk();
        }
        $rider->postJson("/api/rider/batches/{$delivery->id}/start", ['request_key' => 'go1'])->assertOk()
            ->assertJsonPath('batch.cod_total', fn ($v) => (float) $v === 1850.0);
        $this->assertSame(['out_for_delivery', 'out_for_delivery'], [$anaOrder->fresh()->status, $benOrder->fresh()->status]);

        // 7. Ana: delivered with COD and a photo. Ben: nobody home.
        $stops = collect($rider->getJson("/api/rider/batches/{$delivery->id}")->json('batch.stops'))->keyBy('name');
        $anaTask = $stops[$ana->full_name]['items'][0]['assignment_id'];
        $benTask = $stops[$ben->full_name]['items'][0]['assignment_id'];
        $rider->postJson("/api/rider/batches/{$delivery->id}/stops/arrive", ['assignment_ids' => [$anaTask], 'lat' => 14.0902, 'lng' => 121.1503])->assertOk();
        $rider->post("/api/rider/batches/{$delivery->id}/deliveries/{$anaTask}/deliver", [
            'tracking_no' => $anaParcel->tracking_no, 'cod_confirmed' => 650, 'photo' => $this->photo(), 'request_key' => 'd1',
        ])->assertOk();
        $rider->post("/api/rider/batches/{$delivery->id}/deliveries/{$benTask}/fail", [
            'reason' => 'buyer_unavailable', 'photo' => $this->photo(), 'request_key' => 'd1',
        ])->assertOk()->assertJsonPath('batch.to_remit', fn ($v) => (float) $v === 650.0);

        $this->assertSame('delivered', $anaOrder->fresh()->status);
        $this->assertSame(ParcelStatus::Delivered, $anaParcel->fresh()->current_status);
        $this->assertSame('delivery_failed', $benOrder->fresh()->status);

        // 8. Back at the station: Ben's parcel handed back, ₱650 counted. Delivery batch done.
        $rider->postJson("/api/rider/batches/{$delivery->id}/hub/arrive", ['lat' => 14.0863, 'lng' => 121.1497])->assertOk();
        $this->asStaff($this->dispatcher)->post($this->pickrushUrl('/station/receiving'), ['tracking_no' => $benParcel->tracking_no])
            ->assertSessionHas('success', fn ($m) => str_contains($m, 'handed back by Xtian Sorro'));
        $this->asStaff($this->manager)->post($this->pickrushUrl("/station/cash/riders/{$xtian->id}"), ['received_amount' => 650])
            ->assertSessionHasNoErrors();
        $rider->getJson("/api/rider/batches/{$delivery->id}")->assertJsonPath('batch.status', 'completed');

        // 9. Second attempt for Ben with another rider: delivered.
        $jay = $this->rider($this->tanauan, 'Jay Reyes');
        $this->asStaff($this->dispatcher)->post($this->pickrushUrl('/station/dispatch'), ['parcel_ids' => [$benParcel->id], 'rider_id' => $jay->id])
            ->assertSessionHasNoErrors();
        $retry = RiderBatch::where('rider_id', $jay->id)->sole();
        $jayApp = $this->app($jay);
        $jayApp->postJson("/api/rider/batches/{$retry->id}/accept")->assertOk();
        $jayApp->postJson("/api/rider/batches/{$retry->id}/load", ['tracking_no' => $benParcel->tracking_no])->assertOk();
        $jayApp->postJson("/api/rider/batches/{$retry->id}/start")->assertOk();
        $retryTask = $jayApp->getJson("/api/rider/batches/{$retry->id}")->json('batch.stops.0.items.0.assignment_id');
        $jayApp->post("/api/rider/batches/{$retry->id}/deliveries/{$retryTask}/deliver", [
            'tracking_no' => $benParcel->tracking_no, 'cod_confirmed' => 1200, 'photo' => $this->photo(),
        ])->assertOk();

        $this->assertSame('delivered', $benOrder->fresh()->status);
        $this->assertSame(1, $benParcel->fresh()->attempt_count, 'one failed attempt, then delivered');

        // 10. What people saw: the buyers' updates, the riders' day.
        $this->assertSame([
            'Your parcel was picked up from the seller.',
            'Your parcel is out for delivery today.',
            'Your parcel was delivered.',
        ], array_values(array_unique($this->buyerHeard($ana))));
        $this->assertContains('We could not deliver your parcel. Pickrush will contact you to try again.', $this->buyerHeard($ben));
        $this->assertSame('Your parcel was delivered.', last($this->buyerHeard($ben)));
        $this->app($xtian)->getJson('/api/rider/dashboard')->assertOk()     // signed in as Xtian again (Jay used the app last)
            ->assertJsonPath('today.pickups', 2)
            ->assertJsonPath('today.delivered', 1)
            ->assertJsonCount(0, 'batches');
    }

    public function test_an_order_to_another_city_travels_through_the_province_hub_to_the_buyers_station(): void
    {
        $sanJose = $this->station('041005000');
        $sanJose->update(['lat' => 13.8770, 'lng' => 121.1050]);
        $sanJoseManager = $this->staff(StaffRole::HubManager, $sanJose);
        $carla = $this->user('buyer', 'San Jose', 'Aguila');
        [$order, $parcel] = $this->handedOverOrder($carla, 980);
        $this->assertSame([$this->tanauan->id, $sanJose->id], [$parcel->origin_hub_id, $parcel->dest_hub_id]);

        // First mile in Tanauan.
        $xtian = $this->rider($this->tanauan, 'Xtian Sorro');
        $this->asStaff($this->dispatcher)->post($this->pickrushUrl('/station/dispatch'), ['parcel_ids' => [$parcel->id], 'rider_id' => $xtian->id]);
        $pickup = RiderBatch::sole();
        $rider = $this->app($xtian);
        $rider->postJson("/api/rider/batches/{$pickup->id}/accept")->assertOk();
        $rider->postJson("/api/rider/batches/{$pickup->id}/pickups", ['tracking_nos' => [$parcel->tracking_no]])->assertOk();
        $this->asStaff($this->dispatcher)->post($this->pickrushUrl('/station/receiving'), ['tracking_no' => $parcel->tracking_no])
            ->assertSessionHas('success', fn ($m) => str_contains($m, 'next trip to Batangas Province Hub'));

        // Middle mile: Tanauan → Batangas province hub → San Jose station.
        $manifests = app(ManifestService::class);
        $batangas = $this->tanauan->parent;
        $sorter = $this->staff(StaffRole::Sorter, $batangas);
        $this->asStaff($this->dispatcher);
        $up = $manifests->create($this->tanauan, $batangas, $this->dispatcher);
        $manifests->add($up, $parcel->tracking_no);
        $manifests->dispatch($up, 'ABC 123', 'Mang Jose', $this->dispatcher);
        $this->asStaff($sorter);
        $manifests->receive($up, $parcel->tracking_no, $sorter);
        $down = $manifests->create($batangas, $sanJose, $sorter);
        $manifests->add($down, $parcel->tracking_no);
        $manifests->dispatch($down, 'XYZ 789', 'Mang Pedro', $sorter);
        $this->asStaff($sanJoseManager)->post($this->pickrushUrl('/station/receiving'), ['tracking_no' => $parcel->tracking_no])
            ->assertSessionHasNoErrors();
        $this->assertSame('sorted', $order->fresh()->status, 'arrived at the buyer\'s station');
        // Logistics staff are not marketplace users: the order history names no person for Pickrush steps.
        $this->assertSame([null], OrderStatusHistory::where('order_id', $order->id)->where('source', 'pickrush')
            ->pluck('changed_by')->unique()->values()->all());

        // Last mile in San Jose with a San Jose rider.
        $lea = $this->rider($sanJose, 'Lea Santos');
        $this->asStaff($sanJoseManager)->post($this->pickrushUrl('/station/dispatch'), ['parcel_ids' => [$parcel->id], 'rider_id' => $lea->id])
            ->assertSessionHasNoErrors();
        $batch = RiderBatch::where('rider_id', $lea->id)->sole();
        $leaApp = $this->app($lea);
        $leaApp->postJson("/api/rider/batches/{$batch->id}/accept")->assertOk();
        $leaApp->postJson("/api/rider/batches/{$batch->id}/load", ['tracking_no' => $parcel->tracking_no])->assertOk();
        $leaApp->postJson("/api/rider/batches/{$batch->id}/start")->assertOk();
        $task = $leaApp->getJson("/api/rider/batches/{$batch->id}")->json('batch.stops.0.items.0.assignment_id');
        $leaApp->post("/api/rider/batches/{$batch->id}/deliveries/{$task}/deliver", [
            'tracking_no' => $parcel->tracking_no, 'cod_confirmed' => 980, 'photo' => $this->photo(),
        ])->assertOk();
        $leaApp->postJson("/api/rider/batches/{$batch->id}/hub/arrive", ['lat' => 13.8771, 'lng' => 121.1050])->assertOk();
        $this->asStaff($sanJoseManager)->post($this->pickrushUrl("/station/cash/riders/{$lea->id}"), ['received_amount' => 980])
            ->assertSessionHasNoErrors();

        $this->assertSame('delivered', $order->fresh()->status);
        $this->assertSame(ParcelStatus::Delivered, $parcel->fresh()->current_status);
        $leaApp->getJson("/api/rider/batches/{$batch->id}")->assertJsonPath('batch.status', 'completed');
        $this->assertSame('Your parcel was delivered.', last($this->buyerHeard($carla)));
    }
}
