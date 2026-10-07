<?php

namespace Tests\Feature;

use App\Auth\Permission;
use App\Models\AuditLog;
use App\Models\Complaint;
use App\Models\LogisticsBranch;
use App\Models\Municipality;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\Product;
use App\Models\ReturnRequest;
use App\Models\User;
use App\Notifications\OrderChangedByAdminNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/** Module 6 — Orders: search, full lifecycle, guarded admin overrides, audit, finance figures. */
class AdminOrderControlTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $buyer;
    private User $seller;
    private User $courier;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        config(['app.platform_commission_rate' => 10.0]);
        $this->admin = $this->user('admin');
        $this->buyer = $this->user('buyer');
        $this->seller = $this->user('seller', ['business_name' => 'Tote House']);
        $this->courier = $this->user('courier');
        $this->product = Product::create(['seller_id' => $this->seller->id, 'name' => 'Canvas Tote', 'category' => 'Fashion', 'price' => 500, 'stock' => 5, 'status' => 'active']);
    }

    private function user(string $role, array $overrides = []): User
    {
        static $n = 0;
        $n++;

        return User::create(array_merge([
            'first_name' => ucfirst($role), 'last_name' => "Ord{$n}", 'email' => "{$role}{$n}@ord.test",
            'password' => bcrypt('password'), 'role' => $role, 'status' => 'approved', 'sex' => 'Male',
            'contact_no' => '09171234567', 'birthday' => '1990-01-01', 'age' => 35,
            'province' => 'Metro Manila', 'municipality' => 'Pasig', 'barangay' => 'San Miguel',
        ], $overrides));
    }

    private function order(string $status = 'placed', array $overrides = []): Order
    {
        static $n = 0;
        $n++;

        return Order::create(array_merge([
            'order_number' => "ORD-CTL-{$n}", 'product_id' => $this->product->id, 'buyer_id' => $this->buyer->id,
            'seller_id' => $this->seller->id, 'product_name' => 'Canvas Tote', 'quantity' => 2,
            'amount' => 1000, 'commission' => 100, 'status' => $status,
        ], $overrides));
    }

    private function override(Order $order, string $to, array $overrides = [])
    {
        return $this->actingAs($this->admin)->patch(route('admin.orders.status', $order), array_merge([
            'status' => $to, 'reason' => 'Seller confirmed by phone that the parcel was handed over.', 'confirm' => '1',
        ], $overrides));
    }

    // Every order is searchable --------------------------------------------------------------

    public function test_every_order_is_searchable_and_filterable(): void
    {
        $municipality = Municipality::create(['province' => 'Metro Manila', 'name' => 'Pasig']);
        $hub = LogisticsBranch::create(['municipality_id' => $municipality->id, 'name' => 'Pasig Hub', 'status' => 'active']);
        $otherSeller = $this->user('seller', ['business_name' => 'Gadget Hub']);
        $a = $this->order('out_for_delivery', ['courier_id' => $this->courier->id, 'destination_branch_id' => $hub->id, 'waybill_number' => 'WB-FIND-ME']);
        $b = $this->order('placed', ['seller_id' => $otherSeller->id, 'product_name' => 'Speaker']);
        $old = $this->order('completed');
        Order::whereKey($old->id)->update(['created_at' => now()->subDays(40)]);

        $see = fn (array $query, Order $yes, Order $no) => $this->actingAs($this->admin)->get(route('admin.orders', $query))->assertOk()
            ->assertSee($yes->order_number)->assertDontSee($no->order_number);

        $see(['search' => 'WB-FIND-ME'], $a, $b);
        $see(['search' => (string) $b->id], $b, $a);
        $see(['status' => 'out_for_delivery'], $a, $b);
        $see(['seller' => $otherSeller->id], $b, $a);
        $see(['buyer' => $this->buyer->id], $a, $this->order('placed', ['buyer_id' => $this->user('buyer')->id]));
        $see(['courier' => $this->courier->id], $a, $b);
        $see(['branch' => $hub->id], $a, $b);
        $see(['date' => '30d'], $a, $old);

        $this->actingAs($this->admin)->get(route('admin.orders'))->assertSeeInOrder(['Order', 'Buyer', 'Seller', 'Amount', 'Status', 'Courier', 'Updated', 'Action']);
    }

    // Complete lifecycle visible -------------------------------------------------------------

    public function test_portal_transitions_stamp_lifecycle_and_history_automatically(): void
    {
        $order = $this->order('placed');
        $this->actingAs($this->seller)->patch(route('seller.orders.pack', $order))->assertRedirect();
        $this->actingAs($this->seller)->patch(route('seller.orders.handover', $order))->assertRedirect();

        $order->refresh();
        $this->assertNotNull($order->preparing_at);
        $this->assertNotNull($order->ready_for_pickup_at);
        $this->assertSame(
            [[null, 'placed', 'system'], ['placed', 'preparing', 'seller'], ['preparing', 'ready_for_pickup', 'seller']],
            $order->statusHistories->map(fn ($h) => [$h->from_status, $h->to_status, $h->source])->all(),
        );
        $this->assertSame($this->seller->id, $order->statusHistories->last()->changed_by);
    }

    public function test_detail_page_shows_summary_lifecycle_sorting_courier_returns_and_audit(): void
    {
        $order = $this->order('placed');
        $order->update(['status' => 'preparing']);
        $order->update(['status' => 'ready_for_pickup', 'waybill_number' => 'WB-DETAIL']);
        $order->update(['status' => 'picked_up']);
        $order->update(['status' => 'at_sorting_center']);
        $order->update(['status' => 'assigned_to_rider', 'courier_id' => $this->courier->id]);
        $order->update(['status' => 'out_for_delivery']);
        $order->update(['status' => 'delivered']);
        $order->update(['status' => 'completed']);
        ReturnRequest::create(['order_id' => $order->id, 'buyer_id' => $this->buyer->id, 'seller_id' => $this->seller->id,
            'reason' => 'damaged', 'details' => 'Torn strap', 'status' => 'refund_due', 'refund_amount' => 1000, 'refund_due_at' => now()]);
        Complaint::create(['filed_by' => $this->buyer->id, 'against_user_id' => $this->seller->id, 'subject' => 'Torn strap on arrival', 'details' => 'x', 'status' => 'open']);

        $this->actingAs($this->admin)->get(route('admin.orders.show', $order))->assertOk()
            ->assertSeeInOrder(['Order ' . $order->order_number, 'Buyer', 'Seller', 'Item', 'Order amount', 'Platform commission', 'Net to seller',
                'Fulfillment timeline', 'Status history', 'Sorting events', 'Courier assignment', 'Complaints', 'Returns &amp; refunds', 'Audit history'], false)
            ->assertSee('WB-DETAIL')->assertSee($this->courier->full_name)
            ->assertSee('Skipped')                    // confirmed / sorted were never reached
            ->assertDontSee('@else')                  // no leaked Blade directives
            ->assertSee('Torn strap on arrival')->assertSee('Refund due')->assertSee('₱1,000.00');
    }

    // Overrides: permission, transitions, reason, audit -----------------------------------

    public function test_admin_override_requires_permission(): void
    {
        $order = $this->order('ready_for_pickup');
        config(['permissions.roles.admin' => [Permission::ORDERS_VIEW, Permission::ORDERS_MANAGE]]);

        $this->actingAs($this->admin)->get(route('admin.orders.show', $order))->assertOk()->assertDontSee('Override status</button>', false);
        $this->override($order, 'picked_up')->assertForbidden();
        $this->assertSame('ready_for_pickup', $order->fresh()->status);
    }

    public function test_invalid_transitions_are_rejected(): void
    {
        $placed = $this->order('placed');
        $this->override($placed, 'delivered')->assertStatus(409);           // skips the lifecycle
        $this->override($placed, 'ready_for_pickup')->assertForbidden();    // needs a waybill from the seller

        $completed = $this->order('completed');
        $this->override($completed, 'cancelled')->assertStatus(409);        // terminal
        $sorting = $this->order('at_sorting_center');
        $this->override($sorting, 'assigned_to_rider')->assertForbidden();  // needs a rider from logistics
        $this->override($sorting, 'teleported')->assertStatus(409);

        $this->assertSame(['placed', 'completed', 'at_sorting_center'], [$placed->fresh()->status, $completed->fresh()->status, $sorting->fresh()->status]);
        $this->assertSame(0, AuditLog::where('action', 'order.status_overridden')->count());
    }

    public function test_override_requires_reason_and_confirmation(): void
    {
        $order = $this->order('ready_for_pickup');

        $this->override($order, 'picked_up', ['reason' => ''])->assertSessionHasErrors('reason');
        $this->override($order, 'picked_up', ['reason' => 'too short'])->assertSessionHasErrors('reason');
        $this->override($order, 'picked_up', ['confirm' => null])->assertSessionHasErrors('confirm');
        $this->assertSame('ready_for_pickup', $order->fresh()->status);
    }

    public function test_valid_override_is_applied_recorded_audited_and_notified(): void
    {
        $order = $this->order('ready_for_pickup');

        $this->override($order, 'picked_up')->assertSessionHasNoErrors();

        $order->refresh();
        $this->assertSame('picked_up', $order->status);
        $this->assertNotNull($order->picked_up_at);
        $history = $order->statusHistories->last();
        $this->assertSame(['ready_for_pickup', 'picked_up', 'admin', $this->admin->id, 'Seller confirmed by phone that the parcel was handed over.'],
            [$history->from_status, $history->to_status, $history->source, $history->changed_by, $history->reason]);

        $log = AuditLog::where('action', 'order.status_overridden')->sole();
        $this->assertSame(Permission::ORDERS_OVERRIDE_STATUS, $log->permission);
        $this->assertSame(['status' => ['from' => 'ready_for_pickup', 'to' => 'picked_up']], $log->changes);
        $this->assertSame('Seller confirmed by phone that the parcel was handed over.', $log->metadata['reason']);

        Notification::assertSentTo([$this->buyer, $this->seller], OrderChangedByAdminNotification::class);
        $this->actingAs($this->admin)->get(route('admin.orders.show', $order))->assertSee('order.status_overridden');
    }

    public function test_exception_resolutions_apply_side_effects(): void
    {
        $failed = $this->order('delivery_failed', ['courier_id' => $this->courier->id]);
        $this->actingAs($this->admin)->patch(route('admin.orders.resolve', $failed), ['action' => 'reattempt_delivery', 'reason' => 'Buyer was away; reschedule delivery.'])
            ->assertSessionHasNoErrors();
        $this->assertSame(['at_sorting_center', null], [$failed->fresh()->status, $failed->fresh()->courier_id]);

        $returned = $this->order('delivery_failed');
        $this->actingAs($this->admin)->patch(route('admin.orders.resolve', $returned), ['action' => 'return_to_seller', 'reason' => 'Buyer refused the parcel twice.']);
        $this->assertSame('returned', $returned->fresh()->status);
        $this->assertSame(7, $this->product->fresh()->stock, 'returned quantity goes back into stock');

        $placed = $this->order('placed');
        $this->actingAs($this->admin)->patch(route('admin.orders.resolve', $placed), ['action' => 'return_to_seller', 'reason' => 'Not applicable to placed orders.'])->assertStatus(409);
        $this->actingAs($this->admin)->patch(route('admin.orders.resolve', $placed), ['action' => 'cancel_order', 'reason' => 'Seller closed the shop before shipping.'])->assertSessionHasNoErrors();
        $this->assertSame('cancelled', $placed->fresh()->status);
        $this->assertSame(9, $this->product->fresh()->stock);

        $this->assertSame(3, AuditLog::where('action', 'order.exception_resolved')->count());
        $this->assertSame(Permission::ORDERS_MANAGE, AuditLog::where('action', 'order.exception_resolved')->first()->permission);

        config(['permissions.roles.admin' => [Permission::ORDERS_VIEW, Permission::ORDERS_OVERRIDE_STATUS]]);
        $this->actingAs($this->admin)->patch(route('admin.orders.resolve', $this->order('placed')), ['action' => 'cancel_order', 'reason' => 'No manage permission here.'])->assertForbidden();
    }

    public function test_concurrent_change_is_rejected_under_lock(): void
    {
        $order = $this->order('ready_for_pickup');
        $stale = Order::find($order->id);
        $order->update(['status' => 'picked_up']); // logistics moved it first

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(\App\Services\Orders\OrderAdministrationService::class)->overrideStatus($stale, $this->admin, 'cancelled', 'Cancel the stale order now.');
    }

    // Financial figures come from Finance services ------------------------------------------

    public function test_financial_figures_come_from_the_finance_layer(): void
    {
        $order = $this->order('completed', ['amount' => 1000, 'commission' => 80]); // placed when the rate was 8%
        $money = app(\App\Services\Finance\FinancialSummary::class)->forOrder($order);
        $this->assertSame([1000.0, 80.0, 920.0, 8.0], [
            $money['amount'],
            $money['commission'],
            $money['net_to_seller'],
            $money['commission_rate'],
        ]);

        $this->actingAs($this->admin)->get(route('admin.orders.show', $order))
            ->assertSeeInOrder(['Order amount', '₱1,000.00', 'Platform commission (8%)', '₱80.00', 'Net to seller', '₱920.00'])
            ->assertSee('Commission and rate are recorded when the order is placed.');
    }

    public function test_legacy_statuses_can_be_moved_onto_the_lifecycle(): void
    {
        $legacy = $this->order('placed');
        Order::whereKey($legacy->id)->update(['status' => 'processing']); // pre-lifecycle data

        $this->actingAs($this->admin)->get(route('admin.orders.show', $legacy))->assertOk()->assertSee('Legacy status');
        $this->override($legacy->fresh(), 'delivered')->assertStatus(409);
        $this->override($legacy->fresh(), 'preparing', ['reason' => 'Normalising legacy status after seller check.'])->assertSessionHasNoErrors();
        $this->assertSame('preparing', $legacy->fresh()->status);
    }
}
