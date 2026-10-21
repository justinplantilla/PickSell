<?php

namespace Tests\Feature;

use App\Auth\Permission;
use App\Models\AuditLog;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/** Orders list layout: filter chips, name search, preview drawer, CSV export and selected-row bulk actions. */
class AdminOrderListTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $buyer;
    private User $seller;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->admin = $this->user('admin');
        $this->buyer = $this->user('buyer', ['first_name' => 'Maria', 'last_name' => 'Plantilla']);
        $this->seller = $this->user('seller', ['business_name' => 'Kelly & Co.']);
    }

    public function test_search_matches_buyer_and_seller_names(): void
    {
        $mine = $this->order('placed');
        $other = $this->order('placed', ['buyer_id' => $this->user('buyer', ['first_name' => 'Jake', 'last_name' => 'Woods'])->id,
            'seller_id' => $this->user('seller', ['business_name' => 'Juan Store'])->id]);

        foreach (['Maria Plantilla', 'Plantilla', 'Kelly'] as $term) {
            $this->actingAs($this->admin)->get(route('admin.orders', ['search' => $term]))->assertOk()
                ->assertSee($mine->order_number)->assertDontSee($other->order_number);
        }
    }

    public function test_active_filters_render_as_removable_chips_and_more_filters_shows_a_count(): void
    {
        $this->order('placed');

        $response = $this->actingAs($this->admin)->get(route('admin.orders', ['seller' => $this->seller->id, 'status' => 'placed', 'stage' => 'fulfillment']))
            ->assertOk()
            ->assertSee('Seller: Kelly &amp; Co.', false)
            ->assertSee('Status: Placed')
            ->assertSee('class="list-count-pill">1<', false)
            ->assertSee('Clear all');

        $response->assertSee('href="'.e(route('admin.orders', ['status' => 'placed', 'stage' => 'fulfillment'])).'"', false);
        $response->assertSee('href="'.e(route('admin.orders', ['stage' => 'fulfillment'])).'" class="list-chips-clear"', false);
    }

    public function test_rows_flag_stuck_orders_and_sorted_parcels_without_a_rider(): void
    {
        $stuck = $this->order('preparing');
        Order::whereKey($stuck->id)->update(['updated_at' => now()->subDays(3)]);
        $this->order('sorted');

        $this->actingAs($this->admin)->get(route('admin.orders'))->assertOk()
            ->assertSee('Stuck')
            ->assertSee('No rider assigned')
            ->assertSee('data-preview-url="'.route('admin.orders.preview', $stuck).'"', false);
    }

    public function test_preview_returns_the_order_summary_fragment(): void
    {
        $order = $this->order('ready_for_pickup', ['waybill_number' => 'WB-261009-PREVIEW1']);

        $this->actingAs($this->admin)->get(route('admin.orders.preview', $order))->assertOk()
            ->assertSee($order->order_number)
            ->assertSee('WB-261009-PREVIEW1')
            ->assertSee('Open full order')
            ->assertDontSee('<html', false);
    }

    public function test_preview_requires_order_view_permission(): void
    {
        config(['permissions.roles.admin' => [Permission::DASHBOARD_VIEW]]);

        $this->actingAs($this->admin)->get(route('admin.orders.preview', $this->order('placed')))->assertForbidden();
    }

    public function test_export_streams_filtered_or_selected_orders_and_is_audited(): void
    {
        $a = $this->order('placed', ['product_name' => '=HYPERLINK("x")']);
        $b = $this->order('delivered');

        $filtered = $this->actingAs($this->admin)->get(route('admin.orders.export', ['status' => 'placed']));
        $filtered->assertOk();
        $csv = $filtered->streamedContent();
        $this->assertStringContainsString($a->order_number, $csv);
        $this->assertStringNotContainsString($b->order_number, $csv);
        $this->assertStringContainsString("'=HYPERLINK", $csv, 'Formula-like cells are neutralised.');

        $selected = $this->actingAs($this->admin)->get(route('admin.orders.export', ['order_ids' => "{$b->id}"]))->streamedContent();
        $this->assertStringContainsString($b->order_number, $selected);
        $this->assertStringNotContainsString($a->order_number, $selected);

        $this->assertSame(2, AuditLog::where('action', 'orders.exported')->count());
        $this->assertSame([$b->id], AuditLog::where('action', 'orders.exported')->latest('id')->first()->metadata['order_ids']);
    }

    public function test_export_requires_the_export_permission(): void
    {
        config(['permissions.roles.admin' => [Permission::DASHBOARD_VIEW, Permission::ORDERS_VIEW]]);

        $this->actingAs($this->admin)->get(route('admin.orders.export'))->assertForbidden();
        $this->actingAs($this->admin)->get(route('admin.orders'))->assertOk()->assertDontSee('admin/orders/export', false);
    }

    public function test_bulk_status_applies_only_to_selected_orders(): void
    {
        $picked = $this->order('ready_for_pickup');
        $untouched = $this->order('ready_for_pickup');

        $this->actingAs($this->admin)->post(route('admin.orders.bulk-status'), [
            'order_ids' => [$picked->id],
            'matching_count' => 1,
            'to_status' => 'picked_up',
            'reason' => 'Courier confirmed collection by phone.',
            'confirm' => '1',
        ])->assertRedirect()->assertSessionHas('success', '1 order(s) updated.');

        $this->assertSame('picked_up', $picked->fresh()->status);
        $this->assertSame('ready_for_pickup', $untouched->fresh()->status);
    }

    public function test_bulk_status_rejects_a_stale_selection_count(): void
    {
        $order = $this->order('ready_for_pickup');

        $this->actingAs($this->admin)->post(route('admin.orders.bulk-status'), [
            'order_ids' => [$order->id, 999999],
            'matching_count' => 2,
            'to_status' => 'picked_up',
            'reason' => 'Courier confirmed collection by phone.',
            'confirm' => '1',
        ])->assertRedirect()->assertSessionHas('warning');

        $this->assertSame('ready_for_pickup', $order->fresh()->status);
    }

    private function user(string $role, array $overrides = []): User
    {
        static $n = 0;
        $n++;

        return User::create(array_merge([
            'first_name' => ucfirst($role), 'last_name' => "List{$n}", 'email' => "{$role}{$n}@list.test",
            'password' => bcrypt('password'), 'role' => $role, 'status' => 'approved', 'sex' => 'Male',
            'contact_no' => '09171234567', 'birthday' => '1990-01-01', 'age' => 35,
            'province' => 'Metro Manila', 'municipality' => 'Pasig', 'barangay' => 'San Miguel',
        ], $overrides));
    }

    private function order(string $status, array $overrides = []): Order
    {
        static $n = 0;
        $n++;

        return Order::create(array_merge([
            'order_number' => 'ORD-LIST-'.str_pad((string) $n, 3, '0', STR_PAD_LEFT), 'buyer_id' => $this->buyer->id,
            'seller_id' => $this->seller->id, 'product_name' => 'Canvas Tote', 'quantity' => 1,
            'amount' => 500, 'commission' => 50, 'status' => $status,
        ], $overrides));
    }
}
