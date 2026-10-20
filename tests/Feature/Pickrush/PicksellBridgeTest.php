<?php

namespace Tests\Feature\Pickrush;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Notifications\BuyerOrderUpdate;
use App\Pickrush\Bridge\PicksellBridge;
use App\Pickrush\Enums\ParcelStatus;
use App\Pickrush\Models\Parcel;
use App\Pickrush\Services\ParcelLedger;
use App\Services\Orders\OrderAdministrationService;
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

    /** An order the seller has prepared and handed over, so Pickrush has booked its parcel. */
    private function handedOver(?User $buyer = null): array
    {
        $product = Product::create(['seller_id' => $this->seller->id, 'name' => 'Canvas Tote', 'category' => 'Fashion', 'price' => 600, 'stock' => 5, 'status' => 'active']);
        $order = Order::create([
            'order_number' => 'ORD-BR-'.uniqid(), 'product_id' => $product->id, 'buyer_id' => ($buyer ?? $this->buyer)->id,
            'seller_id' => $this->seller->id, 'product_name' => 'Canvas Tote', 'quantity' => 2, 'amount' => 1200, 'commission' => 120, 'status' => 'preparing',
        ]);
        $this->actingAs($this->seller)->patch(config('app.url')."/seller/orders/{$order->id}/handover")->assertRedirect();

        return [$order->fresh(), PicksellBridge::parcelFor($order)];
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

        // The seller still confirms receipt in the marketplace.
        $this->actingAs($this->seller)->patch(config('app.url')."/seller/orders/{$order->id}/confirm-delivery")->assertRedirect();
        $this->assertSame('completed', $order->fresh()->status);
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
        $this->assertTrue($this->admin->notifications()->get()->contains(fn ($n) => str_contains($n->data, 'cannot follow Pickrush')));
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
