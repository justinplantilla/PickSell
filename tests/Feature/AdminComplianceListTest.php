<?php

namespace Tests\Feature;

use App\Auth\Permission;
use App\Models\AuditLog;
use App\Models\ComplianceCase;
use App\Models\Product;
use App\Models\User;
use App\Services\Admin\ComplianceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/** Compliance list layout: SQL risk score, risk summary cards and filter, signals, preview drawer and export. */
class AdminComplianceListTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        Mail::fake();
        $this->admin = $this->user('admin');
    }

    public function test_sql_risk_score_orders_sellers_and_matches_the_service_score(): void
    {
        $clean = $this->seller('Clean Shop', 'Books');
        $medium = $this->seller('Juan Store', 'Electronics');
        $this->offCategory($medium, 22);                    // capped at 3 points
        $high = $this->seller('Risky Bags', 'Fashion');
        $this->openCase($high, 'critical');                 // 5 points
        $this->offCategory($high, 1);                       // +1

        $this->actingAs($this->admin)->get(route('admin.compliance'))->assertOk()
            ->assertSeeInOrder(['Risky Bags', 'Juan Store', 'Clean Shop'])
            ->assertSee('risk-high', false)->assertSee('risk-medium', false);

        $this->assertSame(['high' => 1, 'medium' => 1, 'low' => 0, 'none' => 1], $this->summary());
        $this->assertSame('high', ComplianceService::risk(5, 0, 1, 0, 0)['level']);
        $this->assertSame('medium', ComplianceService::risk(0, 0, 22, 0, 0)['level']);
    }

    public function test_risk_cards_filter_the_list_and_toggle_off(): void
    {
        $this->seller('Clean Shop', 'Books');
        $medium = $this->seller('Juan Store', 'Electronics');
        $this->offCategory($medium, 4);

        $this->actingAs($this->admin)->get(route('admin.compliance', ['risk' => 'medium']))->assertOk()
            ->assertSee('Juan Store')->assertDontSee('Clean Shop')
            ->assertSee('Risk: Medium')
            ->assertSee('href="'.e(route('admin.compliance')).'" class="compliance-summary-card is-medium is-active"', false);

        $this->actingAs($this->admin)->get(route('admin.compliance', ['risk' => 'none']))->assertOk()
            ->assertSee('Clean Shop')->assertDontSee('Juan Store');
    }

    public function test_row_keeps_every_signal_and_explains_the_risk(): void
    {
        $seller = $this->seller('Juan Store', 'Electronics', ['first_name' => 'Juan', 'last_name' => 'Dela Cruz', 'email' => 'juan@shop.test']);
        $this->offCategory($seller, 22);

        $this->actingAs($this->admin)->get(route('admin.compliance'))->assertOk()
            ->assertSeeInOrder(['Risk', 'Seller', 'Off-cat.', 'Cases', 'Warn 90d', 'Status'])
            ->assertSee('Juan Dela Cruz · juan@shop.test')
            ->assertSee('Electronics')
            ->assertSee('22 off-category')
            ->assertSee('href="'.e(route('admin.products', ['seller' => $seller->id])).'"', false)
            ->assertSee('data-preview-url="'.route('admin.compliance.seller.preview', $seller).'"', false);
    }

    public function test_preview_shows_the_breakdown_off_category_products_and_cases(): void
    {
        $seller = $this->seller('Juan Store', 'Electronics');
        $this->offCategory($seller, 2);
        $this->openCase($seller, 'high');

        $this->actingAs($this->admin)->get(route('admin.compliance.seller.preview', $seller))->assertOk()
            ->assertSee('Risk breakdown')
            ->assertSee('Open-case severity points')
            ->assertSee('Off-category item 1')
            ->assertSee('Open cases')
            ->assertSee('Open full review')
            ->assertDontSee('<html', false);
    }

    public function test_preview_rejects_non_sellers(): void
    {
        $this->actingAs($this->admin)->get(route('admin.compliance.seller.preview', $this->user('buyer')))->assertNotFound();
    }

    public function test_export_includes_every_factor_and_is_audited(): void
    {
        $seller = $this->seller('=Sneaky Store', 'Electronics');
        $this->offCategory($seller, 2);

        $csv = $this->actingAs($this->admin)->get(route('admin.compliance.export', ['risk' => 'low']))->assertOk()->streamedContent();

        $this->assertStringContainsString('off_category_listings,open_cases,warnings_90d,admin_archived_products,open_complaints', $csv);
        $this->assertStringContainsString("'=Sneaky Store", $csv);
        $this->assertSame(['risk' => 'low'], AuditLog::where('action', 'compliance.sellers_exported')->sole()->metadata['filters']);
    }

    public function test_export_requires_the_export_permission(): void
    {
        config(['permissions.roles.admin' => [Permission::DASHBOARD_VIEW, Permission::SELLER_COMPLIANCE_VIEW]]);

        $this->actingAs($this->admin)->get(route('admin.compliance.export'))->assertForbidden();
        $this->actingAs($this->admin)->get(route('admin.compliance'))->assertOk()->assertDontSee('compliance/export', false);
    }

    private function summary(): array
    {
        $response = $this->actingAs($this->admin)->get(route('admin.compliance'));

        return collect(['high', 'medium', 'low', 'none'])->mapWithKeys(fn ($level) => [
            $level => (int) $response->viewData('riskSummary')[$level],
        ])->all();
    }

    private function offCategory(User $seller, int $count): void
    {
        foreach (range(1, $count) as $i) {
            Product::create(['seller_id' => $seller->id, 'name' => "Off-category item {$i}", 'category' => 'Something Else',
                'price' => 100, 'stock' => 5, 'status' => 'active']);
        }
    }

    private function openCase(User $seller, string $severity): void
    {
        ComplianceCase::create(['seller_id' => $seller->id, 'opened_by' => $this->admin->id, 'type' => 'counterfeit',
            'severity' => $severity, 'status' => 'open', 'description' => 'Reported by several buyers.']);
    }

    private function seller(string $business, string $line, array $overrides = []): User
    {
        return $this->user('seller', array_merge(['business_name' => $business, 'line_of_business' => $line], $overrides));
    }

    private function user(string $role, array $overrides = []): User
    {
        static $n = 0;
        $n++;

        return User::create(array_merge([
            'first_name' => ucfirst($role), 'last_name' => "Comp{$n}", 'email' => "{$role}{$n}@comp.test",
            'password' => bcrypt('password'), 'role' => $role, 'status' => 'approved', 'sex' => 'Male',
            'contact_no' => '09171234567', 'birthday' => '1990-01-01', 'age' => 35,
            'province' => 'Metro Manila', 'municipality' => 'Pasig', 'barangay' => 'San Miguel',
        ], $overrides));
    }
}
