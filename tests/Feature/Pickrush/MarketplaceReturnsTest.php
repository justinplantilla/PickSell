<?php

namespace Tests\Feature\Pickrush;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\LogisticsIssue;
use App\Models\Order;
use App\Models\Product;
use App\Models\SellerStrike;
use App\Models\User;
use App\Notifications\SellerReturnUpdate;
use App\Pickrush\Bridge\PicksellBridge;
use App\Pickrush\Enums\ParcelStatus;
use App\Pickrush\Enums\StaffRole;
use App\Pickrush\Models\Hub;
use App\Pickrush\Models\Parcel;
use App\Pickrush\Models\Rider;
use App\Pickrush\Models\RiderToken;
use App\Pickrush\Models\Staff;
use App\Pickrush\Services\OperationRefused;
use App\Pickrush\Services\ParcelLedger;
use App\Pickrush\Services\Station\CashHandling;
use App\Pickrush\Services\Station\DispatchService;
use App\Pickrush\Services\Station\FailedDeliveries;
use App\Pickrush\Services\Station\RiderTaskService;
use App\Services\Logistics\LogisticsIssueDesk;
use App\Services\Logistics\SellerStrikes;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * A PickSell parcel the buyer did not receive: after three failed attempts it goes back to the
 * seller, who pays the original shipping fee to the return rider (no PickSell commission). A
 * seller who does not take it back has it held at the station; the company reports it, PickSell
 * strikes the seller, three strikes hold the shop, and after 30 days the company keeps the parcel.
 */
class MarketplaceReturnsTest extends TestCase
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
            'role' => $role, 'status' => 'approved', 'first_name' => ucfirst($role), 'last_name' => "Ret{$n}", 'sex' => 'Female',
            'email' => "{$role}{$n}@returns.test", 'password' => bcrypt('secret-password'), 'contact_no' => '09170000000',
            'birthday' => '1995-01-01', 'age' => 30, 'province' => 'Batangas', 'municipality' => 'City of Tanauan', 'barangay' => 'Altura Bata',
            'house_no' => '12', 'street' => 'Rizal St',
        ], $extra));
    }

    private function rider(): Rider
    {
        static $n = 0;
        $n++;

        $rider = Rider::withoutGlobalScopes()->create([
            'station_hub_id' => $this->tanauan->id, 'full_name' => "Return Rider {$n}", 'mobile_no' => '0919'.str_pad((string) $n, 7, '0', STR_PAD_LEFT),
            'vehicle_type' => 'motorcycle', 'status' => 'active', 'shift_status' => 'on_shift', 'last_seen_at' => now(),
            'cod_limit' => 100000, 'max_parcels' => 30, 'created_by' => $this->manager->id,
        ]);
        RiderToken::issue($rider);

        return $rider;
    }

    /** A 1 kg order in Tanauan (same city, local: ₱75), picked up and at the station. */
    private function parcelAtStation(): array
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
        app(RiderTaskService::class)->pickUp(app(DispatchService::class)->assign($parcel, $this->rider(), $this->manager));
        app(ParcelLedger::class)->record($parcel->fresh(), ParcelStatus::AtOriginHub, $this->tanauan);

        return [$order->fresh(), $parcel->fresh(), $product];
    }

    /** Three failed attempts, then the station starts the return. */
    private function returning(): array
    {
        [$order, $parcel, $product] = $this->parcelAtStation();
        $dispatch = app(DispatchService::class);
        $tasks = app(RiderTaskService::class);
        foreach (range(1, 3) as $attempt) {
            $task = $dispatch->assign($parcel->fresh(), $this->rider(), $this->manager);
            $tasks->startDelivery($task);
            $tasks->fail($task->fresh(), 'buyer_unavailable', null, "door{$attempt}.jpg");
        }
        $this->assertSame(3, $parcel->fresh()->attempt_count);

        app(FailedDeliveries::class)->returnToSender($parcel->fresh(), $this->manager, 'Buyer never home');

        return [$order, $parcel->fresh(), $product];
    }

    /** A return rider out to the seller. */
    private function handBack(Parcel $parcel): array
    {
        $rider = $this->rider();
        $task = app(DispatchService::class)->assign($parcel->fresh(), $rider, $this->manager);
        $this->assertSame('return', $task->type);
        app(RiderTaskService::class)->startDelivery($task);

        return [$rider, $task->fresh()];
    }

    public function test_the_seller_pays_the_original_shipping_fee_to_the_return_rider_and_picksell_takes_nothing(): void
    {
        [$order, $parcel] = $this->returning();
        $this->assertSame(['75.00', '75.00', true], [(string) $parcel->shipping_fee, (string) $parcel->return_fee, $parcel->is_return]);
        $this->assertTrue($this->seller->notifications()->get()->contains(fn ($n) => str_contains($n->data['message'], 'on its way back to you')
            && str_contains($n->data['message'], '₱75.00')), 'the seller is told the return fee');

        [$rider, $task] = $this->handBack($parcel);
        try {
            app(RiderTaskService::class)->deliver($task, 0, 'seller.jpg');
            $this->fail('A hand-back without the return fee was accepted.');
        } catch (OperationRefused $e) {
            $this->assertStringContainsString('return fee is ₱75.00', $e->getMessage());
        }
        app(RiderTaskService::class)->deliver($task, 75, 'seller.jpg');

        $this->assertSame(ParcelStatus::ReturnedToSender, $parcel->fresh()->current_status);
        $this->assertSame('returned', $order->fresh()->status);
        $this->assertSame(['return' => 15.0], DB::table('pr_rider_earnings')->where('rider_id', $rider->id)->pluck('amount', 'kind')->map(fn ($a) => (float) $a)->all());
        // The rider hands all ₱75 to the station; their ₱15 goes to their payslip.
        $cash = app(CashHandling::class)->breakdown($rider);
        $this->assertSame([75.0, 0.0, 75.0], [$cash['cod'], $cash['pay'], $cash['to_remit']]);
        $this->assertSame(0, DB::table('shipment_earnings')->count(), 'no PickSell commission or split on a return');
    }

    public function test_a_return_the_seller_does_not_take_back_is_held_and_after_30_days_is_abandoned(): void
    {
        [$order, $parcel] = $this->returning();
        [$rider, $task] = $this->handBack($parcel);

        app(RiderTaskService::class)->fail($task, 'seller_refused_fee', null, 'shop.jpg');

        $parcel->refresh();
        $this->assertSame(ParcelStatus::ReturningToSender, $parcel->current_status);
        $this->assertNotNull($parcel->return_refused_at);
        $this->assertSame(0, DB::table('pr_rider_earnings')->where('rider_id', $rider->id)->count(), 'a refused hand-back earns ₱0');
        $this->assertTrue($this->seller->notifications()->get()->contains(fn ($n) => str_contains($n->data['message'], 'held at')));
        $this->assertSame('return', DispatchService::taskFor($parcel, $this->tanauan), 'the seller can ask for another hand-back');

        try {
            app(FailedDeliveries::class)->abandon($parcel, $this->manager);
            $this->fail('A return was abandoned before 30 days.');
        } catch (OperationRefused $e) {
            $this->assertStringContainsString('after 30 days', $e->getMessage());
        }

        $this->travel(30)->days();
        app(FailedDeliveries::class)->abandon($parcel->fresh(), $this->manager);

        $this->assertSame(ParcelStatus::Abandoned, $parcel->fresh()->current_status);
        $this->assertNull($parcel->fresh()->current_hub_id);
        $this->assertSame('returned', $order->fresh()->status);
    }

    public function test_the_company_reports_a_refused_return_and_three_seller_faults_hold_the_shop_until_picksell_lifts_it(): void
    {
        [, $parcel, $product] = $this->returning();
        $desk = app(LogisticsIssueDesk::class);

        try {
            $desk->reportRefusedReturn($this->manager, $parcel, 'The seller would not take it back.');
            $this->fail('A return the seller never refused was reported.');
        } catch (DomainException $e) {
            $this->assertStringContainsString('not a return the seller did not take back', $e->getMessage());
        }

        [, $task] = $this->handBack($parcel);
        app(RiderTaskService::class)->fail($task, 'seller_unavailable', null, 'closed.jpg');
        $issue = $desk->reportRefusedReturn($this->manager, $parcel->fresh(), 'Tried twice; the shop said to keep it.');
        $this->assertSame(['return_refused', 'logistics'], [$issue->type, $issue->reporter_role]);
        $this->expectsDuplicateReportRefused($desk, $parcel);

        // Two earlier strikes, then this one resolved as the seller's fault: the third holds the shop.
        foreach (range(1, 2) as $i) {
            SellerStrike::create(['seller_id' => $this->seller->id, 'reason' => "Earlier return {$i}", 'expires_at' => now()->addDays(60)]);
        }
        $desk->resolve($issue, $this->admin, 'seller', 'Seller confirmed they refused.');

        $this->seller->refresh();
        $this->assertSame(3, app(SellerStrikes::class)->active($this->seller));
        $this->assertTrue($this->seller->isSellingHeld());
        $this->assertFalse($product->fresh()->isPurchasable(), 'a held shop takes no orders');
        $this->assertTrue($product->fresh()->isShowcased(), 'but buyers still see it');
        $this->assertTrue($this->seller->notifications()->get()->contains(fn ($n) => str_contains($n->data['message'], 'Your shop is on hold')));

        // Strikes lapse after 90 days, but only PickSell lifts the hold.
        $this->travel(91)->days();
        $this->assertSame(0, app(SellerStrikes::class)->active($this->seller));
        $this->assertTrue($this->seller->fresh()->isSellingHeld());

        app(SellerStrikes::class)->lift($this->seller->fresh(), $this->admin, 'Talked with the seller; they will take returns back.');
        $this->assertFalse($this->seller->fresh()->isSellingHeld());
        $this->assertTrue($product->fresh()->isPurchasable());
    }

    public function test_a_fault_that_is_not_the_sellers_gives_no_strike(): void
    {
        [, $parcel] = $this->returning();
        [, $task] = $this->handBack($parcel);
        app(RiderTaskService::class)->fail($task, 'wrong_address', null, 'nowhere.jpg');
        $issue = app(LogisticsIssueDesk::class)->reportRefusedReturn($this->manager, $parcel->fresh(), 'Could not find the shop at the address.');

        app(LogisticsIssueDesk::class)->resolve($issue, $this->admin, 'carrier', 'The rider went to the wrong barangay.');

        $this->assertSame(0, SellerStrike::count());
        $this->assertFalse($this->seller->fresh()->isSellingHeld());
    }

    public function test_the_station_hq_and_admin_pages_follow_a_return_from_held_to_lifted_hold(): void
    {
        [, $parcel] = $this->returning();
        [, $task] = $this->handBack($parcel);
        app(RiderTaskService::class)->fail($task, 'seller_refused_parcel', null, 'shop.jpg');

        $this->actingAs($this->manager, 'pickrush')->get($this->pickrushUrl('/station/failed'))->assertOk()
            ->assertSee('Returns the seller did not take back (1)')->assertSee($parcel->tracking_no)->assertSee('₱75.00');

        $this->travel(7)->days();
        $hq = $this->staff(StaffRole::SuperAdmin, null, ['company_id' => $parcel->company_id]);
        $this->actingAs($hq, 'pickrush')->get($this->pickrushUrl('/hq/marketplace-issues'))->assertOk()
            ->assertSee('Held past 7 days, not reported yet')->assertSee($parcel->tracking_no);
        $this->actingAs($hq, 'pickrush')->post($this->pickrushUrl('/hq/marketplace-issues/refused-returns'),
            ['tracking_no' => strtolower($parcel->tracking_no), 'description' => 'Seller refused twice; held 7 days.'])->assertSessionHasNoErrors();
        $this->assertSame(1, LogisticsIssue::where('type', 'return_refused')->where('parcel_id', $parcel->id)->count());

        app(SellerStrikes::class)->hold($this->seller, '3 strikes for returns not taken back in 90 days.');
        $this->actingAs($this->seller)->get(config('app.url').'/seller/dashboard')->assertOk()->assertSee('Your shop is on hold');
        $this->actingAs($this->admin)->get(route('admin.compliance.seller', $this->seller))->assertOk()
            ->assertSee('Shop on hold')->assertSee('Lift hold');
        $this->actingAs($this->admin)->patch(route('admin.compliance.lift-hold', $this->seller), ['note' => 'Talked with the seller by phone.'])
            ->assertSessionHasNoErrors();
        $this->assertFalse($this->seller->fresh()->isSellingHeld());
    }

    private function expectsDuplicateReportRefused(LogisticsIssueDesk $desk, Parcel $parcel): void
    {
        try {
            $desk->reportRefusedReturn($this->manager, $parcel->fresh(), 'Reporting the same return again.');
            $this->fail('The same return was reported twice.');
        } catch (DomainException $e) {
            $this->assertStringContainsString('already reported', $e->getMessage());
        }
        $this->assertSame(1, LogisticsIssue::where('type', 'return_refused')->count());
    }
}
