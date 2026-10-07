<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminAccountAndNotificationsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = $this->createAdmin('admin1@account.test');
    }

    public function test_admin_profile_and_password_changes_require_reauthentication_and_are_audited(): void
    {
        $this->actingAs($this->admin)
            ->patch('/admin/account', [
                'first_name' => 'Updated',
                'last_name' => $this->admin->last_name,
                'email' => $this->admin->email,
                'contact_no' => $this->admin->contact_no,
                'current_password' => 'old-password',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('users', ['id' => $this->admin->id, 'first_name' => 'Updated']);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'admin_account.profile_updated',
            'subject_id' => $this->admin->id,
        ]);

        $this->actingAs($this->admin)
            ->patch('/admin/account/password', [
                'current_password' => 'old-password',
                'password' => 'a-new-secure-password',
                'password_confirmation' => 'a-new-secure-password',
            ])
            ->assertRedirect();

        $this->assertTrue(password_verify('a-new-secure-password', $this->admin->fresh()->password));
        $audit = AuditLog::query()->where('action', 'admin_account.password_changed')->firstOrFail();
        $this->assertSame('[redacted]', $audit->changes['password_changed']['to']);
    }

    public function test_account_page_uses_typed_confirmation_without_a_redundant_confirm_dialog(): void
    {
        $this->actingAs($this->admin)
            ->get('/admin/account')
            ->assertOk()
            ->assertSee('name="confirmation"', false)
            ->assertDontSee('data-confirm="Delete your admin account permanently?', false);
    }

    public function test_last_admin_cannot_delete_their_account_and_deletion_needs_explicit_confirmation(): void
    {
        $this->actingAs($this->admin)
            ->delete('/admin/account', [
                'current_password' => 'old-password',
                'confirmation' => 'DELETE',
            ])
            ->assertForbidden();

        $secondAdmin = $this->createAdmin('admin2@account.test');
        $this->actingAs($this->admin)
            ->delete('/admin/account', [
                'current_password' => 'old-password',
                'confirmation' => 'delete',
            ])
            ->assertSessionHasErrors('confirmation');

        $this->assertDatabaseHas('users', ['id' => $this->admin->id]);
        $this->assertDatabaseHas('users', ['id' => $secondAdmin->id]);
    }

    public function test_deletion_is_blocked_when_it_would_remove_chat_history(): void
    {
        $this->createAdmin('admin4@account.test');
        $participant = User::create([
            'first_name' => 'Buyer',
            'last_name' => 'Participant',
            'email' => 'buyer@account.test',
            'password' => bcrypt('old-password'),
            'role' => 'buyer',
            'status' => 'approved',
            'sex' => 'Male',
            'contact_no' => '09171234568',
            'birthday' => '1990-01-01',
            'age' => 36,
            'province' => 'Metro Manila',
            'municipality' => 'Quezon City',
            'barangay' => 'Bahay',
        ]);
        $this->admin->sentMessages()->create([
            'receiver_id' => $participant->id,
            'body' => 'Retain this chat history',
        ]);

        $this->actingAs($this->admin)
            ->delete('/admin/account', [
                'current_password' => 'old-password',
                'confirmation' => 'DELETE',
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('users', ['id' => $this->admin->id]);
        $this->assertDatabaseHas('messages', ['body' => 'Retain this chat history']);
    }

    public function test_confirmed_deletion_records_audit_event_and_preserves_it_after_account_is_removed(): void
    {
        $this->createAdmin('admin5@account.test');

        $this->actingAs($this->admin)
            ->delete('/admin/account', [
                'current_password' => 'old-password',
                'confirmation' => 'DELETE',
            ])
            ->assertRedirect('/');

        $this->assertDatabaseMissing('users', ['id' => $this->admin->id]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'admin_account.deleted',
            'actor_id' => null,
            'subject_id' => $this->admin->id,
        ]);
    }

    public function test_admin_can_mark_only_their_own_notification_read_and_notification_url_is_rendered(): void
    {
        $notification = $this->admin->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => 'App\\Notifications\\AdminActivity',
            'data' => json_encode([
                'title' => 'Pending registration',
                'message' => '<script>alert(1)</script> New account',
                'url' => '/admin/registrations/12',
            ], JSON_THROW_ON_ERROR),
        ]);
        $this->admin->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => 'App\\Notifications\\AdminActivity',
            'data' => json_encode(['message' => 'Unsafe link', 'url' => '//outside.example'], JSON_THROW_ON_ERROR),
        ]);

        $this->actingAs($this->admin)
            ->get('/admin/notifications')
            ->assertOk()
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertSee('/admin/registrations/12', false)
            ->assertSee('notification-detail-trigger', false);

        $this->actingAs($this->admin)
            ->get('/admin/notifications/feed')
            ->assertOk()
            ->assertJsonFragment(['url' => null]);

        $this->actingAs($this->admin)
            ->post("/admin/notifications/{$notification->id}/read")
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_admin_cannot_mark_another_users_notification_read(): void
    {
        $other = $this->createAdmin('admin3@account.test');
        $notification = $other->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => 'App\\Notifications\\AdminActivity',
            'data' => json_encode(['message' => 'Private'], JSON_THROW_ON_ERROR),
        ]);

        $this->actingAs($this->admin)
            ->post("/admin/notifications/{$notification->id}/read")
            ->assertNotFound();

        $this->assertNull($notification->fresh()->read_at);
    }

    private function createAdmin(string $email): User
    {
        return User::create([
            'first_name' => 'Admin',
            'last_name' => 'Account',
            'email' => $email,
            'password' => bcrypt('old-password'),
            'role' => 'admin',
            'status' => 'approved',
            'sex' => 'Male',
            'contact_no' => '09171234567',
            'birthday' => '1990-01-01',
            'age' => 36,
            'province' => 'Metro Manila',
            'municipality' => 'Quezon City',
            'barangay' => 'Bahay',
        ]);
    }
}
