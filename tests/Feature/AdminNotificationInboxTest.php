<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Admin\AdminNotificationInbox;
use App\Services\Admin\RegistrationReviewService;
use App\Services\AdminNotificationService;
use App\Support\RegistrationChecklist;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminNotificationInboxTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->admin = $this->user('admin', 'approved');
    }

    public function test_repeat_alerts_about_the_same_subject_refresh_one_unread_notification(): void
    {
        $service = app(AdminNotificationService::class);
        $service->notifyAdmins('registration.pending', 'Pending registration', 'First submit.', '/admin/registrations/5', subject: 'registration:5');
        $service->notifyAdmins('registration.pending', 'Resubmitted registration', 'Second submit.', '/admin/registrations/5', subject: 'registration:5');
        $service->notifyAdmins('registration.pending', 'Pending registration', 'Other applicant.', '/admin/registrations/50', subject: 'registration:50');

        $this->assertSame(2, $this->admin->notifications()->count());
        $refreshed = AdminNotificationService::whereData($this->admin->notifications()->getQuery(), 'subject', 'registration:5')->sole();
        $this->assertSame('Second submit.', $refreshed->data['message']);
        $this->assertSame('Resubmitted registration', $refreshed->data['title']);
    }

    public function test_deciding_a_registration_resolves_every_admins_alert_and_the_page_says_who_decided(): void
    {
        $otherAdmin = $this->user('admin', 'approved');
        $applicant = $this->user('buyer');
        app(AdminNotificationService::class)->notifyAdmins(
            'registration.pending', 'Pending registration', "{$applicant->full_name} submitted a buyer registration.",
            route('admin.registrations.show', $applicant, false), subject: "registration:{$applicant->id}",
        );

        app(RegistrationReviewService::class)->approve(
            $applicant, $otherAdmin, array_fill_keys(array_keys(RegistrationChecklist::for($applicant)), true),
        );

        $this->assertSame(0, $this->admin->unreadNotifications()->count());
        $this->actingAs($this->admin)->get('/admin/notifications')
            ->assertOk()
            ->assertSee('Approved by '.$otherAdmin->full_name)
            ->assertSee('<strong>'.e($applicant->full_name).'</strong>', false);
    }

    public function test_legacy_registration_alert_for_an_already_decided_applicant_is_marked_read_when_viewed(): void
    {
        $applicant = $this->user('seller', 'disapproved');
        $notification = $this->notify(['event' => 'registration.pending', 'title' => 'Pending registration', 'message' => 'Old alert.', 'url' => "/admin/registrations/{$applicant->id}"]);

        $this->actingAs($this->admin)->get('/admin/notifications')->assertOk()->assertSee('Disapproved');

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_pending_registration_shows_a_review_action(): void
    {
        $applicant = $this->user('seller');
        $this->notify(['event' => 'registration.pending', 'title' => 'Pending registration', 'message' => 'New.', 'url' => "/admin/registrations/{$applicant->id}"]);

        $this->actingAs($this->admin)->get('/admin/notifications')
            ->assertOk()
            ->assertSee('href="/admin/registrations/'.$applicant->id.'"', false)
            ->assertSee('>Review<', false);
    }

    public function test_filters_show_only_their_category_with_unread_counts(): void
    {
        $this->notify(['event' => 'registration.pending', 'title' => 'Pending registration', 'message' => 'Reg alert.']);
        $this->notify(['event' => 'delivery.failed', 'title' => 'Delivery failed', 'message' => 'Failed ORD-ABC123.', 'priority' => 'critical']);
        $this->notify(['event' => 'refund.requested', 'title' => 'Refund requested', 'message' => 'Refund alert.']);
        $this->notify(['type' => 'order', 'title' => 'Parcel handed over', 'message' => 'Legacy handover.'], 'App\\Notifications\\OrderWorkflowUpdate');

        $this->actingAs($this->admin)->get('/admin/notifications?filter=registrations')
            ->assertOk()->assertSee('Reg alert.')->assertDontSee('Refund alert.')->assertDontSee('Legacy handover.');
        $this->actingAs($this->admin)->get('/admin/notifications?filter=logistics')
            ->assertOk()->assertSee('Legacy handover.')->assertSee('<strong>ORD-ABC123</strong>', false)->assertDontSee('Reg alert.');
        $this->actingAs($this->admin)->get('/admin/notifications?filter=critical')
            ->assertOk()->assertSee('Failed')->assertDontSee('Refund alert.');
        $this->actingAs($this->admin)->get('/admin/notifications?filter=bogus')
            ->assertOk()->assertSee('Reg alert.')->assertSee('Refund alert.');

        $counts = app(AdminNotificationInbox::class)->unreadCounts($this->admin);
        $this->assertSame(['all' => 4, 'unread' => 4, 'registrations' => 1, 'orders' => 1, 'logistics' => 2, 'critical' => 1], $counts);
    }

    public function test_mark_read_can_be_limited_to_the_current_filter(): void
    {
        $registration = $this->notify(['event' => 'registration.pending', 'title' => 'Pending registration', 'message' => 'Reg.']);
        $refund = $this->notify(['event' => 'refund.requested', 'title' => 'Refund requested', 'message' => 'Refund.']);

        $this->actingAs($this->admin)->postJson('/admin/notifications/read', ['filter' => 'orders'])
            ->assertOk()->assertJson(['success' => true, 'unread' => 1]);

        $this->assertNull($registration->fresh()->read_at);
        $this->assertNotNull($refund->fresh()->read_at);
    }

    public function test_identical_repeats_merge_and_same_title_alerts_collapse_into_a_group(): void
    {
        $first = $this->notify(['event' => 'complaint.opened', 'title' => 'Complaint opened', 'message' => 'Same complaint.']);
        $second = $this->notify(['event' => 'complaint.opened', 'title' => 'Complaint opened', 'message' => 'Same complaint.']);
        foreach (['A', 'B', 'C'] as $name) {
            $this->notify(['event' => 'refund.requested', 'title' => 'Refund requested', 'message' => "Refund {$name}."]);
        }

        $this->actingAs($this->admin)->get('/admin/notifications')
            ->assertOk()
            ->assertSee('×2')
            ->assertSee('data-notification-ids="'.$first->id.','.$second->id.'"', false)
            ->assertSee('3 × Refund requested')
            ->assertSee('Today');

        $this->actingAs($this->admin)->postJson("/admin/notifications/{$first->id}/read", ['ids' => [$first->id, $second->id]])->assertOk();
        $this->assertNotNull($second->fresh()->read_at);
    }

    public function test_marking_read_ignores_ids_belonging_to_other_users(): void
    {
        $other = $this->user('admin', 'approved');
        $mine = $this->notify(['title' => 'Mine', 'message' => 'Mine.']);
        $theirs = $other->notifications()->create(['id' => (string) Str::uuid(), 'type' => AdminNotificationService::TYPE, 'data' => json_encode(['message' => 'Theirs'])]);

        $this->actingAs($this->admin)->postJson("/admin/notifications/{$mine->id}/read", ['ids' => [$theirs->id]])->assertOk();

        $this->assertNotNull($mine->fresh()->read_at);
        $this->assertNull($theirs->fresh()->read_at);
    }

    public function test_prune_deletes_only_old_read_admin_notifications(): void
    {
        $buyer = $this->user('buyer', 'approved');
        $oldRead = $this->notify(['message' => 'Old read.']);
        $oldUnread = $this->notify(['message' => 'Old unread.']);
        $recentRead = $this->notify(['message' => 'Recent read.']);
        $buyerOld = $buyer->notifications()->create(['id' => (string) Str::uuid(), 'type' => 'X', 'data' => '{}']);
        DatabaseNotification::whereKey([$oldRead->id, $oldUnread->id, $buyerOld->id])->update(['created_at' => now()->subDays(120)]);
        DatabaseNotification::whereKey([$oldRead->id, $recentRead->id, $buyerOld->id])->update(['read_at' => now()]);

        $this->artisan('notifications:prune-admin')->assertSuccessful();

        $this->assertNull($oldRead->fresh());
        $this->assertNotNull($oldUnread->fresh());
        $this->assertNotNull($recentRead->fresh());
        $this->assertNotNull($buyerOld->fresh());
    }

    private function notify(array $data, string $type = AdminNotificationService::TYPE): DatabaseNotification
    {
        return $this->admin->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => $type,
            'data' => json_encode($data, JSON_THROW_ON_ERROR),
        ]);
    }

    private function user(string $role, string $status = 'pending'): User
    {
        static $n = 0;
        $n++;

        return User::create([
            'first_name' => ucfirst($role), 'last_name' => "Inbox{$n}", 'email' => "{$role}{$n}@inbox.test",
            'password' => bcrypt('password'), 'role' => $role, 'status' => $status, 'sex' => 'Female',
            'contact_no' => '09171234567', 'birthday' => '1992-04-01', 'age' => 34,
            'province' => 'Metro Manila', 'municipality' => 'Pasig', 'barangay' => 'San Miguel',
            'id_upload' => 'uploads/ids/id.jpg',
            'business_name' => $role === 'seller' ? 'Inbox Goods' : null,
            'line_of_business' => $role === 'seller' ? 'Fashion' : null,
            'business_permit' => $role === 'seller' ? 'uploads/permits/permit.pdf' : null,
        ]);
    }
}
