<?php

namespace Tests\Feature\Pickrush;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\FinancialTransaction;
use App\Models\LogisticsIssue;
use App\Models\Order;
use App\Models\Product;
use App\Models\ReturnRequest;
use App\Models\User;
use App\Pickrush\Bridge\PicksellBridge;
use App\Pickrush\Enums\ParcelStatus;
use App\Pickrush\Enums\StaffRole;
use App\Pickrush\Models\Hub;
use App\Pickrush\Models\Parcel;
use App\Pickrush\Models\Rider;
use App\Pickrush\Models\RiderToken;
use App\Pickrush\Models\Staff;
use App\Pickrush\Services\Hq\PicksellSettlement;
use App\Pickrush\Services\OperationRefused;
use App\Pickrush\Services\ParcelLedger;
use App\Pickrush\Services\Station\DispatchService;
use App\Pickrush\Services\Station\FailedDeliveries;
use App\Pickrush\Services\Station\RiderTaskService;
use App\Services\Logistics\LogisticsIssueDesk;
use App\Services\Orders\OrderReceipt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * A buyer's return/refund: the order waits as Delivered while it is handled; the seller approves and
 * Pickrush picks the item up from the buyer and hands it to the seller, who pays the return fee;
 * the seller checks it, Finance approves (the order completes and the refund comes out of the held
 * payment) and sends it through GCash. A seller who does not take the return has it held at the
 * station like any return, and PickSell can refund the buyer anyway.
 */
class BuyerReturnsTest extends TestCase
{
    use BuildsNetwork, RefreshDatabase;

    private User $seller;

    private User $buyer;

    private User $admin;

    private Hub $tanauan;

    private Staff $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildNetwork();
        $this->tanauan = $this->station('041029000');
        $this->manager = $this->staff(StaffRole::HubManager, $this->tanauan);
        $this->seller = $this->user('seller', ['business_name' => 'Tote House']);
        $this->buyer = $this->user('buyer');
        $this->admin = $this->user('admin');
    }

    private function user(string $role, array $extra = []): User
    {
        static $n = 0;
        $n++;

        return User::create(array_merge([
            'role' => $role, 'status' => 'approved', 'first_name' => ucfirst($role), 'last_name' => "Buy{$n}", 'sex' => 'Female',
            'email' => "{$role}{$n}@buyer-returns.test", 'password' => bcrypt('secret-password'), 'contact_no' => '09170000000',
            'birthday' => '1995-01-01', 'age' => 30, 'province' => 'Batangas', 'municipality' => 'City of Tanauan', 'barangay' => 'Altura Bata',
            'house_no' => '12', 'street' => 'Rizal St',
        ], $extra));
    }

    private function rider(): Rider
    {
        static $n = 0;
        $n++;

        $rider = Rider::withoutGlobalScopes()->create([
            'station_hub_id' => $this->tanauan->id, 'full_name' => "Buyer Return Rider {$n}", 'mobile_no' => '0918'.str_pad((string) $n, 7, '0', STR_PAD_LEFT),
            'vehicle_type' => 'motorcycle', 'status' => 'active', 'shift_status' => 'on_shift', 'last_seen_at' => now(),
            'cod_limit' => 100000, 'max_parcels' => 30, 'created_by' => $this->manager->id,
        ]);
        RiderToken::issue($rider);

        return $rider;
    }

    /** A 1 kg ₱500 tote bought and delivered in Tanauan (same city: ₱75 delivery). */
    private function deliveredOrder(): Order
    {
        $product = Product::create(['seller_id' => $this->seller->id, 'name' => 'Canvas Tote', 'category' => 'Fashion',
            'price' => 500, 'stock' => 10, 'status' => 'active', 'weight_kg' => 1]);
        $cart = Cart::firstOrCreate(['buyer_id' => $this->buyer->id]);
        $item = CartItem::create(['cart_id' => $cart->id, 'product_id' => $product->id, 'quantity' => 1]);
        $this->actingAs($this->buyer)->post(config('app.url').'/buyer/cart/checkout', ['item_ids' => [$item->id]])->assertSessionHasNoErrors();
        $order = Order::latest('id')->first();
        $this->actingAs($this->seller)->patch(config('app.url')."/seller/orders/{$order->id}/pack")->assertSessionHasNoErrors();
        $this->actingAs($this->seller)->post(config('app.url')."/seller/orders/{$order->id}/waybill")->assertSessionHasNoErrors();
        $this->actingAs($this->seller)->patch(config('app.url')."/seller/orders/{$order->id}/handover")->assertSessionHasNoErrors();
        $parcel = PicksellBridge::parcelFor($order);

        $this->actingAs($this->manager, 'pickrush');
        $dispatch = app(DispatchService::class);
        $tasks = app(RiderTaskService::class);
        $tasks->pickUp($dispatch->assign($parcel, $this->rider(), $this->manager));
        app(ParcelLedger::class)->record($parcel->fresh(), ParcelStatus::AtOriginHub, $this->tanauan);
        $task = $dispatch->assign($parcel->fresh(), $this->rider(), $this->manager);
        $tasks->startDelivery($task);
        $tasks->deliver($task->fresh(), (float) $parcel->fresh()->cod_amount, 'door.jpg');

        $order = $order->fresh();
        $this->assertSame('delivered', $order->status);

        return $order;
    }

    private function requestReturn(Order $order): ReturnRequest
    {
        $this->actingAs($this->buyer)->post(config('app.url')."/buyer/orders/{$order->id}/returns", [
            'reason' => 'damaged', 'details' => 'The strap was torn when it arrived.',
            'gcash_number' => '09171234567', 'gcash_name' => 'Buyer Buy',
        ])->assertSessionHasNoErrors();

        return ReturnRequest::where('order_id', $order->id)->sole();
    }

    /** Seller approves; Pickrush picks it up at the buyer and brings it to the station, out to the seller. */
    private function approvedAndOutToSeller(ReturnRequest $return): array
    {
        $this->actingAs($this->seller)->patch(config('app.url')."/seller/returns/{$return->id}/approve", ['seller_note' => 'Sorry, send it back.'])
            ->assertSessionHasNoErrors();
        $parcel = PicksellBridge::returnParcel($return);
        $this->assertNotNull($parcel);

        $this->actingAs($this->manager, 'pickrush');
        $dispatch = app(DispatchService::class);
        $tasks = app(RiderTaskService::class);
        $pickup = $dispatch->assign($parcel->fresh(), $this->rider(), $this->manager);
        $this->assertSame('pickup', $pickup->type);
        $tasks->pickUp($pickup);
        $this->assertSame('in_transit', $return->fresh()->tracking_status);
        app(ParcelLedger::class)->record($parcel->fresh(), ParcelStatus::AtOriginHub, $this->tanauan);

        $rider = $this->rider();
        $task = $dispatch->assign($parcel->fresh(), $rider, $this->manager);
        $this->assertSame('delivery', $task->type, 'it is delivered to the seller like any parcel');
        $tasks->startDelivery($task);

        return [$parcel->fresh(), $task->fresh(), $rider];
    }

    public function test_a_return_goes_from_the_buyer_to_the_seller_on_pickrush_and_the_refund_comes_from_the_held_payment(): void
    {
        $order = $this->deliveredOrder();
        $return = $this->requestReturn($order);
        $this->assertSame(['requested', '500.00', '09171234567'], [$return->status, (string) $return->refund_amount, $return->refund_gcash_number]);

        // Pending: no Order Received, and the 5-day automatic completion skips it.
        $this->actingAs($this->buyer)->patch(config('app.url')."/buyer/orders/{$order->id}/received")->assertStatus(422);
        $this->travel(6)->days();
        app(OrderReceipt::class)->autoConfirmDue();
        $this->assertSame('delivered', $order->fresh()->status, 'the payment stays on hold past the deadline');
        $this->assertSame(0, FinancialTransaction::where('order_id', $order->id)->count());

        [$parcel, $task, $rider] = $this->approvedAndOutToSeller($return);
        $this->assertTrue($parcel->isBuyerReturn());
        $this->assertSame('RET-'.$return->id, $parcel->external_ref);
        $this->assertSame([$order->shipment->recipient_name ?? $this->buyer->full_name, 'Tote House'], [$parcel->shipper_name, $parcel->consignee_name], 'picked up at the buyer, delivered to the seller');
        $this->assertSame(['75.00', '75.00', '0.00'], [(string) $parcel->cod_amount, (string) $parcel->shipping_fee, (string) $parcel->platform_commission]);
        $this->assertSame('75.00', (string) $return->fresh()->return_fee);

        // The seller pays the return fee to the rider; PickSell is owed nothing of it.
        app(RiderTaskService::class)->deliver($task, 75, 'seller.jpg');
        $this->assertSame(0.0, PicksellSettlement::owedFor($parcel->fresh()));
        $return->refresh();
        $this->assertSame(['received', 'delivered_to_seller'], [$return->status, $return->tracking_status]);
        $this->assertSame('delivered', $order->fresh()->status, 'the order itself does not follow the return waybill');

        // The seller checks it: Finance is asked for the refund.
        $this->actingAs($this->seller)->patch(config('app.url')."/seller/returns/{$return->id}/inspect", [
            'result' => 'passed', 'inspection_note' => 'Strap torn as the buyer said.',
        ])->assertSessionHasNoErrors();
        $refund = $return->refunds()->sole();
        $this->assertSame(['approved_for_refund', 'requested'], [$return->fresh()->status, $refund->status]);

        // Finance approves: the order completes, posting the held payment, and the refund reverses it.
        $this->actingAs($this->admin)->patch(config('app.url')."/admin/refunds/{$refund->id}/approve", ['reason' => 'Seller confirmed the damage.'])
            ->assertSessionHasNoErrors();
        $this->assertSame('completed', $order->fresh()->status);
        $this->assertSame(['commission', 'commission_reversal', 'order_gross', 'refund_gross', 'seller_adjustment', 'seller_net'],
            FinancialTransaction::where('order_id', $order->id)->orderBy('type')->pluck('type')->all());

        // Finance sends it through GCash and records the reference: Refunded.
        $this->actingAs($this->admin)->patch(config('app.url')."/admin/refunds/{$refund->id}/sent", ['payout_reference' => 'GC-778899'])
            ->assertSessionHasNoErrors();
        $this->assertSame(['processed', 'gcash', 'GC-778899'], [$refund->fresh()->status, $refund->fresh()->payout_method, $refund->fresh()->payout_reference]);
        $this->assertSame('completed', $return->fresh()->status);
        // Return notifications are stored JSON-encoded (readers decode until they get an array).
        $this->assertTrue($this->buyer->notifications()->get()->contains(fn ($n) => str_contains(is_string($n->data) ? $n->data : json_encode($n->data), 'GC-778899')),
            'the buyer is told the transfer reference');
    }

    public function test_after_order_received_the_regular_return_is_closed(): void
    {
        $order = $this->deliveredOrder();
        $this->actingAs($this->buyer)->patch(config('app.url')."/buyer/orders/{$order->id}/received")->assertSessionHasNoErrors();
        $this->assertSame('completed', $order->fresh()->status);
        $this->assertFalse($order->fresh()->returnWindowOpen());

        $this->actingAs($this->buyer)->post(config('app.url')."/buyer/orders/{$order->id}/returns", [
            'reason' => 'damaged', 'details' => 'Changed my mind after confirming.',
            'gcash_number' => '09171234567', 'gcash_name' => 'Buyer Buy',
        ])->assertStatus(422);
        $this->assertSame(0, ReturnRequest::count());
    }

    public function test_only_seller_fault_reasons_and_a_gcash_number_are_accepted(): void
    {
        $order = $this->deliveredOrder();
        $this->actingAs($this->buyer)->post(config('app.url')."/buyer/orders/{$order->id}/returns", [
            'reason' => 'other', 'details' => 'I changed my mind.', 'gcash_number' => '12345', 'gcash_name' => 'Buyer',
        ])->assertSessionHasErrors(['reason', 'gcash_number']);
        $this->assertSame(0, ReturnRequest::count());
    }

    public function test_a_return_the_seller_does_not_take_is_held_never_sent_to_the_buyer_and_picksell_refunds_anyway(): void
    {
        $order = $this->deliveredOrder();
        $return = $this->requestReturn($order);
        [$parcel, $task, $rider] = $this->approvedAndOutToSeller($return);

        app(RiderTaskService::class)->fail($task, 'seller_refused_fee', null, 'shop.jpg');
        $parcel->refresh();
        $this->assertSame(ParcelStatus::DeliveryFailed, $parcel->current_status);
        $this->assertTrue($parcel->isHeldForSeller());
        $this->assertSame('held_for_seller', $return->fresh()->tracking_status);
        $this->assertSame(0, DB::table('pr_rider_earnings')->where('rider_id', $rider->id)->count(), 'a refused hand-over earns ₱0');

        try {
            app(FailedDeliveries::class)->returnToSender($parcel, $this->manager, 'Seller refused');
            $this->fail('A buyer return was sent back to the buyer.');
        } catch (OperationRefused $e) {
            $this->assertStringContainsString('does not go back to the buyer', $e->getMessage());
        }
        $this->assertSame('delivery', DispatchService::taskFor($parcel, $this->tanauan), 'the seller can ask for it again');

        // Past the hold the company reports it (PickSell sets the fault and the strike) ...
        $issue = app(LogisticsIssueDesk::class)->reportRefusedReturn($this->manager, $parcel, 'The seller refused to pay the return fee twice.');
        $this->assertSame([$order->id, 'return_refused'], [$issue->order_id, $issue->type]);

        // ... and after 30 days it may be abandoned.
        $this->travel(30)->days();
        app(FailedDeliveries::class)->abandon($parcel->fresh(), $this->manager);
        $this->assertSame(ParcelStatus::Abandoned, $parcel->fresh()->current_status);
        $this->assertSame('abandoned', $return->fresh()->tracking_status);

        // PickSell refunds the buyer without the item, so they are not left waiting.
        $this->actingAs($this->admin)->patch(config('app.url')."/admin/returns/{$return->id}/refund-without-item", ['admin_notes' => 'Seller never took the return.'])
            ->assertSessionHasNoErrors();
        $this->assertSame('approved_for_refund', $return->fresh()->status);
        $this->assertSame('requested', $return->refunds()->sole()->status);
    }

    public function test_a_failed_inspection_goes_to_picksell_who_can_side_with_the_seller(): void
    {
        $order = $this->deliveredOrder();
        $return = $this->requestReturn($order);
        [, $task] = $this->approvedAndOutToSeller($return);
        app(RiderTaskService::class)->deliver($task, 75, 'seller.jpg');

        $this->actingAs($this->seller)->patch(config('app.url')."/seller/returns/{$return->id}/inspect", [
            'result' => 'failed', 'inspection_note' => 'This is a different, used bag.',
        ])->assertSessionHasNoErrors();
        $this->assertSame(['received', 'open'], [$return->fresh()->status, $return->fresh()->dispute_status]);

        $this->actingAs($this->admin)->patch(config('app.url')."/admin/returns/{$return->id}/resolve", [
            'decision' => 'side_with_seller', 'admin_notes' => 'Photos show a different bag.',
        ])->assertSessionHasNoErrors();

        $this->assertSame(['rejected', 'resolved'], [$return->fresh()->status, $return->fresh()->dispute_status]);
        $this->assertSame('completed', $order->fresh()->status, 'the seller\'s payment is released');
        $back = Parcel::withoutGlobalScopes()->where('external_ref', 'RTB-'.$return->id)->sole();
        $this->assertSame(['Tote House', '75.00'], [$back->shipper_name, (string) $back->cod_amount]);
        $this->assertNull(FailedDeliveries::returnFee($back), 'a refused send-back is not charged to the seller');
        $this->assertSame(0, $return->refunds()->count());
    }

    public function test_a_rejection_upheld_by_picksell_completes_the_order(): void
    {
        $order = $this->deliveredOrder();
        $return = $this->requestReturn($order);
        $this->actingAs($this->seller)->patch(config('app.url')."/seller/returns/{$return->id}/reject", ['seller_note' => 'The photo shows wear from use.'])
            ->assertSessionHasNoErrors();

        $this->actingAs($this->admin)->patch(config('app.url')."/admin/returns/{$return->id}/resolve", [
            'decision' => 'uphold_rejection', 'admin_notes' => 'The damage is wear from use.',
        ])->assertSessionHasNoErrors();

        $this->assertSame('completed', $order->fresh()->status);
        $this->assertNull(PicksellBridge::returnParcel($return));
    }
}
