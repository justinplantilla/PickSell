<?php

namespace Tests\Feature;

use App\Auth\Permission;
use App\Models\AuditLog;
use App\Models\RegistrationReview;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/** Registration queue layout: status tabs, documents column, overdue and duplicate flags, preview, bulk disapprove, export. */
class AdminRegistrationQueueTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->admin = $this->user('admin', 'approved');
    }

    public function test_status_tabs_show_counts_and_the_decision_column_only_outside_pending(): void
    {
        $this->user('buyer');
        $approved = $this->user('seller', 'approved');
        RegistrationReview::create(['user_id' => $approved->id, 'reviewed_by' => $this->admin->id, 'decision' => 'approved', 'reviewed_at' => now()]);

        $this->actingAs($this->admin)->get(route('admin.registrations'))->assertOk()
            ->assertSeeInOrder(['Pending', '1', 'Approved', '1', 'Disapproved', '0', 'All', '2'])
            ->assertSeeInOrder(['Applicant', 'Role', 'Documents', 'Waiting'])
            ->assertDontSee('<th>Decision</th>', false);

        $this->actingAs($this->admin)->get(route('admin.registrations', ['status' => 'approved']))->assertOk()
            ->assertSeeInOrder(['Applicant', 'Role', 'Documents', 'Submitted', 'Decision'])
            ->assertSee('by '.$this->admin->full_name);
    }

    public function test_documents_column_counts_required_uploads_and_names_what_is_missing(): void
    {
        $this->user('seller', 'pending', ['seller_type' => 'corporation', 'sec_certificate' => 'docs/sec.pdf', 'bir_certificate' => null, 'tin' => '123456789012']);
        $this->user('buyer');

        $this->actingAs($this->admin)->get(route('admin.registrations'))->assertOk()
            ->assertSee('2 of 3 documents')
            ->assertSee('Missing: BIR')
            ->assertSee('1 of 1 document')
            ->assertSee('Corporation')
            ->assertSee('Personal account');
    }

    public function test_overdue_applications_are_flagged_and_counted(): void
    {
        $old = $this->user('buyer');
        User::whereKey($old->id)->update(['created_at' => now()->subDays(9)]);
        $this->user('buyer');

        $this->actingAs($this->admin)->get(route('admin.registrations'))->assertOk()
            ->assertSee('1 application has waited over 7 days.')
            ->assertSee('href="'.e(route('admin.registrations', ['date' => 'older_7d'])).'"', false)
            ->assertSee('list-row is-stale', false);
    }

    public function test_same_name_applicants_are_flagged_in_the_row_and_preview(): void
    {
        $first = $this->user('buyer', 'pending', ['first_name' => 'David', 'last_name' => 'Limjoco']);
        $this->user('buyer', 'pending', ['first_name' => ' david ', 'last_name' => 'LIMJOCO']);
        $this->user('buyer', 'pending', ['first_name' => 'Maria', 'last_name' => 'Cruz']);

        $this->actingAs($this->admin)->get(route('admin.registrations'))->assertOk()
            ->assertSee('Same name as 1 other account');

        $this->actingAs($this->admin)->get(route('admin.registrations.preview', $first))->assertOk()
            ->assertSee('Same name as 1 other account')
            ->assertSee('Review &amp; decide', false)
            ->assertSee('Disapprove without the full review')
            ->assertDontSee('<html', false);
    }

    public function test_preview_hides_quick_disapproval_without_manage_permission(): void
    {
        config(['permissions.roles.admin' => [Permission::DASHBOARD_VIEW, Permission::REGISTRATIONS_VIEW]]);
        $applicant = $this->user('buyer');

        $this->actingAs($this->admin)->get(route('admin.registrations.preview', $applicant))->assertOk()
            ->assertDontSee('Disapprove without the full review');
        $this->actingAs($this->admin)->get(route('admin.registrations'))->assertOk()->assertDontSee('data-select-row', false);
    }

    public function test_bulk_disapprove_only_touches_selected_pending_applications(): void
    {
        $spam = $this->user('buyer');
        $keep = $this->user('buyer');
        $decided = $this->user('seller', 'approved');

        $this->actingAs($this->admin)->post(route('admin.registrations.bulk-disapprove'), [
            'user_ids' => [$spam->id, $decided->id], 'matching_count' => 2, 'confirm' => '1',
            'reason' => 'This looks like a test registration.',
        ])->assertRedirect()->assertSessionHas('success', '1 application(s) disapproved.')->assertSessionHas('bulkSkippedCount', 1);

        $this->assertSame('disapproved', $spam->fresh()->status);
        $this->assertSame('pending', $keep->fresh()->status);
        $this->assertSame('approved', $decided->fresh()->status);
        $this->assertSame('This looks like a test registration.', $spam->latestRegistrationReview()->first()->reason);
    }

    public function test_bulk_disapprove_requires_reason_and_manage_permission(): void
    {
        $applicant = $this->user('buyer');

        $this->actingAs($this->admin)->post(route('admin.registrations.bulk-disapprove'), [
            'user_ids' => [$applicant->id], 'matching_count' => 1, 'confirm' => '1', 'reason' => 'short',
        ])->assertSessionHasErrors('reason');

        config(['permissions.roles.admin' => [Permission::DASHBOARD_VIEW, Permission::REGISTRATIONS_VIEW]]);
        $this->actingAs($this->admin)->post(route('admin.registrations.bulk-disapprove'), [
            'user_ids' => [$applicant->id], 'matching_count' => 1, 'confirm' => '1', 'reason' => 'This looks like a test registration.',
        ])->assertForbidden();

        $this->assertSame('pending', $applicant->fresh()->status);
    }

    public function test_export_streams_the_queue_without_documents_and_is_audited(): void
    {
        $this->user('seller', 'pending', ['business_name' => '=Formula Shop']);

        $csv = $this->actingAs($this->admin)->get(route('admin.registrations.export'))->assertOk()->streamedContent();

        $this->assertStringContainsString('documents_uploaded,documents_required', $csv);
        $this->assertStringContainsString("'=Formula Shop", $csv);
        $this->assertStringNotContainsString('uploads/ids', $csv);
        $this->assertSame(['status' => 'pending'], AuditLog::where('action', 'registrations.exported')->sole()->metadata['filters']);
    }

    private function user(string $role, string $status = 'pending', array $overrides = []): User
    {
        static $n = 0;
        $n++;

        return User::create(array_merge([
            'first_name' => ucfirst($role), 'last_name' => "Queue{$n}", 'email' => "{$role}{$n}@queue.test",
            'password' => bcrypt('password'), 'role' => $role, 'status' => $status, 'sex' => 'Female',
            'contact_no' => '09171234567', 'birthday' => '1992-04-01', 'age' => 34,
            'province' => 'Metro Manila', 'municipality' => 'Pasig', 'barangay' => 'San Miguel',
            'id_upload' => 'uploads/ids/id.jpg',
            'business_name' => $role === 'seller' ? 'Queue Goods' : null,
            'line_of_business' => $role === 'seller' ? 'Fashion' : null,
        ], $overrides));
    }
}
