<?php

namespace Tests\Feature\Pickrush;

use App\Models\BuyerAddress;
use App\Models\Order;
use App\Models\OrderShipment;
use App\Models\Product;
use App\Models\User;
use App\Notifications\BuyerOrderUpdate;
use App\Pickrush\Bridge\PicksellBridge;
use App\Pickrush\Enums\ParcelStatus;
use App\Pickrush\Enums\StaffRole;
use App\Pickrush\Models\Parcel;
use App\Pickrush\Services\ParcelLedger;
use App\Services\Orders\OrderAdministrationService;
use App\Support\DeliveryAddress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PicksellBridgeTest extends TestCase
{
    use BuildsNetwork, RefreshDatabase;

    private User $seller;

    private User $buyer;

    private User $admin;

    private ParcelLedger $ledger;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildNetwork();
        $this->ledger = app(ParcelLedger::class);
        $this->seller = $this->user('seller', 'Batangas', 'City of Tanauan', 'Altura Bata', ['business_name' => 'Tote House']);
        $this->buyer = $this->user('buyer', 'Metro Manila', 'Quezon City', 'Bagong Pag-asa');
        $this->admin = $this->user('admin', 'Batangas', 'City of Tanauan', 'Altura Bata');
    }

    private function user(string $role, string $province, string $municipality, string $barangay, array $extra = []): User
    {
        static $n = 0;
        $n++;

        return User::create(array_merge([
            'role' => $role, 'status' => 'approved', 'first_name' => ucfirst($role), 'last_name' => "Bridge{$n}", 'sex' => 'Female',
            'email' => "{$role}{$n}@bridge.test", 'password' => bcrypt('secret-password'), 'contact_no' => '09170000000',
            'birthday' => '1995-01-01', 'age' => 30, 'province' => $province, 'municipality' => $municipality, 'barangay' => $barangay,
            'house_no' => '12', 'street' => 'Rizal St',
        ], $extra));
    }

    private function preparing(?User $buyer = null): Order
    {
        $product = Product::create(['seller_id' => $this->seller->id, 'name' => 'Canvas Tote', 'category' => 'Fashion', 'price' => 600, 'stock' => 5, 'status' => 'active']);

        return Order::create([
            'order_number' => 'ORD-BR-'.uniqid(), 'product_id' => $product->id, 'buyer_id' => ($buyer ?? $this->buyer)->id,
            'seller_id' => $this->seller->id, 'product_name' => 'Canvas Tote', 'quantity' => 2, 'amount' => 1200, 'commission' => 120, 'status' => 'preparing',
        ]);
    }

    /** An order the seller has prepared, labelled and handed over, so Pickrush is waiting to pick it up. */
    private function handedOver(?User $buyer = null): array
    {
        return $this->handOver($this->preparing($buyer));
    }

    private function handOver(Order $order): array
    {
        $this->actingAs($this->seller)->post(config('app.url')."/seller/orders/{$order->id}/waybill")->assertRedirect();
        $this->actingAs($this->seller)->patch(config('app.url')."/seller/orders/{$order->id}/handover")->assertRedirect();

        return [$order->fresh(), PicksellBridge::parcelFor($order)];
    }

    public function test_generating_the_waybill_does_not_call_a_rider_until_the_seller_hands_over(): void
    {
        Notification::fake();
        $order = $this->preparing();
        $tanauan = $this->station('041029000');

        $this->actingAs($this->seller)->patch(config('app.url')."/seller/orders/{$order->id}/handover")->assertStatus(422);
        $this->assertNull(PicksellBridge::parcelFor($order), 'no waybill, no handover');

        $this->actingAs($this->seller)->post(config('app.url')."/seller/orders/{$order->id}/waybill")->assertSessionHasNoErrors();
        $this->actingAs($this->seller)->post(config('app.url')."/seller/orders/{$order->id}/waybill")->assertRedirect();
        $parcel = PicksellBridge::parcelFor($order);
        $this->assertSame(1, Parcel::withoutGlobalScopes()->count(), 'generating twice reuses the waybill');
        $this->assertSame(ParcelStatus::LabelCreated, $parcel->current_status);
        $this->assertSame(['preparing', $parcel->tracking_no], [$order->fresh()->status, $order->fresh()->waybill_number]);
        $this->assertSame(0, Parcel::where('origin_hub_id', $tanauan->id)->where('current_status', ParcelStatus::WaitingPickup)->count(), 'not in the station pickup queue');
        $this->assertSame(0, $this->buyer->notifications()->count());
        $this->actingAs($this->seller)->get(config('app.url')."/seller/orders/{$order->id}/waybill")->assertOk();
        $this->actingAs($this->seller)->get(config('app.url')."/seller/orders/{$order->id}")->assertOk()->assertSee('Hand Over to Pickrush');

        $this->actingAs($this->seller)->patch(config('app.url')."/seller/orders/{$order->id}/handover")->assertSessionHasNoErrors();
        $this->assertSame(ParcelStatus::WaitingPickup, $parcel->fresh()->current_status);
        $this->assertSame('ready_for_pickup', $order->fresh()->status);
        $this->assertSame(1, $this->buyer->notifications()->count(), 'the buyer hears about the handover only');
    }

    public function test_sellers_generate_and_print_several_waybills_at_once(): void
    {
        $first = $this->preparing();
        $second = $this->preparing();

        $this->actingAs($this->seller)->post(config('app.url').'/seller/orders/bulk', ['action' => 'waybills', 'order_ids' => [$first->id, $second->id]])
            ->assertSessionHas('success', '2 waybills generated.');
        $this->assertNotNull(PicksellBridge::parcelFor($first));
        $this->assertNotNull(PicksellBridge::parcelFor($second));

        // Again: already done, so skipped. Printing gives one PDF with both labels.
        $this->actingAs($this->seller)->post(config('app.url').'/seller/orders/bulk', ['action' => 'waybills', 'order_ids' => [$first->id]])
            ->assertSessionHas('success', '0 waybills generated. 1 skipped: not at that step yet or already done.');
        $pdf = $this->actingAs($this->seller)->post(config('app.url').'/seller/orders/bulk', ['action' => 'print', 'order_ids' => [$first->id, $second->id]]);
        $pdf->assertOk();
        $this->assertSame('application/pdf', $pdf->headers->get('content-type'));
    }

    public function test_confirming_an_order_generates_its_waybill_without_calling_a_rider(): void
    {
        $order = $this->preparing();
        $order->update(['status' => 'placed']);

        $this->actingAs($this->seller)->patch(config('app.url')."/seller/orders/{$order->id}/pack")
            ->assertSessionHasNoErrors()->assertSessionHas('success', 'Order confirmed. Waybill generated.');
        $parcel = PicksellBridge::parcelFor($order);
        $this->assertSame(ParcelStatus::LabelCreated, $parcel->current_status);
        $this->assertSame(['preparing', $parcel->tracking_no], [$order->fresh()->status, $order->fresh()->waybill_number]);
        $this->actingAs($this->seller)->get(config('app.url').'/seller/orders')->assertOk()
            ->assertSee('data-bulk-order="print handover"', false)->assertDontSee('>Generate Waybill<', false);
    }

    public function test_a_failed_waybill_still_confirms_and_leaves_generate_waybill_as_the_fallback(): void
    {
        $order = $this->preparing();
        $order->update(['status' => 'placed']);
        $this->mock(PicksellBridge::class)->shouldReceive('book')->andThrow(new \RuntimeException('Pickrush is down'));

        $this->actingAs($this->seller)->patch(config('app.url')."/seller/orders/{$order->id}/pack")
            ->assertSessionHas('success', 'Order confirmed. Waybill not generated.');
        $this->assertSame(['preparing', null], [$order->fresh()->status, $order->fresh()->waybill_number]);
        $this->actingAs($this->seller)->get(config('app.url').'/seller/orders')->assertOk()
            ->assertSee('data-bulk-order="waybills"', false)->assertSee('Generate Waybill');
    }

    public function test_sellers_confirm_and_hand_over_several_orders_at_once(): void
    {
        $first = $this->preparing();
        $second = $this->preparing();
        $unlabelled = $this->preparing();
        Order::whereIn('id', [$first->id, $second->id])->update(['status' => 'placed']);

        $this->actingAs($this->seller)->post(config('app.url').'/seller/orders/bulk', ['action' => 'confirm', 'order_ids' => [$first->id, $second->id]])
            ->assertSessionHas('success', '2 orders confirmed. 2 waybills generated.');
        $this->assertNotNull(PicksellBridge::parcelFor($first));
        $this->assertNotNull(PicksellBridge::parcelFor($second));

        $this->actingAs($this->seller)->post(config('app.url').'/seller/orders/bulk', ['action' => 'handover', 'order_ids' => [$first->id, $second->id, $unlabelled->id]])
            ->assertSessionHas('success', '2 parcels handed over for pickup. 1 skipped: not at that step yet or already done.');
        $this->assertSame(['ready_for_pickup', 'ready_for_pickup', 'preparing'], [$first->fresh()->status, $second->fresh()->status, $unlabelled->fresh()->status]);
        $this->assertSame(ParcelStatus::WaitingPickup, PicksellBridge::parcelFor($first)->current_status);
        $this->assertSame(2, $this->buyer->notifications()->count());
    }

    public function test_a_labelled_order_can_still_be_cancelled_before_handover(): void
    {
        $order = $this->preparing();
        $this->actingAs($this->seller)->post(config('app.url')."/seller/orders/{$order->id}/waybill");

        $this->actingAs($this->seller)->patch(config('app.url')."/seller/orders/{$order->id}/cancel", ['reason' => 'Out of stock'])->assertSessionHasNoErrors();
        $this->assertSame(['cancelled', ParcelStatus::Cancelled], [$order->fresh()->status, PicksellBridge::parcelFor($order)->current_status]);
    }

    private function scan(Parcel $parcel, ParcelStatus $status, string $municipalityCode, ?string $toCode = null, string $type = 'station'): void
    {
        $hub = $type === 'province' ? $this->station($municipalityCode)->parent : $this->station($municipalityCode);
        $to = $toCode ? ($toCode === 'parent' ? $hub->parent : $this->station($toCode)) : null;
        $this->ledger->record($parcel, $status, $hub, ['to_hub' => $to]);
    }

    public function test_handover_books_a_pickrush_parcel_whose_tracking_number_is_the_waybill(): void
    {
        [$order, $parcel] = $this->handedOver();

        $this->assertSame('ready_for_pickup', $order->status);
        $this->assertSame($parcel->tracking_no, $order->waybill_number);
        $this->assertSame(['Tanauan City Station', 'Quezon City Station'], [$parcel->originHub->name, $parcel->destHub->name]);
        $this->assertSame(['Tote House', 'Buyer '.$this->buyer->last_name], [$parcel->shipper_name, $parcel->consignee_name]);
        $this->assertSame('1200.00', $parcel->cod_amount);
        $this->assertSame('Canvas Tote × 2', $parcel->item_description);
        $this->assertSame('12, Rizal St, Bagong Pag-asa, Quezon City, Metro Manila', $parcel->delivery_address);
        $this->assertFalse($parcel->needs_address_fix);
        $this->assertSame(1, Parcel::withoutGlobalScopes()->count(), 'one parcel per order');
    }

    public function test_the_seller_shop_pin_follows_parcels_still_waiting_for_a_rider(): void
    {
        $this->seller->update(['pickup_lat' => 14.0900, 'pickup_lng' => 121.1500]);
        [, $booked] = $this->handedOver();
        $this->assertEqualsWithDelta(14.09, (float) $booked->pickup_lat, 1e-6, 'booking copies the shop pin');

        [, $collected] = $this->handedOver();
        $this->scan($collected, ParcelStatus::PickedUp, '041029000');

        $this->actingAs($this->seller)->patch(config('app.url').'/seller/account/pickup-location', ['lat' => 14.0950, 'lng' => 121.1550])
            ->assertSessionHasNoErrors();
        $this->assertEqualsWithDelta(14.095, (float) $booked->fresh()->pickup_lat, 1e-6);
        $this->assertEqualsWithDelta(14.09, (float) $collected->fresh()->pickup_lat, 1e-6, 'a parcel already collected keeps where it was picked up');

        $this->actingAs($this->seller)->patch(config('app.url').'/seller/account/pickup-location', ['lat' => 40.7, 'lng' => -74.0])
            ->assertSessionHasErrors('lat');
        $this->actingAs($this->seller)->get(config('app.url').'/seller/account')->assertOk()->assertSee('Shop Pin for Pickups');
    }

    public function test_the_buyer_delivery_pin_follows_parcels_not_yet_delivered(): void
    {
        $this->buyer->update(['delivery_lat' => 14.6500, 'delivery_lng' => 121.0300]);
        [, $onTheWay] = $this->handedOver();
        $this->assertEqualsWithDelta(14.65, (float) $onTheWay->delivery_lat, 1e-6, 'booking copies the delivery pin');

        [, $delivered] = $this->handedOver();
        $delivered->forceFill(['current_status' => ParcelStatus::Delivered])->save();

        $this->actingAs($this->buyer)->patch(config('app.url').'/buyer/account/delivery-location', ['lat' => 14.6600, 'lng' => 121.0400])
            ->assertSessionHasNoErrors();
        $this->assertEqualsWithDelta(14.66, (float) $this->buyer->fresh()->delivery_lat, 1e-6);
        $this->assertEqualsWithDelta(14.66, (float) $onTheWay->fresh()->delivery_lat, 1e-6);
        $this->assertEqualsWithDelta(14.65, (float) $delivered->fresh()->delivery_lat, 1e-6, 'a delivered parcel keeps where it was delivered');

        $this->actingAs($this->buyer)->patch(config('app.url').'/buyer/account/delivery-location', ['lat' => 40.7, 'lng' => -74.0])
            ->assertSessionHasErrors('lat');
        $this->actingAs($this->buyer)->get(config('app.url').'/buyer/account')->assertOk()->assertSee('Delivery Pin')->assertSee('data-map-pin="buyer-pin"', false);

        // The buyer app saves it the same way and says how many parcels moved.
        \Laravel\Sanctum\Sanctum::actingAs($this->buyer->fresh());
        $this->patchJson('/api/mobile/buyer/delivery-location', ['lat' => 14.6700, 'lng' => 121.0500])->assertOk()
            ->assertJsonPath('parcels_updated', 1)->assertJsonPath('delivery_address.pin', ['lat' => 14.67, 'lng' => 121.05]);
        $this->assertEqualsWithDelta(14.67, (float) $onTheWay->fresh()->delivery_lat, 1e-6);
        $this->assertEqualsWithDelta(14.65, (float) $delivered->fresh()->delivery_lat, 1e-6);
    }

    public function test_a_parcel_goes_to_the_address_chosen_at_checkout_and_keeps_its_own_pin(): void
    {
        $this->buyer->update(['delivery_lat' => 14.6500, 'delivery_lng' => 121.0300]);
        $office = BuyerAddress::create(['user_id' => $this->buyer->id, 'label' => 'Office', 'recipient_name' => 'Ana Cruz', 'contact_no' => '09175550101',
            'province' => 'Batangas', 'municipality' => 'City of Tanauan', 'barangay' => 'Altura Matanda', 'street' => 'Mabini St', 'house_no' => '7']);
        $order = $this->preparing();
        $shipment = OrderShipment::create(['reference' => 'SHP-OFFICE01', 'buyer_id' => $this->buyer->id, 'seller_id' => $this->seller->id,
            'destination_region' => 'S_LUZON', 'price_source' => 'GENERIC_FALLBACK', 'parcel_class' => 'STANDARD', 'chargeable_weight_kg' => 1,
            'delivery_fee' => 100, 'rider_pay' => 50, 'platform_commission' => 10, 'logistics_share' => 40, 'delivery_commission_rate' => 10,
            'rates_version' => 'test'] + DeliveryAddress::fromBook($office)->shipmentColumns());
        $order->update(['order_shipment_id' => $shipment->id]);

        [, $parcel] = $this->handOver($order);
        $this->assertSame(['Ana Cruz', '09175550101', '7, Mabini St, Altura Matanda, City of Tanauan, Batangas'],
            [$parcel->consignee_name, $parcel->consignee_mobile, $parcel->delivery_address]);
        $this->assertSame('041029000', $parcel->delivery_municipality_code, 'routed to the office, not the Quezon City profile');
        $this->assertNull($parcel->delivery_lat, 'the home pin is not the office');

        // Moving the home pin leaves the office parcel alone.
        $this->actingAs($this->buyer)->patch(config('app.url').'/buyer/account/delivery-location', ['lat' => 14.6600, 'lng' => 121.0400])->assertSessionHasNoErrors();
        $this->assertNull($parcel->fresh()->delivery_lat);
    }

    public function test_every_hub_scan_reaches_the_order_and_the_buyer(): void
    {
        Notification::fake();
        [$order, $parcel] = $this->handedOver();

        $this->scan($parcel, ParcelStatus::PickedUp, '041029000');
        $this->assertSame('picked_up', $order->fresh()->status);

        $this->scan($parcel, ParcelStatus::AtOriginHub, '041029000');
        $this->assertSame('at_sorting_center', $order->fresh()->status);

        $this->scan($parcel, ParcelStatus::InTransitToProvince, '041029000', 'parent');
        $this->scan($parcel, ParcelStatus::AtProvinceHub, '041029000', null, 'province');
        $this->assertSame(['at_sorting_center', 'Arrived at Batangas Province Hub'], [$order->fresh()->status, $order->fresh()->tracking_status],
            'passing a hub updates what the buyer sees without changing the order status');

        $eastern = $this->station('137404000')->parent;
        $this->ledger->record($parcel, ParcelStatus::InTransitToProvince, $this->station('041029000')->parent, ['to_hub' => $eastern]);
        $this->ledger->record($parcel, ParcelStatus::AtProvinceHub, $eastern);
        $this->ledger->record($parcel, ParcelStatus::InTransitToStation, $eastern, ['to_hub' => $this->station('137404000')]);
        $this->scan($parcel, ParcelStatus::ArrivedAtStation, '137404000');
        $this->assertSame('sorted', $order->fresh()->status);

        $this->scan($parcel, ParcelStatus::AssignedToRider, '137404000');
        $this->scan($parcel, ParcelStatus::OutForDelivery, '137404000');
        $this->scan($parcel, ParcelStatus::Delivered, '137404000');

        $order->refresh();
        $this->assertSame('delivered', $order->status);
        $this->assertNotNull($order->delivered_at);
        $this->assertSame(
            ['picked_up', 'at_sorting_center', 'sorted', 'assigned_to_rider', 'out_for_delivery', 'delivered'],
            $order->statusHistories()->where('source', 'pickrush')->pluck('to_status')->all(),
        );
        Notification::assertSentToTimes($this->buyer, BuyerOrderUpdate::class, 4); // picked up, at your station, out for delivery, delivered

        // The buyer confirms receipt in the marketplace ("Order Received").
        $this->actingAs($this->buyer)->patch(config('app.url')."/buyer/orders/{$order->id}/received")->assertRedirect();
        $this->assertSame('completed', $order->fresh()->status);
    }

    public function test_scans_by_signed_in_pickrush_staff_still_move_the_order(): void
    {
        [$order, $parcel] = $this->handedOver();
        // Staff ids are not marketplace user ids; one with no matching user used to break the history FK.
        $staff = $this->staff(StaffRole::HubManager, $this->station('041029000'));
        $staff->forceFill(['id' => 9999])->save();
        $this->actingAs($staff->fresh(), 'pickrush');

        $this->scan($parcel, ParcelStatus::PickedUp, '041029000');
        $this->scan($parcel, ParcelStatus::AtOriginHub, '041029000');

        $this->assertSame('at_sorting_center', $order->fresh()->status);
        $this->assertNull($order->statusHistories()->where('source', 'pickrush')->latest('id')->value('changed_by'));
    }

    public function test_drop_off_sends_no_rider_and_the_station_scan_receives_it(): void
    {
        $order = $this->preparing();
        $this->actingAs($this->seller)->post(config('app.url')."/seller/orders/{$order->id}/waybill")->assertRedirect();
        $this->actingAs($this->seller)->patch(config('app.url')."/seller/orders/{$order->id}/handover", ['method' => 'dropoff'])->assertSessionHasNoErrors();

        $order->refresh();
        $parcel = PicksellBridge::parcelFor($order);
        $this->assertSame(ParcelStatus::AwaitingDropoff, $parcel->current_status);
        $this->assertSame(['ready_for_pickup', 'dropoff'], [$order->status, $order->fulfillment]);
        $this->assertTrue($order->dropoff_by->between(now()->addDays(2)->subMinute(), now()->addDays(2)->addMinute()));

        $staff = $this->staff(StaffRole::HubManager, $parcel->originHub);
        $message = app(\App\Pickrush\Services\Station\StationReceiving::class)->scan($parcel->tracking_no, $staff);
        $this->assertStringContainsString('received', $message);
        $this->assertSame(ParcelStatus::AtOriginHub, $parcel->fresh()->current_status);
        $this->assertSame('at_sorting_center', $order->fresh()->status);
    }

    public function test_sellers_see_the_drop_off_choice_and_then_where_and_by_when(): void
    {
        $order = $this->preparing();
        $this->actingAs($this->seller)->post(config('app.url')."/seller/orders/{$order->id}/waybill")->assertRedirect();
        $this->actingAs($this->seller)->get(config('app.url')."/seller/orders/{$order->id}")->assertOk()->assertSee('Drop Off at Station');
        $this->actingAs($this->seller)->get(config('app.url').'/seller/orders')->assertOk()->assertSee('value="dropoff"', false);

        $this->actingAs($this->seller)->patch(config('app.url')."/seller/orders/{$order->id}/handover", ['method' => 'dropoff']);
        $station = PicksellBridge::parcelFor($order)->originHub;
        $this->actingAs($this->seller)->get(config('app.url')."/seller/orders/{$order->id}")->assertOk()
            ->assertSee("at {$station->name}", false)->assertDontSee('Drop Off at Station');
        $this->actingAs($this->seller)->get(config('app.url').'/seller/orders')->assertOk()->assertSee('Drop off by');
    }

    public function test_a_bulk_drop_off_gets_one_manifest_the_station_checks_and_confirms(): void
    {
        $orders = collect([$this->preparing(), $this->preparing(), $this->preparing()]);
        $orders->each(fn (Order $o) => $this->actingAs($this->seller)->post(config('app.url')."/seller/orders/{$o->id}/waybill"));
        $response = $this->actingAs($this->seller)->post(config('app.url').'/seller/orders/bulk', ['action' => 'dropoff', 'order_ids' => $orders->pluck('id')->all()]);

        $batch = \App\Pickrush\Models\DropoffBatch::sole();
        $response->assertRedirect(route('seller.dropoffs.show', $batch));
        $this->assertSame(3, $batch->parcel_count);
        $this->actingAs($this->seller)->get(route('seller.dropoffs.show', $batch))->assertOk()->assertSee($batch->batch_no)->assertSee('To drop off');
        $this->actingAs($this->seller)->get(route('seller.dropoffs.print', $batch))->assertOk();

        // The counter: the manifest code opens the batch, two of three parcels are scanned, then confirmed.
        $staff = $this->staff(StaffRole::HubManager, $batch->originHub);
        $this->actingAs($staff, 'pickrush');
        $this->post(route('pickrush.station.receiving.scan'), ['tracking_no' => strtolower($batch->batch_no)])
            ->assertRedirect(route('pickrush.station.dropoffs.show', $batch));
        $parcels = $batch->parcels()->get();
        foreach ($parcels->take(2) as $parcel) {
            $this->post(route('pickrush.station.dropoffs.scan', $batch), ['tracking_no' => $parcel->tracking_no])->assertSessionHasNoErrors();
        }
        $this->get(route('pickrush.station.dropoffs.show', $batch))->assertOk()->assertSee('2 of 3 received');
        $this->post(route('pickrush.station.dropoffs.confirm', $batch))->assertSessionHasNoErrors();

        $batch->refresh();
        $this->assertSame(['received', $staff->id], [$batch->status, $batch->received_by]);
        $this->assertSame(ParcelStatus::AwaitingDropoff, $parcels->last()->fresh()->current_status, 'the missing one keeps waiting');
        $receipt = $this->seller->notifications()->where('type', \App\Notifications\SellerDropoffReceipt::class)->sole();
        $this->assertStringContainsString('received 2 of 3', $receipt->data['message']);
        $this->post(route('pickrush.station.dropoffs.confirm', $batch))->assertSessionHasErrors('operation');
    }

    public function test_a_drop_off_not_brought_in_within_two_days_is_cancelled(): void
    {
        $order = $this->preparing();
        $this->actingAs($this->seller)->post(config('app.url')."/seller/orders/{$order->id}/waybill")->assertRedirect();
        $this->actingAs($this->seller)->post(config('app.url').'/seller/orders/bulk', ['action' => 'dropoff', 'order_ids' => [$order->id]])->assertSessionHasNoErrors();

        $this->travel(47)->hours();
        app(\App\Services\Orders\ShippingDeadlines::class)->run();
        $this->assertSame('ready_for_pickup', $order->fresh()->status, 'still inside the 2 days');

        $this->travel(2)->hours();
        $result = app(\App\Services\Orders\ShippingDeadlines::class)->run();
        $this->assertSame(0, $result['pickup_issues'], 'a drop-off never blames the courier');
        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertNotNull($order->fresh()->late_cancelled_at);
        $this->assertSame(ParcelStatus::Cancelled, PicksellBridge::parcelFor($order)->fresh()->current_status);
    }

    public function test_walk_in_and_same_city_parcels_skip_steps_they_never_had(): void
    {
        $neighbour = $this->user('buyer', 'Batangas', 'City of Tanauan', 'Altura Bata');
        [$order, $parcel] = $this->handedOver($neighbour);

        $this->scan($parcel, ParcelStatus::AtOriginHub, '041029000');      // seller walked in
        $this->assertSame('at_sorting_center', $order->fresh()->status);
        $this->scan($parcel, ParcelStatus::AssignedToRider, '041029000');  // same station delivers it
        $this->assertSame('assigned_to_rider', $order->fresh()->status);

        $this->assertSame(['ready_for_pickup', 'picked_up', 'at_sorting_center', 'sorted', 'assigned_to_rider'],
            $order->statusHistories()->pluck('to_status')->slice(-5)->values()->all());
    }

    public function test_a_failed_attempt_can_be_retried_or_returned_to_the_seller(): void
    {
        [$order, $parcel] = $this->handedOver($this->user('buyer', 'Batangas', 'City of Tanauan', 'Altura Bata'));
        foreach ([ParcelStatus::AtOriginHub, ParcelStatus::AssignedToRider, ParcelStatus::OutForDelivery, ParcelStatus::DeliveryFailed] as $status) {
            $this->scan($parcel, $status, '041029000');
        }
        $this->assertSame('delivery_failed', $order->fresh()->status);

        $this->scan($parcel, ParcelStatus::AssignedToRider, '041029000');  // second attempt
        $this->assertSame('assigned_to_rider', $order->fresh()->status);
        $this->scan($parcel, ParcelStatus::OutForDelivery, '041029000');
        $this->scan($parcel, ParcelStatus::DeliveryFailed, '041029000');
        $this->scan($parcel, ParcelStatus::ReturningToSender, '041029000');
        $this->assertSame('delivery_failed', $order->fresh()->status);
        $this->scan($parcel, ParcelStatus::ReturnedToSender, '041029000');

        $this->assertSame('returned', $order->fresh()->status);
        $this->assertSame(7, Product::find($order->product_id)->stock, 'the 2 returned units go back into stock (this order skipped checkout, so stock started at 5)');
    }

    public function test_admin_cannot_override_delivery_statuses_but_cancelling_cancels_the_parcel(): void
    {
        [$order, $parcel] = $this->handedOver();

        $this->assertNotContains('picked_up', OrderAdministrationService::overrideTargets($order));
        $this->actingAs($this->admin)->patch(config('app.url')."/admin/orders/{$order->id}/status", ['status' => 'picked_up', 'reason' => 'Rider says it was collected'])
            ->assertForbidden();

        $this->actingAs($this->admin)->patch(config('app.url')."/admin/orders/{$order->id}/resolve", ['action' => 'cancel_order', 'reason' => 'Buyer asked to cancel before pickup'])
            ->assertSessionHasNoErrors();
        $this->assertSame(['cancelled', ParcelStatus::Cancelled], [$order->fresh()->status, $parcel->fresh()->current_status]);
    }

    public function test_an_order_cannot_be_cancelled_once_pickrush_has_the_parcel(): void
    {
        [$order, $parcel] = $this->handedOver();
        $this->scan($parcel, ParcelStatus::AtOriginHub, '041029000');
        Order::whereKey($order->id)->update(['status' => 'ready_for_pickup']); // simulate a stale marketplace view

        $this->actingAs($this->admin)->patch(config('app.url')."/admin/orders/{$order->id}/resolve", ['action' => 'cancel_order', 'reason' => 'Buyer asked to cancel after pickup'])
            ->assertStatus(409);
        $this->assertSame(ParcelStatus::AtOriginHub, $parcel->fresh()->current_status);
    }

    public function test_a_scan_the_order_cannot_follow_is_kept_and_flagged_to_admins(): void
    {
        [$order, $parcel] = $this->handedOver();
        Order::whereKey($order->id)->update(['status' => 'cancelled']);

        $this->scan($parcel, ParcelStatus::PickedUp, '041029000');

        $this->assertSame(ParcelStatus::PickedUp, $parcel->fresh()->current_status, 'the scan is never lost');
        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertTrue($this->admin->notifications()->get()->contains(fn ($n) => str_contains(json_encode($n->data), 'cannot follow Pickrush')));
    }

    public function test_buyer_seller_and_admin_pages_show_the_hub_by_hub_route(): void
    {
        [$order, $parcel] = $this->handedOver();
        $this->scan($parcel, ParcelStatus::AtOriginHub, '041029000');
        $this->scan($parcel, ParcelStatus::InTransitToProvince, '041029000', 'parent');

        $this->actingAs($this->buyer)->get(config('app.url').'/buyer/orders')->assertOk()
            ->assertSee($parcel->tracking_no)->assertSee('Departed Tanauan City Station → Batangas Province Hub')->assertSee('Arrived at Tanauan City Station');
        $this->actingAs($this->seller)->get(config('app.url')."/seller/orders/{$order->id}")->assertOk()->assertSee('Departed Tanauan City Station');
        $this->actingAs($this->admin)->get(config('app.url')."/admin/orders/{$order->id}")->assertOk()->assertSee('Arrived at Tanauan City Station');
    }

    public function test_buyer_tracking_page_lists_pickrush_events_oldest_first(): void
    {
        [$order, $parcel] = $this->handedOver();
        $this->scan($parcel, ParcelStatus::AtOriginHub, '041029000');
        $this->scan($parcel, ParcelStatus::InTransitToProvince, '041029000', 'parent');

        $response = $this->actingAs($this->buyer)->get(config('app.url')."/buyer/orders/{$order->id}/tracking")->assertOk()
            ->assertSee($parcel->tracking_no)
            ->assertSeeInOrder(['Arrived at Tanauan City Station', 'Departed Tanauan City Station → Batangas Province Hub']);
        $this->assertMatchesRegularExpression('~<li class="tracking-event is-latest"[^>]*>.*?Departed Tanauan City Station~s', $response->getContent());
    }

    public function test_public_tracking_shows_the_route_without_personal_details(): void
    {
        [, $parcel] = $this->handedOver();
        $this->scan($parcel, ParcelStatus::AtOriginHub, '041029000');

        $this->get($this->pickrushUrl('/track?no='.strtolower($parcel->tracking_no)))->assertOk()
            ->assertSee('Arrived at Tanauan City Station')->assertSee('Quezon City, Metro Manila')
            ->assertDontSee($this->buyer->last_name)->assertDontSee('Rizal St')->assertDontSee('09170000000');
        $this->get($this->pickrushUrl('/track?no=PR0000000000000X'))->assertOk()->assertSee('We could not find that tracking number');
    }
}
