<?php

namespace Tests\Feature;

use App\Auth\Permission;
use App\Models\AuditLog;
use App\Models\RegistrationReview;
use App\Models\User;
use App\Notifications\RegistrationApprovedNotification;
use App\Notifications\RegistrationDisapprovedNotification;
use App\Support\RegistrationChecklist;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use RuntimeException;
use Tests\TestCase;

/** Module 2 — Registrations: formal review with checklist, history, notifications and audit. */
class RegistrationReviewTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->admin = $this->user('admin', 'approved');
    }

    private function user(string $role, string $status = 'pending', array $overrides = []): User
    {
        static $n = 0;
        $n++;

        return User::create(array_merge([
            'first_name' => ucfirst($role), 'last_name' => "Reg{$n}", 'email' => "{$role}{$n}@reg.test",
            'password' => bcrypt('password'), 'role' => $role, 'status' => $status, 'sex' => 'Female',
            'contact_no' => '09171234567', 'birthday' => '1992-04-01', 'age' => 34,
            'province' => 'Metro Manila', 'municipality' => 'Pasig', 'barangay' => 'San Miguel',
            'id_upload' => 'uploads/ids/id.jpg',
            'business_name' => in_array($role, ['seller', 'logistics'], true) ? 'Reg Goods' : null,
            'line_of_business' => in_array($role, ['seller', 'logistics'], true) ? 'Fashion' : null,
            'business_permit' => in_array($role, ['seller', 'logistics'], true) ? 'uploads/permits/permit.pdf' : null,
        ], $overrides));
    }

    private function allChecks(User $applicant): array
    {
        return array_fill_keys(array_keys(RegistrationChecklist::for($applicant)), '1');
    }

    // Approval requires permission ---------------------------------------------------------

    public function test_approval_requires_the_manage_permission(): void
    {
        $seller = $this->user('seller');
        config(['permissions.roles.admin' => [Permission::DASHBOARD_VIEW, Permission::REGISTRATIONS_VIEW]]);

        $this->actingAs($this->admin)->get(route('admin.registrations.show', $seller))->assertOk()
            ->assertSee('Deciding it requires the registrations management permission')->assertDontSee('>Approve</button>', false);
        $this->actingAs($this->admin)->patch(route('admin.registrations.approve', $seller), ['checklist' => $this->allChecks($seller)])
            ->assertForbidden();

        $this->assertSame('pending', $seller->fresh()->status);
        $this->assertSame(0, RegistrationReview::count());
        Notification::assertNothingSent();
    }

    public function test_approval_requires_every_checklist_item(): void
    {
        $seller = $this->user('seller');
        $partial = $this->allChecks($seller);
        unset($partial['business_permit']);

        $this->actingAs($this->admin)->from(route('admin.registrations.show', $seller))
            ->patch(route('admin.registrations.approve', $seller), ['checklist' => $partial])
            ->assertRedirect(route('admin.registrations.show', $seller))
            ->assertSessionHasErrors(['checklist.business_permit']);
        $this->assertSame('pending', $seller->fresh()->status);

        $this->actingAs($this->admin)->patch(route('admin.registrations.approve', $seller), ['checklist' => $this->allChecks($seller)])
            ->assertSessionHasNoErrors();
        $this->assertSame('approved', $seller->fresh()->status);
    }

    public function test_application_missing_a_required_document_cannot_be_approved(): void
    {
        $seller = $this->user('seller', 'pending', ['business_permit' => null]);

        $this->actingAs($this->admin)->get(route('admin.registrations.show', $seller))->assertOk()
            ->assertSee('Document not uploaded — cannot be confirmed');
        $this->actingAs($this->admin)->patch(route('admin.registrations.approve', $seller), ['checklist' => $this->allChecks($seller)])
            ->assertSessionHasErrors(['checklist.business_permit']);
        $this->assertSame('pending', $seller->fresh()->status);
    }

    // Disapproval requires reason ----------------------------------------------------------

    public function test_disapproval_requires_a_meaningful_reason(): void
    {
        $buyer = $this->user('buyer');

        foreach (['', '   ', 'Bad ID'] as $reason) {
            $this->actingAs($this->admin)->patch(route('admin.registrations.disapprove', $buyer), ['reason' => $reason])
                ->assertSessionHasErrors('reason');
        }
        $this->assertSame('pending', $buyer->fresh()->status);

        $this->actingAs($this->admin)->patch(route('admin.registrations.disapprove', $buyer), [
            'reason' => 'The ID photo is blurred; please register again with a clear photo.',
            'checklist' => ['contact' => '1', 'address' => '1'],
        ])->assertSessionHasNoErrors();

        $review = RegistrationReview::sole();
        $this->assertSame('disapproved', $buyer->fresh()->status);
        $this->assertSame('The ID photo is blurred; please register again with a clear photo.', $review->reason);
        $this->assertFalse($review->verification_snapshot['checklist']['identity']['confirmed']);
        $this->assertTrue($review->verification_snapshot['checklist']['contact']['confirmed']);
    }

    // Review history is retained -----------------------------------------------------------

    public function test_review_history_is_retained_with_snapshot_and_shown(): void
    {
        $logistics = $this->user('logistics', 'pending', ['provider_type' => 'company']);

        $this->actingAs($this->admin)->patch(route('admin.registrations.approve', $logistics), [
            'checklist' => $this->allChecks($logistics),
            'reason' => 'Permit verified with the issuing LGU.',
        ])->assertSessionHasNoErrors();

        $review = RegistrationReview::sole();
        $this->assertSame($logistics->id, $review->user_id);
        $this->assertSame($this->admin->id, $review->reviewed_by);
        $this->assertSame(RegistrationReview::APPROVED, $review->decision);
        $this->assertNotNull($review->reviewed_at);
        $this->assertSame(['identity', 'contact', 'address', 'business_permit', 'business_details', 'provider_type'], array_keys($review->verification_snapshot['checklist']));
        $this->assertSame('uploads/permits/permit.pdf', $review->verification_snapshot['documents']['business_permit']);
        $this->assertSame('Reg Goods', $review->verification_snapshot['applicant']['business_name']);

        // A later re-application keeps the earlier decision in the history.
        $logistics->refresh()->update(['status' => 'pending']);
        $this->actingAs($this->admin)->patch(route('admin.registrations.disapprove', $logistics), ['reason' => 'Permit expired since the first review.']);
        $this->assertSame(2, $logistics->registrationReviews()->count());

        $this->actingAs($this->admin)->get(route('admin.registrations.show', $logistics))->assertOk()
            ->assertSeeInOrder(['Review history', 'Disapproved', 'Permit expired since the first review.', 'Approved', 'Permit verified with the issuing LGU.'])
            ->assertSee('Audit history')->assertSee('user.registration_disapproved');
    }

    // Applicant is notified ------------------------------------------------------------------

    public function test_applicant_receives_notification_and_email(): void
    {
        $seller = $this->user('seller');
        $buyer = $this->user('buyer');

        $this->actingAs($this->admin)->patch(route('admin.registrations.approve', $seller), ['checklist' => $this->allChecks($seller)]);
        $this->actingAs($this->admin)->patch(route('admin.registrations.disapprove', $buyer), ['reason' => 'Name on the ID does not match the form.']);

        Notification::assertSentTo($seller, RegistrationApprovedNotification::class, fn ($n, $channels) => $channels === ['mail', 'database']);
        Notification::assertSentTo($buyer, RegistrationDisapprovedNotification::class,
            fn ($n, $channels) => $n->reason === 'Name on the ID does not match the form.' && in_array('mail', $channels, true));
    }

    public function test_email_uses_the_existing_templates(): void
    {
        $buyer = $this->user('buyer');
        $mail = (new RegistrationDisapprovedNotification('Name on the ID does not match.'))->toMail($buyer);

        $this->assertTrue($mail->hasTo($buyer->email));
        $mail->assertSeeInHtml('Name on the ID does not match.');
    }

    public function test_mail_failure_keeps_the_decision_and_warns(): void
    {
        Notification::swap(new class extends \Illuminate\Support\Testing\Fakes\NotificationFake {
            public function send($notifiables, $notification): void { throw new RuntimeException('SMTP down'); }
            public function sendNow($notifiables, $notification, ?array $channels = null): void { throw new RuntimeException('SMTP down'); }
        });
        $seller = $this->user('seller');

        $this->actingAs($this->admin)->patch(route('admin.registrations.approve', $seller), ['checklist' => $this->allChecks($seller)])
            ->assertSessionHas('warning', 'The decision was saved, but the notification email could not be sent. Check the mail settings.');
        $this->assertSame('approved', $seller->fresh()->status);
        $this->assertSame(1, RegistrationReview::count());
    }

    // Decision is audited ------------------------------------------------------------------

    public function test_decision_is_audited_and_linked_to_the_review(): void
    {
        $buyer = $this->user('buyer');
        $this->actingAs($this->admin)->patch(route('admin.registrations.disapprove', $buyer), ['reason' => 'Duplicate account for the same person.']);

        $log = AuditLog::where('action', 'user.registration_disapproved')->sole();
        $this->assertSame($this->admin->id, $log->actor_id);
        $this->assertTrue($log->subject->is($buyer));
        $this->assertSame(Permission::REGISTRATIONS_MANAGE, $log->permission);
        $this->assertSame(['status' => ['from' => 'pending', 'to' => 'disapproved']], $log->changes);
        $this->assertSame(RegistrationReview::sole()->id, $log->metadata['review_id']);
        $this->assertSame('Duplicate account for the same person.', $log->metadata['reason']);
    }

    // Invalid repeated decisions are blocked ----------------------------------------------

    public function test_repeated_or_conflicting_decisions_are_blocked(): void
    {
        $seller = $this->user('seller');
        $approve = fn () => $this->actingAs($this->admin)->patch(route('admin.registrations.approve', $seller), ['checklist' => $this->allChecks($seller)]);

        $approve()->assertRedirect();
        $approve()->assertStatus(409);
        $this->actingAs($this->admin)->patch(route('admin.registrations.disapprove', $seller), ['reason' => 'Changed my mind about this one.'])
            ->assertStatus(409);

        $this->assertSame('approved', $seller->fresh()->status);
        $this->assertSame(1, RegistrationReview::count());
        $this->assertSame(1, AuditLog::where('action', 'user.registration_approved')->count());
        Notification::assertSentToTimes($seller, RegistrationApprovedNotification::class, 1);
    }

    public function test_service_lock_blocks_a_decision_that_raced_past_authorization(): void
    {
        $seller = $this->user('seller');
        $stale = User::find($seller->id);       // reviewer B loaded the page while it was pending
        $seller->update(['status' => 'approved']); // reviewer A decided first

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(\App\Services\Admin\RegistrationReviewService::class)->disapprove($stale, $this->admin, 'Too late to decide this.');
    }

    // Queue ----------------------------------------------------------------------------------

    public function test_queue_filters_by_search_role_status_and_date_oldest_first(): void
    {
        $old = $this->user('seller', 'pending', ['business_name' => 'Old Shop']);
        User::whereKey($old->id)->update(['created_at' => now()->subDays(10)]);
        $new = $this->user('buyer');
        $approved = $this->user('seller', 'approved', ['business_name' => 'Done Shop']);
        $courier = $this->user('courier');

        $this->actingAs($this->admin)->get(route('admin.registrations'))->assertOk()
            ->assertSeeInOrder([$old->email, $new->email])
            ->assertSee('Review application')
            ->assertSee('View Logistics review')
            ->assertDontSee($approved->email);
        $this->actingAs($this->admin)->get(route('admin.registrations.show', $old))->assertOk()
            ->assertSee('>Approve</button>', false)
            ->assertSee('>Disapprove</button>', false);
        $this->actingAs($this->admin)->get(route('admin.registrations.show', $courier))->assertOk()
            ->assertSee('Courier applications are reviewed in the Logistics portal.')
            ->assertDontSee('>Approve</button>', false)
            ->assertDontSee('>Disapprove</button>', false);
        $this->actingAs($this->admin)->get(route('admin.registrations', ['search' => 'Old Shop']))
            ->assertSee($old->email)->assertDontSee($new->email);
        $this->actingAs($this->admin)->get(route('admin.registrations', ['role' => 'buyer']))
            ->assertSee($new->email)->assertDontSee($old->email);
        $this->actingAs($this->admin)->get(route('admin.registrations', ['date' => 'older_7d']))
            ->assertSee($old->email)->assertDontSee($new->email);
        $this->actingAs($this->admin)->get(route('admin.registrations', ['status' => 'approved']))
            ->assertSee($approved->email)->assertDontSee($old->email);
    }
}
