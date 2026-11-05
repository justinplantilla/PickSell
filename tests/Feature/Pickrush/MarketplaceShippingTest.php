<?php

namespace Tests\Feature\Pickrush;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\OrderShipment;
use App\Models\Product;
use App\Models\User;
use App\Pickrush\Bridge\PicksellBridge;
use App\Pickrush\Enums\ParcelStatus;
use App\Pickrush\Enums\StaffRole;
use App\Pickrush\Models\Hub;
use App\Pickrush\Models\ParcelClass;
use App\Pickrush\Models\Rider;
use App\Pickrush\Models\RiderToken;
use App\Pickrush\Models\ShippingRate;
use App\Pickrush\Models\Staff;
use App\Pickrush\Services\ParcelLedger;
use App\Pickrush\Services\ParcelStatusChanged;
use App\Pickrush\Services\Rider\RiderEarnings;
use App\Pickrush\Services\Station\DispatchService;
use App\Pickrush\Services\Station\RiderTaskService;
use App\Services\DeliveryFeeSplit;
use App\Services\Finance\ShipmentSettlement;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * PickSell checkout to Pickrush delivery: one parcel and fee per seller, the fee's split saved at
 * placement, rider pay by parcel class, and money credited once on delivery and never on failure.
 */
class MarketplaceShippingTest extends TestCase
{
    use BuildsNetwork, RefreshDatabase;

    private User $seller;

    private User $buyer;

    private Hub $tanauan;

    private Staff $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildNetwork();
        $this->tanauan = $this->station('041029000');
        $this->manager = $this->staff(StaffRole::HubManager, $this->tanauan);
        $this->seller = $this->user('seller', 'Batangas', 'City of Tanauan', ['business_name' => 'Tote House']);
        $this->buyer = $this->user('buyer', 'Batangas', 'City of Tanauan');           // LUZON, same-city delivery
        $this->user('admin', 'Batangas', 'City of Tanauan');
    }

    private function user(string $role, string $province, string $municipality, array $extra = []): User
    {
        static $n = 0;
        $n++;

        return User::create(array_merge([
            'role' => $role, 'status' => 'approved', 'first_name' => ucfirst($role), 'last_name' => "Ship{$n}", 'sex' => 'Female',
            'email' => "{$role}{$n}@ship.test", 'password' => bcrypt('secret-password'), 'contact_no' => '09170000000',
            'birthday' => '1995-01-01', 'age' => 30, 'province' => $province, 'municipality' => $municipality, 'barangay' => 'Altura Bata',
            'house_no' => '12', 'street' => 'Rizal St',
        ], $extra));
    }

    private function product(array $extra = []): Product
    {
        return Product::create($extra + [
            'seller_id' => $this->seller->id, 'name' => 'Canvas Tote', 'category' => 'Fashion',
            'price' => 500, 'stock' => 10, 'status' => 'active', 'weight_kg' => 1,
        ]);
    }

    /** @param  array<int, array{0: Product, 1: int}>  $lines */
    private function cart(User $buyer, array $lines): array
    {
        $cart = Cart::firstOrCreate(['buyer_id' => $buyer->id]);

        return array_map(fn ($line) => CartItem::create(['cart_id' => $cart->id, 'product_id' => $line[0]->id, 'quantity' => $line[1]])->id, $lines);
    }

    private function checkout(User $buyer, array $itemIds)
    {
        return $this->actingAs($buyer)->post(config('app.url').'/buyer/cart/checkout', ['item_ids' => $itemIds]);
    }

    private function rider(): Rider
    {
        static $n = 0;
        $n++;

        $rider = Rider::withoutGlobalScopes()->create([
            'station_hub_id' => $this->tanauan->id, 'full_name' => "Rider {$n}", 'mobile_no' => '0918'.str_pad((string) $n, 7, '0', STR_PAD_LEFT),
            'vehicle_type' => 'motorcycle', 'status' => 'active', 'shift_status' => 'on_shift', 'last_seen_at' => now(),
            'cod_limit' => 100000, 'max_parcels' => 30, 'created_by' => $this->manager->id,
        ]);
        RiderToken::issue($rider);   // logged in to the rider app

        return $rider;
    }

    // The split -------------------------------------------------------------------------------

    public static function splits(): array
    {
        return [
            'NCR 0.5 kg' => ['60.00', '15.00', '45.00', '9.00', '36.00'],
            'NCR 1 kg' => ['75.00', '15.00', '60.00', '12.00', '48.00'],
            'Visayas 2 kg' => ['115.00', '15.00', '100.00', '20.00', '80.00'],
            'NCR 15 kg' => ['405.00', '25.00', '380.00', '76.00', '304.00'],
            'Mindanao 25 kg' => ['810.00', '50.00', '760.00', '152.00', '608.00'],
        ];
    }

    #[DataProvider('splits')]
    public function test_the_delivery_fee_splits_rider_first_then_picksell_then_logistics(string $fee, string $rider, string $balance, string $picksell, string $logistics): void
    {
        $split = app(DeliveryFeeSplit::class)->split($fee, $rider, '20');

        $this->assertSame([$fee, $rider, $balance, $picksell, $logistics, '20.00'],
            [$split['delivery_fee'], $split['rider_pay'], $split['balance'], $split['platform_commission'], $split['logistics_share'], $split['rate']]);
    }

    public function test_the_parts_always_add_up_to_the_fee_even_when_rounding(): void
    {
        $split = app(DeliveryFeeSplit::class)->split('77.77', '15.00', '12.5');
        $this->assertSame(['62.77', '7.85', '54.92'], [$split['balance'], $split['platform_commission'], $split['logistics_share']]);   // 7.84625 rounds half up

        $splitter = app(DeliveryFeeSplit::class);
        foreach (['60.01', '99.99', '123.45', '910.00', '15.01'] as $fee) {
            foreach (['0', '7.5', '12.345', '33.33', '100'] as $rate) {
                $s = $splitter->split($fee, '15.00', $rate);
                $sum = intdiv((int) round(((float) $s['rider_pay'] + (float) $s['platform_commission'] + (float) $s['logistics_share']) * 100), 1);
                $this->assertSame((int) round((float) $fee * 100), $sum, "{$fee} at {$rate}%");
            }
        }
    }

    public function test_a_fee_that_does_not_cover_the_rider_is_refused(): void
    {
        $this->expectException(DomainException::class);
        app(DeliveryFeeSplit::class)->split('15.00', '15.00');
    }

    // Checkout --------------------------------------------------------------------------------

    public function test_a_full_order_charges_the_buyer_once_and_splits_every_peso(): void
    {
        // A Metro Manila seller to a Quezon City buyer: the NCR → NCR route.
        $this->seller->update(['province' => 'Metro Manila', 'municipality' => 'Makati']);
        $manila = $this->user('buyer', 'Metro Manila', 'Quezon City');
        $this->checkout($manila, $this->cart($manila, [[$this->product(), 1]]))->assertRedirect(config('app.url').'/buyer/orders');

        $order = Order::sole();
        $shipment = $order->shipment;
        $this->assertSame(['NCR', 'NCR', 'ORIGIN_DESTINATION', 'STANDARD', '1.00', '75.00'], [$shipment->origin_region, $shipment->destination_region,
            $shipment->price_source, $shipment->parcel_class, (string) $shipment->chargeable_weight_kg, (string) $shipment->delivery_fee]);
        // Buyer pays 575: seller 450, rider 15, logistics 48, PickSell 62 (50 item + 12 delivery).
        $seller = (float) $order->amount - (float) $order->commission;
        $picksell = (float) $order->commission + (float) $shipment->platform_commission;
        $this->assertSame([450.0, 15.0, 48.0, 62.0], [$seller, (float) $shipment->rider_pay, (float) $shipment->logistics_share, $picksell]);
        $this->assertSame(575.0, $seller + (float) $shipment->rider_pay + (float) $shipment->logistics_share + $picksell);

        $this->actingAs($this->seller)->patch(config('app.url')."/seller/orders/{$order->id}/pack");
        $this->actingAs($this->seller)->post(config('app.url')."/seller/orders/{$order->id}/waybill");
        $this->assertSame('575.00', (string) PicksellBridge::parcelFor($order)->cod_amount, 'COD is the item plus the delivery fee');
        $this->actingAs($manila)->get(config('app.url')."/buyer/orders/{$order->id}/tracking")->assertOk()
            ->assertSee('₱75.00')->assertSee('Cash to pay on delivery: ₱575.00');
        $this->actingAs($this->seller)->get(config('app.url')."/seller/orders/{$order->id}")->assertOk()
            ->assertSee('not part of your earnings');
    }

    public function test_the_seller_inventory_warns_about_missing_weight(): void
    {
        $this->product(['name' => 'No Weight', 'weight_kg' => null]);

        $this->actingAs($this->seller)->get(config('app.url').'/seller/inventory')->assertOk()
            ->assertSee('Add its weight: buyers cannot check it out until you do.')
            ->assertSee('name="weight_kg"', false)
            ->assertDontSee('name="length_cm"', false);
    }

    public function test_a_seller_without_a_matching_province_is_asked_to_fix_it_and_priced_generically(): void
    {
        $this->seller->update(['province' => 'Atlantis']);
        $this->actingAs($this->seller)->get(config('app.url').'/seller/inventory')->assertOk()
            ->assertSee('Complete your address (province)');

        $this->checkout($this->buyer, $this->cart($this->buyer, [[$this->product(), 1]]))->assertSessionHasNoErrors();
        $shipment = OrderShipment::sole();
        $this->assertSame([null, 'GENERIC_FALLBACK', '85.00'], [$shipment->origin_region, $shipment->price_source, (string) $shipment->delivery_fee]);
    }

    public function test_checkout_shows_each_regions_fee_and_one_fee_per_seller(): void
    {
        $other = $this->user('seller', 'Cebu', 'Cebu City', ['business_name' => 'Cebu Crafts']);
        $tote = $this->product();
        $mug = $this->product(['name' => 'Mug', 'weight_kg' => 0.5]);
        $basket = $this->product(['seller_id' => $other->id, 'name' => 'Basket', 'weight_kg' => 2]);

        // Tote House is in Batangas (LUZON), Cebu Crafts in Cebu (VISAYAS); both parcels are in the 2 kg tier.
        foreach (['Metro Manila' => ['₱105.00', '₱115.00'], 'Cebu' => ['₱115.00', '₱95.00'], 'Davao del Sur' => ['₱120.00', '₱115.00']] as $province => [$first, $second]) {
            $buyer = $this->user('buyer', $province, 'Somewhere');
            $ids = $this->cart($buyer, [[$tote, 1], [$mug, 1], [$basket, 1]]);
            // Tote + mug = 1.5 kg in one parcel (2 kg tier); the basket is its own 2 kg parcel.
            $this->actingAs($buyer)->get(config('app.url').'/buyer/checkout?'.http_build_query(['item_ids' => $ids]))
                ->assertOk()->assertSeeInOrder(['Tote House', $first, 'Cebu Crafts', $second]);
        }

        $mindanao = $this->user('buyer', 'Davao del Sur', 'Davao City');
        $this->checkout($mindanao, $this->cart($mindanao, [[$tote, 1], [$mug, 1], [$basket, 1]]))->assertSessionHasNoErrors();
        $this->assertSame(2, OrderShipment::count(), 'one parcel per seller');
        $this->assertSame(['120.00', '115.00'], OrderShipment::orderBy('id')->pluck('delivery_fee')->map(fn ($fee) => (string) $fee)->all());
    }

    public function test_items_that_cannot_be_priced_are_never_ordered_at_a_guessed_fee(): void
    {
        $lost = $this->user('buyer', 'Atlantis', 'Nowhere');
        $this->checkout($lost, $this->cart($lost, [[$this->product(), 1]]))
            ->assertSessionHasErrors(['item_ids' => 'We could not find your province. Please check your delivery address.']);

        $noWeight = $this->product(['name' => 'Mystery Box', 'weight_kg' => null]);
        $this->checkout($this->buyer, $this->cart($this->buyer, [[$noWeight, 1]]))
            ->assertSessionHasErrors(['item_ids' => 'Can’t be shipped yet (the seller has not set its weight): Mystery Box. Remove it from your cart or ask the seller.']);

        $anvil = $this->product(['name' => 'Anvil', 'weight_kg' => 51]);
        $heavyBuyer = $this->user('buyer', 'Batangas', 'City of Tanauan');
        $this->checkout($heavyBuyer, $this->cart($heavyBuyer, [[$anvil, 1]]))
            ->assertSessionHasErrors(['item_ids' => 'Items from Tote House: Heavy parcel: shipping will be quoted by PickSell.']);

        $this->assertSame(0, Order::count());
        $this->assertSame(0, OrderShipment::count());
    }

    public function test_a_parcel_class_paying_more_than_the_fee_refuses_the_order_and_alerts_admins(): void
    {
        ParcelClass::find('STANDARD')->update(['rider_pay' => 200]);

        $this->checkout($this->buyer, $this->cart($this->buyer, [[$this->product(), 1]]))->assertSessionHasErrors('item_ids');

        $this->assertSame(0, Order::count());
        $this->assertTrue(User::where('role', 'admin')->sole()->notifications()->get()
            ->contains(fn ($n) => str_contains(json_encode($n->data), 'does not cover the rider')));
    }

    public function test_later_rate_and_commission_changes_leave_placed_orders_alone(): void
    {
        $this->checkout($this->buyer, $this->cart($this->buyer, [[$this->product(), 1]]));
        $before = OrderShipment::sole()->only(['delivery_fee', 'rider_pay', 'platform_commission', 'logistics_share', 'rates_version']);

        ShippingRate::where('destination_region', 'LUZON')->update(['fee' => 999]);
        ParcelClass::find('STANDARD')->update(['rider_pay' => 40]);
        config(['app.delivery_commission_rate' => 50]);

        $this->assertSame($before, OrderShipment::sole()->fresh()->only(array_keys($before)));
        $this->assertSame(['75.00', '15.00', '12.00', '48.00'], [$before['delivery_fee'], $before['rider_pay'], $before['platform_commission'], $before['logistics_share']]);
    }

    // Delivery --------------------------------------------------------------------------------

    /** Checkout, pack, label and hand over a 15 kg + 10 kg order (one 25 kg BULKY parcel). */
    private function bulkyHandedOver(): array
    {
        $table = $this->product(['name' => 'Table', 'weight_kg' => 15, 'price' => 3000]);
        $chairs = $this->product(['name' => 'Chairs', 'weight_kg' => 5, 'price' => 1000]);
        $this->checkout($this->buyer, $this->cart($this->buyer, [[$table, 1], [$chairs, 2]]))->assertSessionHasNoErrors();
        [$first, $second] = Order::orderBy('id')->get()->all();

        $this->actingAs($this->seller)->patch(config('app.url')."/seller/orders/{$first->id}/pack")->assertSessionHasNoErrors();
        $this->assertSame('preparing', $second->fresh()->status, 'items sharing a parcel are packed together');
        $this->actingAs($this->seller)->post(config('app.url')."/seller/orders/{$second->id}/waybill")->assertSessionHasNoErrors();
        $this->actingAs($this->seller)->patch(config('app.url')."/seller/orders/{$first->id}/handover")->assertSessionHasNoErrors();

        return [$first->fresh(), $second->fresh(), PicksellBridge::parcelFor($first)];
    }

    /** Pickup by one rider, then out for delivery with another, in Tanauan (same city). */
    private function outForDelivery($parcel): array
    {
        $this->actingAs($this->manager, 'pickrush');
        $dispatch = app(DispatchService::class);
        $tasks = app(RiderTaskService::class);

        $pickupRider = $this->rider();
        $tasks->pickUp($dispatch->assign($parcel, $pickupRider, $this->manager));
        app(ParcelLedger::class)->record($parcel->fresh(), ParcelStatus::AtOriginHub, $this->tanauan);

        $deliveryRider = $this->rider();
        $task = $dispatch->assign($parcel->fresh(), $deliveryRider, $this->manager);
        $tasks->startDelivery($task);

        return [$pickupRider, $deliveryRider, $task->fresh()];
    }

    public function test_a_bulky_order_flows_from_checkout_to_delivered_and_is_credited_once(): void
    {
        [$first, $second, $parcel] = $this->bulkyHandedOver();
        $shipment = $first->shipment;

        // 25 kg within Luzon (local): 280 + 15 × 25 = 655.
        $this->assertSame(['BULKY', '25.00', '655.00', '50.00', '121.00', '484.00'], [$shipment->parcel_class, (string) $shipment->chargeable_weight_kg,
            (string) $shipment->delivery_fee, (string) $shipment->rider_pay, (string) $shipment->platform_commission, (string) $shipment->logistics_share]);
        $this->assertSame($second->order_shipment_id, $shipment->id);
        $this->assertSame(['ready_for_pickup', 'ready_for_pickup'], [$first->status, $second->status]);
        $this->assertSame(['655.00', '5655.00', 'BULKY', '50.00'], [(string) $parcel->shipping_fee, (string) $parcel->cod_amount, $parcel->parcel_class, (string) $parcel->rider_pay]);
        $this->assertSame(['LUZON', 'LUZON', 'ORIGIN_DESTINATION'], [$parcel->origin_region, $parcel->destination_region, $parcel->price_source]);
        $this->assertSame("{$first->order_number}, {$second->order_number}", PicksellBridge::orderNumbers([$parcel])[$parcel->id]);

        [$pickupRider, $deliveryRider, $task] = $this->outForDelivery($parcel);
        app(RiderTaskService::class)->deliver($task, 5655, 'proof.jpg');

        $this->assertSame(['delivered', 'delivered'], [$first->fresh()->status, $second->fresh()->status]);
        $this->assertSame('delivered', $shipment->fresh()->status);
        $this->assertSame(['logistics' => '484.00', 'picksell' => '121.00', 'rider' => '50.00'],
            DB::table('shipment_earnings')->orderBy('party')->pluck('amount', 'party')->map(fn ($a) => number_format((float) $a, 2, '.', ''))->all());
        $this->assertSame(['pickup' => 15.0], DB::table('pr_rider_earnings')->where('rider_id', $pickupRider->id)->pluck('amount', 'kind')->map(fn ($a) => (float) $a)->all());
        $this->assertSame(['delivery' => 50.0], DB::table('pr_rider_earnings')->where('rider_id', $deliveryRider->id)->pluck('amount', 'kind')->map(fn ($a) => (float) $a)->all());

        // The delivered event repeats (retry, replay): nothing is credited twice.
        $event = $parcel->fresh()->events()->where('status', ParcelStatus::Delivered)->sole();
        ParcelStatusChanged::dispatch($parcel->fresh(), $event);
        $this->assertFalse(app(ShipmentSettlement::class)->delivered($shipment->fresh()));
        app(RiderEarnings::class)->credit($task->fresh());
        $this->artisan('shipments:settle-delivered')->expectsOutput('Credited 0 delivered shipments.');
        $this->assertSame(3, DB::table('shipment_earnings')->count());
        $this->assertSame(2, DB::table('pr_rider_earnings')->count());
    }

    public function test_a_failed_delivery_pays_the_rider_nothing_and_credits_no_one(): void
    {
        [$first, , $parcel] = $this->bulkyHandedOver();
        [, $deliveryRider, $task] = $this->outForDelivery($parcel);

        app(RiderTaskService::class)->fail($task, 'buyer_unavailable', null, 'door.jpg');

        $shipment = $first->shipment->fresh();
        $this->assertSame('failed', $shipment->status);
        $this->assertNotNull($shipment->failed_at);
        $this->assertNotEmpty($shipment->failure_reason);
        $this->assertNull($shipment->earnings_posted_at);
        $this->assertSame(0, DB::table('shipment_earnings')->count());
        $this->assertSame(0, DB::table('pr_rider_earnings')->where('rider_id', $deliveryRider->id)->count(), 'a failed attempt earns ₱0');
        $this->assertSame('delivery_failed', $first->fresh()->status);
        $this->actingAs($this->seller)->get(config('app.url')."/seller/orders/{$first->id}")->assertOk()->assertSee('Attempt failed');
    }

    public function test_cancelling_one_item_keeps_the_parcel_for_the_rest(): void
    {
        $a = $this->product(['name' => 'Tote A']);
        $b = $this->product(['name' => 'Tote B', 'price' => 300]);
        $this->checkout($this->buyer, $this->cart($this->buyer, [[$a, 1], [$b, 1]]));
        [$first, $second] = Order::orderBy('id')->get()->all();
        $this->actingAs($this->seller)->patch(config('app.url')."/seller/orders/{$first->id}/pack");
        $this->actingAs($this->seller)->post(config('app.url')."/seller/orders/{$first->id}/waybill");
        $parcel = PicksellBridge::parcelFor($first);
        $this->assertSame('895.00', (string) $parcel->cod_amount);              // 500 + 300 + 95 (2 kg within Luzon)

        $this->actingAs($this->seller)->patch(config('app.url')."/seller/orders/{$first->id}/cancel", ['reason' => 'Out of stock'])->assertSessionHasNoErrors();

        $parcel->refresh();
        $this->assertSame(ParcelStatus::LabelCreated, $parcel->current_status);
        $this->assertSame('Tote B × 1', $parcel->item_description);
        $this->assertSame('395.00', (string) $parcel->cod_amount, 'the remaining item plus the unchanged fee');

        $this->actingAs($this->seller)->patch(config('app.url')."/seller/orders/{$second->id}/cancel", ['reason' => 'Out of stock']);
        $this->assertSame(ParcelStatus::Cancelled, $parcel->fresh()->current_status);
        $this->assertSame('cancelled', $first->shipment->fresh()->status);
    }
}
