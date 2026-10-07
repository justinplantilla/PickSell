<?php

namespace Tests\Feature;

use App\Auth\Permission;
use App\Http\Middleware\AdminMiddleware;
use App\Models\AuditLog;
use App\Models\Complaint;
use App\Models\Product;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use LogicException;
use RuntimeException;
use Tests\TestCase;

/**
 * Request → Admin middleware → Gate/Policy (permission, resource, business rule)
 * → Controller → Service → DB transaction → Audit log.
 */
class AdminOperationAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->admin = $this->user('admin', 'approved');
    }

    private function user(string $role, string $status = 'approved'): User
    {
        static $n = 0;
        $n++;

        return User::create([
            'first_name' => ucfirst($role),
            'last_name' => "User{$n}",
            'email' => "{$role}{$n}@example.com",
            'password' => bcrypt('password'),
            'role' => $role,
            'status' => $status,
            'sex' => 'Male',
            'contact_no' => '09171234567',
            'birthday' => '1990-01-01',
            'age' => 35,
            'province' => 'Metro Manila',
            'municipality' => 'Quezon City',
            'barangay' => 'Bahay',
            'id_upload' => 'uploads/ids/test.jpg',
            'business_permit' => in_array($role, ['seller', 'logistics'], true) ? 'uploads/permits/test.pdf' : null,
        ]);
    }

    /** Every checklist item confirmed, as the review form would submit it. */
    private function approval(User $applicant): array
    {
        return ['checklist' => array_fill_keys(array_keys(\App\Support\RegistrationChecklist::for($applicant)), '1')];
    }

    private function product(User $seller, array $overrides = []): Product
    {
        return Product::create(array_merge([
            'seller_id' => $seller->id,
            'name' => 'Canvas Tote',
            'category' => 'Fashion',
            'price' => 450,
            'stock' => 10,
            'status' => 'active',
        ], $overrides));
    }

    // Deny by default ---------------------------------------------------------------------

    public function test_admin_route_without_a_permission_is_denied_at_runtime_and_audited(): void
    {
        Route::middleware(['web', 'auth', AdminMiddleware::class])
            ->get('/admin/forgotten-tool', fn () => 'should never render')
            ->name('admin.forgotten-tool');

        $this->actingAs($this->admin)->get('/admin/forgotten-tool')->assertForbidden();

        $log = AuditLog::where('action', 'authorization.denied')->sole();
        $this->assertSame($this->admin->id, $log->actor_id);
        $this->assertSame('admin.forgotten-tool', $log->metadata['route']);
        $this->assertSame('authorization', $log->module);
        $this->assertSame('denied', $log->result);
    }

    // Resource checks ---------------------------------------------------------------------

    public function test_admin_cannot_change_their_own_or_another_admins_status(): void
    {
        $otherAdmin = $this->user('admin');

        foreach ([$this->admin, $otherAdmin] as $target) {
            $this->actingAs($this->admin)->patch("/admin/users/{$target->id}/status", ['status' => 'suspended'])->assertForbidden();
            $this->assertSame('approved', $target->fresh()->status);
        }
        $this->actingAs($this->admin)->get("/admin/registrations/{$otherAdmin->id}")->assertForbidden();
    }

    public function test_warnings_only_go_to_approved_sellers(): void
    {
        $buyer = $this->user('buyer');
        $this->actingAs($this->admin)->patch("/admin/compliance/{$buyer->id}/warn", ['warning' => 'Spam'])->assertForbidden();
        Mail::assertNothingSent();

        $seller = $this->user('seller');
        $this->actingAs($this->admin)->patch("/admin/compliance/{$seller->id}/warn", ['warning' => 'Late shipments'])->assertRedirect();
        $this->assertSame('Late shipments', AuditLog::where('action', 'user.seller_warned')->sole()->metadata['warning']);
    }

    public function test_admin_cannot_decide_a_complaint_they_are_a_party_to(): void
    {
        $seller = $this->user('seller');
        $complaint = Complaint::create(['filed_by' => $this->admin->id, 'against_user_id' => $seller->id, 'subject' => 'x', 'details' => 'y', 'status' => 'open']);

        $this->actingAs($this->admin)->patch("/admin/complaints/{$complaint->id}", ['status' => 'resolved'])->assertForbidden();
        $this->assertSame('open', $complaint->fresh()->status);
    }

    // Business rules ----------------------------------------------------------------------

    public function test_registration_decisions_only_apply_to_pending_non_courier_applications(): void
    {
        $suspended = $this->user('seller', 'suspended');
        $this->actingAs($this->admin)->patch("/admin/registrations/{$suspended->id}/approve", $this->approval($suspended))->assertStatus(409);
        $this->assertSame('suspended', $suspended->fresh()->status);

        $courier = $this->user('courier', 'pending');
        $this->actingAs($this->admin)->patch("/admin/registrations/{$courier->id}/approve", $this->approval($courier))->assertForbidden();

        $pending = $this->user('seller', 'pending');
        $this->actingAs($this->admin)->patch("/admin/registrations/{$pending->id}/approve", $this->approval($pending))->assertRedirect();
        $this->assertSame('approved', $pending->fresh()->status);
        $this->actingAs($this->admin)->patch("/admin/registrations/{$pending->id}/disapprove", ['reason' => 'Applied too late for review'])->assertStatus(409);
    }

    public function test_pending_applications_cannot_be_activated_from_user_accounts(): void
    {
        $pending = $this->user('buyer', 'pending');

        $this->actingAs($this->admin)->patch("/admin/users/{$pending->id}/status", ['status' => 'approved'])->assertForbidden();
        $this->assertSame('pending', $pending->fresh()->status);

        $page = $this->actingAs($this->admin)->get('/admin/users?status=pending')->assertOk()->getContent();
        $this->assertStringNotContainsString(">Activate</button>", $page);
    }

    public function test_archived_products_cannot_be_featured_and_archiving_unfeatures(): void
    {
        $seller = $this->user('seller');
        $archived = $this->product($seller, ['status' => 'archived']);
        $this->actingAs($this->admin)->patch("/admin/products/{$archived->id}/featured")->assertForbidden();
        $this->assertFalse((bool) $archived->fresh()->is_featured);

        $featured = $this->product($seller, ['name' => 'Featured', 'is_featured' => true]);
        $this->actingAs($this->admin)->patch("/admin/products/{$featured->id}/status", ['status' => 'archived', 'reason' => 'Listing photos do not match the item.'])->assertRedirect();
        $featured->refresh();
        $this->assertSame('archived', $featured->status);
        $this->assertFalse((bool) $featured->is_featured);
        $this->assertSame(
            ['status' => ['from' => 'active', 'to' => 'archived'], 'is_featured' => ['from' => true, 'to' => false]],
            AuditLog::where('action', 'product.status_changed')->sole()->changes,
        );
    }

    // Transaction + audit -----------------------------------------------------------------

    public function test_successful_operation_is_audited_with_actor_permission_and_changes(): void
    {
        $pending = $this->user('seller', 'pending');
        $this->actingAs($this->admin)->patch("/admin/registrations/{$pending->id}/approve", $this->approval($pending))->assertRedirect();

        $log = AuditLog::where('action', 'user.registration_approved')->sole();
        $this->assertSame($this->admin->id, $log->actor_id);
        $this->assertSame('admin', $log->actor_role);
        $this->assertSame(Permission::REGISTRATIONS_MANAGE, $log->permission);
        $this->assertTrue($log->subject->is($pending));
        $this->assertSame(['status' => ['from' => 'pending', 'to' => 'approved']], $log->changes);
        $this->assertNotNull($log->ip_address);
        $this->assertSame('user', $log->module);
        $this->assertSame('success', $log->result);
    }

    public function test_sensitive_audit_values_are_redacted_without_losing_change_fields(): void
    {
        $log = app(AuditLogger::class)->record(
            'user.status_changed',
            $this->admin,
            [
                'email' => ['from' => 'old@example.com', 'to' => 'new@example.com'],
                'status' => ['from' => 'approved', 'to' => 'suspended'],
            ],
            ['api_token' => 'do-not-store', 'reason' => 'Repeated policy violations.'],
            Permission::USERS_MANAGE,
        );

        $this->assertSame([
            'email' => ['from' => '[redacted]', 'to' => '[redacted]'],
            'status' => ['from' => 'approved', 'to' => 'suspended'],
        ], $log->changes);
        $this->assertSame('[redacted]', $log->metadata['api_token']);
        $this->assertSame('Repeated policy violations.', $log->metadata['reason']);
    }

    public function test_account_deletion_is_audited_before_the_actor_is_removed(): void
    {
        $buyer = $this->user('buyer');

        $this->actingAs($buyer)->delete('/account', ['password' => 'password'])->assertRedirect('/');

        $log = AuditLog::where('action', 'user.deleted')->sole();
        $this->assertSame($buyer->id, $log->subject_id);
        $this->assertSame($buyer->id, $log->metadata['deleted_actor_id']);
        $this->assertSame('buyer', $log->actor_role);
        $this->assertSame('success', $log->result);
        $this->assertNull($log->actor_id);
        $this->assertDatabaseMissing('users', ['id' => $buyer->id]);
    }

    public function test_policy_denials_are_audited_with_their_reason(): void
    {
        $this->actingAs($this->admin)->patch("/admin/users/{$this->admin->id}/status", ['status' => 'suspended'])->assertForbidden();

        $log = AuditLog::where('action', 'authorization.denied')->sole();
        $this->assertSame('admin.users.status', $log->metadata['route']);
        $this->assertSame('You cannot perform this action on your own account.', $log->metadata['reason']);
    }

    public function test_change_rolls_back_when_its_audit_entry_cannot_be_written(): void
    {
        $this->app->instance(AuditLogger::class, new class extends AuditLogger
        {
            public function record(
                string $action,
                ?Model $subject = null,
                array $changes = [],
                array $metadata = [],
                ?string $permission = null,
                string $result = 'success',
            ): AuditLog {
                throw new RuntimeException('audit store unavailable');
            }
        });
        $this->withoutExceptionHandling();
        $pending = $this->user('seller', 'pending');

        try {
            $this->actingAs($this->admin)->patch("/admin/registrations/{$pending->id}/approve", $this->approval($pending));
            $this->fail('Expected the audit failure to abort the operation.');
        } catch (RuntimeException $e) {
            $this->assertSame('audit store unavailable', $e->getMessage());
        }

        $this->assertSame('pending', $pending->fresh()->status);
        Mail::assertNothingSent();
    }

    public function test_audit_entries_are_immutable(): void
    {
        $pending = $this->user('seller', 'pending');
        $this->actingAs($this->admin)->patch("/admin/registrations/{$pending->id}/approve", $this->approval($pending));
        $log = AuditLog::firstOrFail();

        $this->expectException(LogicException::class);
        $log->update(['action' => 'tampered']);
    }
}
