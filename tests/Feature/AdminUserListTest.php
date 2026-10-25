<?php

namespace Tests\Feature;

use App\Auth\Permission;
use App\Models\AuditLog;
use App\Models\Order;
use App\Models\User;
use App\Models\UserStatusHistory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/** User list layout: status tabs, consistent ⋯ row menu, shared status dialogs, preview drawer, selected bulk and export. */
class AdminUserListTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->admin = $this->user('admin');
    }

    public function test_status_tabs_count_accounts_and_filter(): void
    {
        $active = $this->user('buyer');
        $this->user('seller');
        $pending = $this->user('buyer', 'pending');
        $this->user('buyer', 'suspended');

        $this->actingAs($this->admin)->get(route('admin.users'))->assertOk()
            ->assertSeeInOrder(['All', '4', 'Active', '2', 'Pending review', '1', 'Suspended', '1', 'Deactivated', '0']);
        $this->actingAs($this->admin)->get(route('admin.users', ['status' => 'pending']))->assertOk()
            ->assertSee($pending->email)->assertDontSee($active->email);
    }

    public function test_row_menu_is_consistent_and_puts_deactivate_last_after_a_divider(): void
    {
        $active = $this->user('buyer');

        $html = $this->actingAs($this->admin)->get(route('admin.users'))->assertOk()->getContent();
        $menu = substr($html, strpos($html, 'Actions for '.$active->full_name));
        $menu = substr($menu, 0, strpos($menu, '</details>'));

        $this->assertMatchesRegularExpression('/View profile.*Suspend….*list-menu-divider.*is-danger.*Deactivate…/s', $menu);
        $this->assertStringNotContainsString('btn-deactivate', $html, 'No always-visible destructive buttons in rows.');
    }

    public function test_pending_accounts_point_to_the_registration_queue_instead_of_deciding_here(): void
    {
        $pending = $this->user('seller', 'pending');

        $this->actingAs($this->admin)->get(route('admin.users'))->assertOk()
            ->assertSee('Review in queue →')
            ->assertSee('href="'.route('admin.registrations.show', $pending).'"', false)
            ->assertDontSee('Review / Decide');
    }

    public function test_status_line_shows_reason_for_suspended_accounts_and_order_value(): void
    {
        $buyer = $this->user('buyer', 'suspended');
        UserStatusHistory::create(['user_id' => $buyer->id, 'changed_by' => $this->admin->id, 'from_status' => 'approved', 'to_status' => 'suspended', 'reason' => 'Repeated chargebacks on three orders.']);
        $seller = $this->user('seller');
        Order::create(['order_number' => 'ORD-ULIST-1', 'buyer_id' => $buyer->id, 'seller_id' => $seller->id, 'product_name' => 'x',
            'quantity' => 1, 'amount' => 1250, 'commission' => 125, 'status' => 'completed']);

        $this->actingAs($this->admin)->get(route('admin.users'))->assertOk()
            ->assertSee('Repeated chargebacks on three orders.')
            ->assertSee('₱1,250.00');
    }

    public function test_status_dialog_reopens_for_the_same_account_after_a_validation_error(): void
    {
        $buyer = $this->user('buyer');

        $this->actingAs($this->admin)->from(route('admin.users'))->patch(route('admin.users.status', $buyer), [
            'user_id' => $buyer->id, 'status' => 'suspended', 'reason' => 'short',
        ])->assertRedirect(route('admin.users'));

        $this->actingAs($this->admin)->get(route('admin.users'))->assertOk()
            ->assertSee('id="user-status-suspended" aria-labelledby="user-status-suspended-title" data-open-on-load', false)
            ->assertSee('action="'.route('admin.users.status', $buyer).'"', false);
        $this->assertSame('approved', $buyer->fresh()->status);
    }

    public function test_preview_shows_summary_history_and_allowed_actions_only(): void
    {
        $buyer = $this->user('buyer', 'suspended');

        $this->actingAs($this->admin)->get(route('admin.users.preview', $buyer))->assertOk()
            ->assertSee('Status history')
            ->assertSee('Open full profile')
            ->assertSee('data-row-dialog="user-status-approved"', false)
            ->assertDontSee('data-row-dialog="user-status-suspended"', false)
            ->assertDontSee('<html', false);

        $this->actingAs($this->admin)->get(route('admin.users.preview', $this->admin))->assertNotFound();
    }

    public function test_bulk_status_applies_only_to_selected_accounts(): void
    {
        $picked = $this->user('buyer');
        $untouched = $this->user('buyer');

        $this->actingAs($this->admin)->post(route('admin.users.bulk-status'), [
            'user_ids' => [$picked->id], 'matching_count' => 1, 'to_status' => 'suspended',
            'reason' => 'Fraud review in progress.', 'confirm' => '1',
        ])->assertRedirect()->assertSessionHas('success', '1 account(s) updated.');

        $this->assertSame('suspended', $picked->fresh()->status);
        $this->assertSame('approved', $untouched->fresh()->status);
    }

    public function test_export_streams_selected_accounts_and_is_audited(): void
    {
        $a = $this->user('buyer', 'approved', ['first_name' => '=Formula']);
        $b = $this->user('buyer');

        $csv = $this->actingAs($this->admin)->get(route('admin.users.export', ['user_ids' => (string) $a->id]))->assertOk()->streamedContent();

        $this->assertStringContainsString("'=Formula", $csv);
        $this->assertStringNotContainsString($b->email, $csv);
        $this->assertSame([$a->id], AuditLog::where('action', 'users.exported')->sole()->metadata['user_ids']);

        config(['permissions.roles.admin' => [Permission::DASHBOARD_VIEW, Permission::USERS_VIEW]]);
        $this->actingAs($this->admin)->get(route('admin.users.export'))->assertForbidden();
    }

    private function user(string $role, string $status = 'approved', array $overrides = []): User
    {
        static $n = 0;
        $n++;

        return User::create(array_merge([
            'first_name' => ucfirst($role), 'last_name' => "Ulist{$n}", 'email' => "{$role}{$n}@ulist.test",
            'password' => bcrypt('password'), 'role' => $role, 'status' => $status, 'sex' => 'Male',
            'contact_no' => '09171234567', 'birthday' => '1990-01-01', 'age' => 35,
            'province' => 'Metro Manila', 'municipality' => 'Pasig', 'barangay' => 'San Miguel',
            'id_upload' => 'uploads/ids/id.jpg',
            'business_name' => $role === 'seller' ? 'Ulist Goods' : null,
        ], $overrides));
    }
}
