<?php

namespace Tests\Feature;

use App\Auth\Permission;
use App\Models\AuditLog;
use App\Models\BranchRider;
use App\Models\LogisticsBranch;
use App\Models\Municipality;
use App\Models\Order;
use App\Models\Refund;
use App\Models\ReturnRequest;
use App\Models\User;
use App\Support\AdminNavigation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminNavigationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = $this->user('admin');
    }

    private function user(string $role, string $status = 'approved'): User
    {
        static $n = 0;
        $n++;

        return User::create([
            'first_name' => ucfirst($role), 'last_name' => "Nav{$n}", 'email' => "{$role}{$n}@nav.test",
            'password' => bcrypt('password'), 'role' => $role, 'status' => $status, 'sex' => 'Male',
            'contact_no' => '09171234567', 'birthday' => '1990-01-01', 'age' => 35,
            'province' => 'Metro Manila', 'municipality' => 'Quezon City', 'barangay' => 'Bahay',
        ]);
    }

    private function order(string $status, array $overrides = []): Order
    {
        static $n = 0;
        $n++;

        return Order::create(array_merge([
            'order_number' => "ORD-NAV-{$n}", 'buyer_id' => $this->buyer->id, 'seller_id' => $this->seller->id,
            'product_name' => "Item {$n}", 'quantity' => 1, 'amount' => 500, 'commission' => 50, 'status' => $status,
        ], $overrides));
    }

    private User $buyer;
    private User $seller;

    private function seedMarketplace(): array
    {
        $this->buyer = $this->user('buyer');
        $this->seller = $this->user('seller');
        $municipality = Municipality::create(['province' => 'Metro Manila', 'name' => 'Pasig']);
        $branch = LogisticsBranch::create(['municipality_id' => $municipality->id, 'name' => 'Pasig Hub', 'status' => 'active']);
        $rider = $this->user('courier');
        BranchRider::create(['branch_id' => $branch->id, 'user_id' => $rider->id, 'status' => 'active']);

        $stale = $this->order('at_sorting_center', ['origin_branch_id' => $branch->id, 'destination_branch_id' => $branch->id]);
        Order::whereKey($stale->id)->update(['updated_at' => now()->subHours(72)]);
        $out = $this->order('out_for_delivery', ['courier_id' => $rider->id, 'destination_branch_id' => $branch->id, 'waybill_number' => 'WB-NAV-1']);
        $this->order('placed');
        $completed = $this->order('completed', ['delivered_at' => now()]);

        $return = ReturnRequest::create([
            'order_id' => $completed->id, 'buyer_id' => $this->buyer->id, 'seller_id' => $this->seller->id,
            'reason' => 'damaged', 'details' => 'Broken', 'status' => 'refund_due', 'refund_amount' => 500, 'refund_due_at' => now(),
        ]);
        Refund::create([
            'order_id' => $completed->id, 'return_request_id' => $return->id,
            'requested_by' => $this->buyer->id, 'amount' => 500,
            'commission_reversal' => 50, 'seller_adjustment' => 450,
            'status' => 'approved', 'approved_at' => now(),
        ]);

        return compact('branch', 'rider', 'stale', 'out', 'completed', 'return');
    }

    public function test_sidebar_follows_the_target_structure_in_order(): void
    {
        $html = $this->actingAs($this->admin)->get('/admin/dashboard')->assertOk()->getContent();

        $expected = [];
        foreach (AdminNavigation::groups() as $group) {
            $expected[] = '>' . $group['label'] . '</div>';
            foreach ($group['items'] as $item) {
                $expected[] = '<span class="nav-label">' . e($item['label']) . '</span>';
            }
        }
        $this->assertSame([
            'Overview' => ['Dashboard', 'Notifications'],
            'Marketplace' => ['Orders', 'Products', 'Seller Compliance'],
            'Users' => ['Registrations', 'User Accounts'],
            'Operations' => ['Logistics', 'Sorting Center', 'Rider Assignment'],
            'Customer Care' => ['Complaints & Disputes', 'Returns', 'Refunds'],
            'Finance' => ['Commission', 'Financial Reports'],
            'Analytics' => ['Reports'],
            'System' => ['Platform Settings', 'Audit Logs', 'Chat / Messaging'],
            'Account' => ['My Account'],
        ], collect(AdminNavigation::groups())->mapWithKeys(fn ($g) => [$g['label'] => array_column($g['items'], 'label')])->all());

        $position = -1;
        foreach ($expected as $needle) {
            $found = strpos($html, $needle, $position + 1);
            $this->assertNotFalse($found, "Sidebar is missing {$needle}");
            $this->assertGreaterThan($position, $found, "Sidebar item out of order: {$needle}");
            $position = $found;
        }
    }

    public function test_each_nav_item_uses_the_same_permission_as_its_route(): void
    {
        foreach (AdminNavigation::groups() as $group) {
            foreach ($group['items'] as $item) {
                $route = Route::getRoutes()->getByName($item['route']);
                $this->assertNotNull($route, "Route {$item['route']} does not exist.");
                $this->assertContains('can:' . $item['permission'], $route->gatherMiddleware(), "{$item['label']} link and route disagree on permission.");
            }
        }
    }

    public function test_every_page_in_the_navigation_renders_with_data(): void
    {
        $this->seedMarketplace();
        $this->admin->notifications()->create(['id' => Str::uuid(), 'type' => 'App\\Notifications\\AdminActivity', 'data' => json_encode(['message' => 'Registration approved for Test'])]);

        foreach (AdminNavigation::groups() as $group) {
            foreach ($group['items'] as $item) {
                $this->actingAs($this->admin)->get(route($item['route']))->assertOk();
            }
        }
    }

    public function test_narrower_roles_only_see_their_groups(): void
    {
        config(['permissions.roles.admin' => [Permission::DASHBOARD_VIEW, Permission::LOGISTICS_VIEW]]);
        $html = $this->actingAs($this->admin)->get('/admin/dashboard')->assertOk()->getContent();

        $this->assertStringContainsString('<span class="nav-label">Sorting Center</span>', $html);
        foreach (['Marketplace', 'Customer Care', 'Finance', 'System'] as $hiddenGroup) {
            $this->assertStringNotContainsString('>' . $hiddenGroup . '</div>', $html);
        }
        $this->actingAs($this->admin)->get('/admin/orders')->assertForbidden();
    }

    public function test_orders_page_filters_by_stage_and_search(): void
    {
        $this->seedMarketplace();

        $this->actingAs($this->admin)->get('/admin/orders?stage=logistics')->assertOk()
            ->assertSee('WB-NAV-1')->assertDontSee('>Item 3<', false);
        $this->actingAs($this->admin)->get('/admin/orders?search=WB-NAV-1')->assertOk()
            ->assertSee('ORD-NAV')->assertSee('Out for delivery');
    }

    public function test_sorting_center_flags_idle_parcels(): void
    {
        $seeded = $this->seedMarketplace();

        $this->actingAs($this->admin)->get('/admin/logistics/sorting-center?stale=1')->assertOk()
            ->assertSee($seeded['stale']->order_number)->assertSee('Idle');
        $this->actingAs($this->admin)->get('/admin/logistics')->assertOk()->assertSee('Pasig Hub');
        $this->actingAs($this->admin)->get('/admin/logistics/rider-assignment')->assertOk()
            ->assertSee($seeded['rider']->full_name)->assertSee('WB-NAV-1');
    }

    public function test_returns_refunds_and_non_escalated_detail_are_read_only(): void
    {
        $seeded = $this->seedMarketplace();

        $this->actingAs($this->admin)->get('/admin/refunds')->assertOk()->assertSee('₱500.00');
        $this->actingAs($this->admin)->get('/admin/returns?status=refund_due')->assertOk()->assertSee($seeded['completed']->order_number);
        $this->actingAs($this->admin)->get(route('admin.returns.show', $seeded['return']))->assertOk()
            ->assertSee('This return has not been escalated.')->assertDontSee('Resolve dispute');
        $this->actingAs($this->admin)->patch(route('admin.returns.resolve', $seeded['return']), [
            'decision' => 'approve_return', 'admin_notes' => 'x',
        ])->assertStatus(422);
    }

    public function test_notifications_page_and_bell_feed(): void
    {
        $this->admin->notifications()->create(['id' => Str::uuid(), 'type' => 'App\\Notifications\\AdminActivity', 'data' => json_encode(['message' => 'Seller warned'])]);

        $this->actingAs($this->admin)->get('/admin/notifications')->assertOk()->assertSee('Seller warned')->assertSee('Mark all as read');
        $this->actingAs($this->admin)->get('/admin/notifications/feed')->assertOk()->assertHeader('X-Unread-Count', '1')->assertJsonCount(1);
    }

    public function test_audit_log_page_and_export(): void
    {
        $this->actingAs($this->admin)->patch("/admin/users/{$this->admin->id}/status", ['status' => 'suspended'])->assertForbidden();

        $this->actingAs($this->admin)->get('/admin/audit-logs?denied=1&module=authorization&result=denied')->assertOk()
            ->assertSee('authorization.denied')->assertSee('Denied')->assertSee('authorization');

        $response = $this->actingAs($this->admin)->get('/admin/audit-logs/export?denied=1')->assertOk();
        $csv = $response->streamedContent();
        $this->assertStringStartsWith('id,created_at,actor_id', $csv);
        $this->assertStringContainsString('authorization.denied', $csv);
        $this->assertStringContainsString('authorization,authorization.denied,denied', $csv);
        $this->assertSame(1, AuditLog::where('action', 'audit.exported')->count());

        config(['permissions.roles.admin' => [Permission::AUDIT_VIEW]]);
        $this->actingAs($this->admin)->get('/admin/audit-logs')->assertOk()->assertDontSee('Export CSV');
        $this->actingAs($this->admin)->get('/admin/audit-logs/export')->assertForbidden();
    }
}
