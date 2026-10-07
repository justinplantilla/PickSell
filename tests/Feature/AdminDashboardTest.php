<?php

namespace Tests\Feature;

use App\Auth\Permission;
use App\Models\BranchRider;
use App\Models\Complaint;
use App\Models\LogisticsBranch;
use App\Models\Municipality;
use App\Models\Order;
use App\Models\PlatformSetting;
use App\Models\ReturnRequest;
use App\Models\User;
use App\Services\Admin\AdminDashboardService;
use App\Services\Finance\FinancialLedgerService;
use App\Services\Finance\FinancialSummary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** Module 1 — Admin dashboard as an operational command center. */
class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $buyer;

    private User $seller;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.platform_commission_rate' => 10.0]);
        $this->admin = $this->user('admin');
        $this->buyer = $this->user('buyer');
        $this->seller = $this->user('seller');
    }

    private function user(string $role, string $status = 'approved', array $overrides = []): User
    {
        static $n = 0;
        $n++;

        return User::create(array_merge([
            'first_name' => ucfirst($role), 'last_name' => "Dash{$n}", 'email' => "{$role}{$n}@dash.test",
            'password' => bcrypt('password'), 'role' => $role, 'status' => $status, 'sex' => 'Male',
            'contact_no' => '09171234567', 'birthday' => '1990-01-01', 'age' => 35,
            'province' => 'Metro Manila', 'municipality' => 'Quezon City', 'barangay' => 'Bahay',
        ], $overrides));
    }

    private function order(string $status, array $overrides = [], array $timestamps = []): Order
    {
        static $n = 0;
        $n++;
        $order = Order::create(array_merge([
            'order_number' => "ORD-DASH-{$n}", 'buyer_id' => $this->buyer->id, 'seller_id' => $this->seller->id,
            'product_name' => "Dash item {$n}", 'quantity' => 1, 'amount' => 1000, 'commission' => 100, 'status' => $status,
        ], $overrides));
        if ($timestamps) {
            Order::whereKey($order->id)->update($timestamps);
        }

        return $order->fresh();
    }

    /** Read a KPI's rendered value from the dashboard HTML. */
    private function kpi(string $html, string $key): ?int
    {
        return preg_match('/data-kpi="'.$key.'".*?stat-card-num[^>]*>([\d,]+)</s', $html, $m) ? (int) str_replace(',', '', $m[1]) : null;
    }

    private function seedOperations(): array
    {
        $municipality = Municipality::create(['province' => 'Metro Manila', 'name' => 'Pasig']);
        $branch = LogisticsBranch::create(['municipality_id' => $municipality->id, 'name' => 'Pasig Hub', 'status' => 'active']);
        $rider = $this->user('courier');
        BranchRider::create(['branch_id' => $branch->id, 'user_id' => $rider->id, 'status' => 'active']);
        $inactiveRider = $this->user('courier');
        BranchRider::create(['branch_id' => $branch->id, 'user_id' => $inactiveRider->id, 'status' => 'inactive']);

        return [
            'today' => $this->order('placed'),
            'yesterday' => $this->order('preparing', [], ['created_at' => now()->subDay(), 'updated_at' => now()->subHour()]),
            'stuck' => $this->order('at_sorting_center', [], ['created_at' => now()->subDays(4), 'updated_at' => now()->subDays(3)]),
            'failed' => $this->order('delivery_failed', [], ['created_at' => now()->subDays(2)]),
            'unassigned' => $this->order('sorted'),
            'onDelivery' => $this->order('out_for_delivery', ['courier_id' => $rider->id]),
            'completed' => $this->order('completed', ['amount' => 2500]),
            'pendingSeller' => $this->user('seller', 'pending'),
            'pendingCourier' => $this->user('courier', 'pending'),
            'rider' => $rider,
        ];
    }

    public function test_kpis_reflect_current_database_state(): void
    {
        $this->seedOperations();
        $html = $this->actingAs($this->admin)->get('/admin/dashboard')->assertOk()->getContent();

        $this->assertSame(4, $this->kpi($html, 'orders_today'), 'placed, sorted, out for delivery and completed were created today');
        $this->assertSame(1, $this->kpi($html, 'pending_approvals'), 'couriers are reviewed by Logistics, not Admin');
        $this->assertSame(1, $this->kpi($html, 'active_riders'), 'inactive branch riders do not count');
        $this->assertSame(3, $this->kpi($html, 'issues'), 'stuck + failed + unassigned');

        // Live aggregation: a change in the database shows on the next load.
        $this->user('buyer', 'pending');
        Order::where('status', 'sorted')->update(['courier_id' => User::where('role', 'courier')->first()->id]);
        $html = $this->actingAs($this->admin)->get('/admin/dashboard')->getContent();
        $this->assertSame(2, $this->kpi($html, 'pending_approvals'));
        $this->assertSame(2, $this->kpi($html, 'issues'));
    }

    public function test_each_kpi_opens_its_filtered_module(): void
    {
        $seeded = $this->seedOperations();
        $html = $this->actingAs($this->admin)->get('/admin/dashboard')->getContent();
        preg_match_all('/<a href="([^"]+)" class="stat-card command-kpi[^"]*" data-kpi="([a-z_]+)"/', $html, $links, PREG_SET_ORDER);
        $urls = collect($links)->mapWithKeys(fn ($m) => [$m[2] => html_entity_decode($m[1])]);
        $this->assertSame(['orders_today', 'pending_approvals', 'active_riders', 'issues'], $urls->keys()->all());

        $this->actingAs($this->admin)->get($urls['orders_today'])->assertOk()
            ->assertSee($seeded['today']->order_number)->assertDontSee($seeded['yesterday']->order_number)->assertSee('Placed today');
        $this->actingAs($this->admin)->get($urls['pending_approvals'])->assertOk()->assertSee($seeded['pendingSeller']->email);
        $this->actingAs($this->admin)->get($urls['active_riders'])->assertOk()->assertSee($seeded['rider']->full_name);
        $this->actingAs($this->admin)->get($urls['issues'])->assertOk()
            ->assertSee($seeded['stuck']->order_number)->assertSee($seeded['failed']->order_number)->assertSee($seeded['unassigned']->order_number)
            ->assertDontSee($seeded['today']->order_number)->assertDontSee($seeded['onDelivery']->order_number);
    }

    public function test_lifecycle_stages_count_and_link_to_orders(): void
    {
        $seeded = $this->seedOperations();
        $html = $this->actingAs($this->admin)->get('/admin/dashboard')->getContent();

        foreach (['Placed' => 1, 'Preparing' => 1, 'Sorting' => 2, 'Delivery' => 1] as $label => $count) {
            $this->assertMatchesRegularExpression('/<strong class="oversight-number">'.$count.'<\/strong>\s*<span>'.$label.'<\/span>/', $html);
        }
        $this->actingAs($this->admin)->get(route('admin.orders', ['status' => 'picked_up,at_sorting_center,sorted']))->assertOk()
            ->assertSee($seeded['stuck']->order_number)->assertSee($seeded['unassigned']->order_number)->assertDontSee($seeded['today']->order_number);
    }

    public function test_pending_actions_link_to_their_queues_and_show_clear_when_empty(): void
    {
        $this->user('seller', 'pending');
        Complaint::create(['filed_by' => $this->buyer->id, 'against_user_id' => $this->seller->id, 'subject' => 'Late', 'details' => 'x', 'status' => 'open']);

        $response = $this->actingAs($this->admin)->get('/admin/dashboard')->assertOk();
        $response->assertSee('href="'.route('admin.registrations', ['status' => 'pending']).'"', false)->assertSee('Review applications');
        $response->assertSee('href="'.route('admin.complaints', ['status' => 'open']).'"', false)->assertSee('Review complaints');
        $response->assertDontSee('Resolve disputes')->assertSee('Clear');

        $completed = $this->order('completed');
        ReturnRequest::create(['order_id' => $completed->id, 'buyer_id' => $this->buyer->id, 'seller_id' => $this->seller->id,
            'reason' => 'damaged', 'details' => 'x', 'status' => 'rejected', 'dispute_status' => 'open']);
        $this->actingAs($this->admin)->get('/admin/dashboard')->assertSee('href="'.route('admin.disputes', ['status' => 'open']).'"', false);
    }

    public function test_exceptions_are_visible_with_links(): void
    {
        $this->seedOperations();

        $this->actingAs($this->admin)->get('/admin/dashboard')->assertOk()
            ->assertSee('Operational exceptions')
            ->assertSee('Stuck orders')->assertSee('href="'.route('admin.orders', ['stuck' => 1]).'"', false)
            ->assertSee('Failed deliveries')->assertSee('href="'.route('admin.orders', ['status' => 'delivery_failed']).'"', false)
            ->assertSee('Unassigned parcels')->assertSee('href="'.route('admin.logistics.sorting', ['status' => 'sorted']).'"', false);
    }

    public function test_financial_figures_come_from_the_finance_layer(): void
    {
        $completed = $this->order('completed', ['amount' => 2500, 'commission' => 250]);
        $secondCompleted = $this->order('completed', ['amount' => 1500, 'commission' => 150], ['created_at' => now()->startOfMonth()->addHour()]);
        app(FinancialLedgerService::class)->postCompletedOrder($completed);
        app(FinancialLedgerService::class)->postCompletedOrder($secondCompleted);
        app(FinancialLedgerService::class)->postCompletedOrder($completed);
        $this->assertDatabaseCount('financial_transactions', 6);
        $this->order('delivered', ['amount' => 9999]); // not completed: excluded everywhere

        $month = app(FinancialSummary::class)->forPeriod(now()->startOfMonth(), now());
        $this->assertSame(4000.0, $month['gross_sales']);
        $this->assertSame(400.0, $month['commission']);

        $this->actingAs($this->admin)->get('/admin/dashboard')->assertOk()
            ->assertSee('₱4,000.00')->assertSee('₱400.00')->assertSee('₱3,600.00')->assertDontSee('₱9,999');
        $this->actingAs($this->admin)->get(route('admin.reports', ['tab' => 'financial', 'from' => now()->startOfMonth()->toDateString(), 'to' => now()->toDateString()]))
            ->assertOk()->assertSee('₱4,000');

        // A commission-rate change applies to new orders without rewriting completed ones.
        PlatformSetting::set('commission_rate', '15');
        config(['app.platform_commission_rate' => 15.0]);
        $this->actingAs($this->admin)->get('/admin/dashboard')->assertSee('₱400.00')->assertSee('15% commission');
    }

    public function test_commission_page_remains_available_when_finance_migrations_are_missing(): void
    {
        $this->order('completed', ['amount' => 1250, 'commission' => 125]);
        Schema::partialMock()
            ->shouldReceive('hasTable')
            ->with('financial_transactions')
            ->once()
            ->andReturnFalse();
        Schema::shouldReceive('hasTable')
            ->with('commission_rate_histories')
            ->once()
            ->andReturnFalse();

        $this->actingAs($this->admin)
            ->get('/admin/commission')
            ->assertOk()
            ->assertSee('Financial ledger migration is not applied.')
            ->assertSee('₱1,250.00')
            ->assertSee('₱125.00')
            ->assertSee('No rate changes have been recorded.');
    }

    public function test_registration_trend_uses_real_monthly_counts(): void
    {
        $this->user('buyer', 'approved', [])->forceFill(['created_at' => now()->subMonths(2)->startOfMonth()->addDay()])->save();

        $html = $this->actingAs($this->admin)->get('/admin/dashboard')->getContent();
        preg_match("/data-values='([^']+)'/", $html, $values);
        preg_match("/data-labels='([^']+)'/", $html, $labels);
        $values = json_decode(html_entity_decode($values[1]), true);
        $labels = json_decode(html_entity_decode($labels[1]), true);

        $this->assertCount(6, $values);
        $this->assertSame(now()->format('M Y'), end($labels));
        $this->assertSame(1, $values[3], 'the back-dated buyer');
        $this->assertSame(2, $values[5], 'buyer + seller created in setUp (the admin is not counted)');
    }

    public function test_authorization_limits_the_dashboard_to_permitted_sections(): void
    {
        $this->seedOperations();

        config(['permissions.roles.admin' => [Permission::COMPLAINTS_VIEW]]);
        $this->actingAs($this->admin)->get('/admin/dashboard')->assertForbidden();

        config(['permissions.roles.admin' => [Permission::DASHBOARD_VIEW, Permission::LOGISTICS_VIEW]]);
        $html = $this->actingAs($this->admin)->get('/admin/dashboard')->assertOk()->getContent();

        $this->assertSame(1, $this->kpi($html, 'active_riders'));
        $this->assertSame(1, $this->kpi($html, 'issues'), 'only unassigned parcels are visible to logistics.view');
        $this->assertNull($this->kpi($html, 'orders_today'));
        $this->assertNull($this->kpi($html, 'pending_approvals'));
        foreach (['Marketplace lifecycle', 'Pending actions', 'Finance snapshot', 'Oldest pending applications', 'Active accounts', 'Stuck orders'] as $hidden) {
            $this->assertStringNotContainsString($hidden, $html);
        }
        $this->assertStringContainsString('Unassigned parcels', $html);
    }

    public function test_sidebar_badge_shows_pending_approvals_on_every_admin_page(): void
    {
        $this->user('seller', 'pending');
        $this->user('logistics', 'pending');
        $this->user('courier', 'pending');

        $this->assertSame(2, app(AdminDashboardService::class)->pendingApprovals());
        $this->actingAs($this->admin)->get('/admin/products')->assertOk()->assertSee('<span class="nav-badge" aria-label="2 pending">2</span>', false);
    }
}
