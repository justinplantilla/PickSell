<?php

namespace Tests\Feature\Pickrush;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\LogisticsClaim;
use App\Models\LogisticsIssue;
use App\Models\Order;
use App\Models\PlatformSetting;
use App\Models\Product;
use App\Models\User;
use App\Pickrush\Bridge\PicksellBridge;
use App\Pickrush\Enums\ParcelStatus;
use App\Pickrush\Enums\StaffRole;
use App\Pickrush\Models\Company;
use App\Pickrush\Models\Parcel;
use App\Pickrush\Models\ParcelException;
use App\Pickrush\Services\ParcelLedger;
use App\Services\Finance\LogisticsSettlement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Admin's logistics issues desk: issues open from lost parcels, hub exceptions and buyer/seller
 * reports; admin escalates to the company (which replies from its portal) and resolves with who
 * was at fault; a carrier-fault loss charges a capped claim against the company's share.
 */
class LogisticsIssuesTest extends TestCase
{
    use BuildsNetwork, RefreshDatabase;

    private User $seller;

    private User $buyer;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildNetwork();
        $this->staff(StaffRole::HubManager, $this->station('041029000'));
        $this->seller = $this->user('seller', ['business_name' => 'Tote House', 'municipality_code' => '041029000']);
        $this->buyer = $this->user('buyer', ['municipality_code' => '041029000']);
        $this->admin = $this->user('admin');
    }

    public function test_a_lost_parcel_opens_an_issue_and_a_carrier_fault_charges_a_claim(): void
    {
        [$order, $parcel] = $this->shippedOrder(price: 800);
        $this->lose($parcel);

        $issue = LogisticsIssue::sole();
        $this->assertSame(['lost', 'parcel', 'open', $order->id, Company::defaultId()], [$issue->type, $issue->source, $issue->status, $issue->order_id, $issue->company_id]);

        $this->actingAs($this->admin)->get($this->url('/admin/logistics/issues'))->assertOk()->assertSee('Parcel lost')->assertSee($order->order_number);
        $this->actingAs($this->admin)->get($this->url("/admin/logistics/issues/{$issue->id}"))->assertOk()
            ->assertSee('Claim if the company is at fault')->assertSee('₱800.00');

        $fee = (float) $order->shipment->delivery_fee;
        $this->actingAs($this->admin)->patch($this->url("/admin/logistics/issues/{$issue->id}/resolve"), ['fault' => 'carrier', 'notes' => 'Station admitted the loss'])
            ->assertSessionHasNoErrors();

        $claim = LogisticsClaim::sole();
        $this->assertSame(['charged', round(800 + $fee, 2)], [$claim->status, (float) $claim->amount]);
        $this->assertSame('resolved', $issue->fresh()->status);

        $money = app(LogisticsSettlement::class)->byCompany()->keyBy(fn ($row) => $row['company']->id)[Company::defaultId()];
        $this->assertSame([(float) $claim->amount, round(-$claim->amount, 2)], [$money['claims'], $money['net_logistics']]);

        // The company's scorecard shows the loss and warns the admin.
        $this->actingAs($this->admin)->get($this->url('/admin/logistics/companies/'.Company::defaultId()))->assertOk()
            ->assertSee('Scorecard · last 30 days')->assertSee('Lost rate 100%', false)->assertSee('Suspending is your call');

        // Waiving takes it off the company's bill.
        $this->actingAs($this->admin)->patch($this->url("/admin/logistics/claims/{$claim->id}/waive"), ['reason' => 'Found and delivered later'])->assertSessionHasNoErrors();
        $this->assertSame(0.0, app(LogisticsSettlement::class)->byCompany()->first()['claims']);
    }

    public function test_claims_never_exceed_the_cap(): void
    {
        PlatformSetting::set(LogisticsClaim::CAP_SETTING, '1000');
        [, $parcel] = $this->shippedOrder(price: 4000);
        $this->lose($parcel);
        $issue = LogisticsIssue::sole();

        $this->actingAs($this->admin)->patch($this->url("/admin/logistics/issues/{$issue->id}/resolve"), ['fault' => 'carrier', 'notes' => 'Lost', 'claim_amount' => 5000])
            ->assertSessionHasErrors('claim_amount');
        $this->actingAs($this->admin)->patch($this->url("/admin/logistics/issues/{$issue->id}/resolve"), ['fault' => 'carrier', 'notes' => 'Lost in transit'])
            ->assertSessionHasNoErrors();
        $this->assertSame([1000.0, true], [(float) LogisticsClaim::sole()->amount, LogisticsClaim::sole()->capped]);
    }

    public function test_no_claim_when_someone_else_was_at_fault(): void
    {
        [, $parcel] = $this->shippedOrder();
        $this->lose($parcel);

        $this->actingAs($this->admin)->patch($this->url('/admin/logistics/issues/'.LogisticsIssue::sole()->id.'/resolve'), ['fault' => 'seller', 'notes' => 'Seller never handed it over'])
            ->assertSessionHasNoErrors();
        $this->assertSame(0, LogisticsClaim::count());
    }

    public function test_hub_exceptions_are_mirrored_and_close_when_the_company_resolves_them(): void
    {
        [$order, $parcel] = $this->shippedOrder();
        $exception = ParcelException::withoutGlobalScopes()->create(['hub_id' => $this->station('041029000')->id, 'parcel_id' => $parcel->id, 'type' => 'damaged', 'note' => 'Box crushed']);

        $issue = LogisticsIssue::sole();
        $this->assertSame(['damaged', 'exception', $exception->id, $order->id], [$issue->type, $issue->source, $issue->exception_id, $issue->order_id]);

        $exception->update(['status' => 'resolved', 'resolution' => 'continue', 'resolution_note' => 'Repacked']);
        $this->assertSame('resolved', $issue->fresh()->status);
        $this->assertStringContainsString('Repacked', $issue->fresh()->resolution_notes);
    }

    public function test_buyers_and_sellers_report_problems_and_the_company_replies_from_its_portal(): void
    {
        [$order] = $this->shippedOrder();

        $this->actingAs($this->buyer)->get($this->url("/buyer/orders/{$order->id}/tracking"))->assertOk()->assertSee('Problem with the delivery?');
        $this->actingAs($this->buyer)->post($this->url("/buyer/orders/{$order->id}/delivery-problem"), ['type' => 'pickup_missed', 'description' => 'Not a buyer option at all'])
            ->assertSessionHasErrors('type');
        $this->actingAs($this->buyer)->post($this->url("/buyer/orders/{$order->id}/delivery-problem"), ['type' => 'rider_conduct', 'description' => 'The rider asked for an extra fee'])
            ->assertSessionHasNoErrors();
        $this->actingAs($this->buyer)->post($this->url("/buyer/orders/{$order->id}/delivery-problem"), ['type' => 'rider_conduct', 'description' => 'The rider asked for an extra fee'])
            ->assertSessionHasErrors('delivery_problem');
        $this->actingAs($this->seller)->post($this->url("/seller/orders/{$order->id}/delivery-problem"), ['type' => 'pickup_missed', 'description' => 'No rider came for two days'])
            ->assertSessionHasNoErrors();
        // Someone else's order is not found.
        $stranger = $this->user('buyer');
        $this->actingAs($stranger)->post($this->url("/buyer/orders/{$order->id}/delivery-problem"), ['type' => 'late', 'description' => 'Not my order at all'])->assertNotFound();

        $issue = LogisticsIssue::where('type', 'rider_conduct')->sole();
        $this->assertSame(['report', 'buyer', $this->buyer->id], [$issue->source, $issue->reporter_role, $issue->reported_by]);

        $this->actingAs($this->admin)->patch($this->url("/admin/logistics/issues/{$issue->id}/escalate"), ['message' => 'Please check with the rider and reply'])->assertSessionHasNoErrors();
        $this->assertSame('escalated', $issue->fresh()->status);

        $hq = $this->staff(StaffRole::SuperAdmin, null, ['company_id' => Company::defaultId()]);
        $this->actingAs($hq, 'pickrush')->get($this->pickrushUrl('/hq/marketplace-issues'))->assertOk()
            ->assertSee('Waiting on your reply (1)')->assertSee('Please check with the rider and reply');
        $this->actingAs($hq, 'pickrush')->post($this->pickrushUrl("/hq/marketplace-issues/{$issue->id}/respond"), ['response' => 'Rider was warned; fee refunded to the buyer'])
            ->assertSessionHasNoErrors();
        $this->assertSame(['open', 'Rider was warned; fee refunded to the buyer'], [$issue->fresh()->status, $issue->fresh()->company_response]);

        // Another company cannot see or answer it.
        $other = Company::create(['name' => 'Bilis Hatid Express', 'slug' => 'bilis', 'tracking_prefix' => 'BH', 'status' => 'approved', 'approved_at' => now()]);
        $otherHq = $this->staff(StaffRole::SuperAdmin, null, ['company_id' => $other->id]);
        $this->actingAs($otherHq, 'pickrush')->get($this->pickrushUrl('/hq/marketplace-issues'))->assertOk()->assertDontSee('Rider was warned');
        $this->actingAs($otherHq, 'pickrush')->post($this->pickrushUrl("/hq/marketplace-issues/{$issue->id}/respond"), ['response' => 'Trying to answer for another company'])->assertNotFound();

        $this->actingAs($this->buyer)->get($this->url("/buyer/orders/{$order->id}/tracking"))->assertSee('PickSell is reviewing');
    }

    /** @return array{0: Order, 1: Parcel} an order whose waybill is booked with Pickrush */
    private function shippedOrder(float $price = 500): array
    {
        $product = Product::create(['seller_id' => $this->seller->id, 'name' => 'Canvas Tote', 'category' => 'Fashion', 'price' => $price, 'stock' => 10, 'status' => 'active', 'weight_kg' => 1]);
        $cart = Cart::firstOrCreate(['buyer_id' => $this->buyer->id]);
        $item = CartItem::create(['cart_id' => $cart->id, 'product_id' => $product->id, 'quantity' => 1]);
        $this->actingAs($this->buyer)->post($this->url('/buyer/cart/checkout'), ['item_ids' => [$item->id]])->assertSessionHasNoErrors();
        $order = Order::latest('id')->firstOrFail();
        $this->actingAs($this->seller)->patch($this->url("/seller/orders/{$order->id}/pack"));
        $this->actingAs($this->seller)->post($this->url("/seller/orders/{$order->id}/waybill"))->assertSessionHasNoErrors();
        $this->actingAs($this->seller)->patch($this->url("/seller/orders/{$order->id}/handover"))->assertSessionHasNoErrors();

        return [$order->fresh(), PicksellBridge::parcelFor($order)];
    }

    /** The station receives the parcel, holds it, then the manager marks it lost. */
    private function lose(Parcel $parcel): void
    {
        $ledger = app(ParcelLedger::class);
        $station = $this->station('041029000');
        foreach ([ParcelStatus::AtOriginHub, ParcelStatus::OnHold, ParcelStatus::Lost] as $status) {
            $ledger->record($parcel->fresh(), $status, $station);
        }
    }

    private function url(string $path): string
    {
        return rtrim(config('app.url'), '/').$path;
    }

    private function user(string $role, array $extra = []): User
    {
        static $n = 0;
        $n++;

        return User::create(array_merge([
            'role' => $role, 'status' => 'approved', 'first_name' => ucfirst($role), 'last_name' => "Issue{$n}", 'sex' => 'Female',
            'email' => "{$role}{$n}@issues.test", 'password' => bcrypt('secret-password'), 'contact_no' => '09170000000',
            'birthday' => '1995-01-01', 'age' => 30, 'province' => 'Batangas', 'municipality' => 'City of Tanauan', 'barangay' => 'Altura Bata',
            'house_no' => '12', 'street' => 'Rizal St',
        ], $extra));
    }
}
