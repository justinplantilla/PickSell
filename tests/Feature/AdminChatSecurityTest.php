<?php

namespace Tests\Feature;

use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminChatSecurityTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role, string $email, string $status = 'approved'): User
    {
        return User::create([
            'first_name' => ucfirst($role),
            'last_name' => 'Chat',
            'email' => $email,
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
        ]);
    }

    public function test_admin_chat_lists_only_approved_supported_contacts(): void
    {
        $admin = $this->user('admin', 'admin@example.com');
        $buyer = $this->user('buyer', 'buyer@example.com');
        $this->user('buyer', 'pending@example.com', 'pending');
        $this->user('admin', 'second-admin@example.com');

        $response = $this->actingAs($admin)
            ->get('/admin/chat')
            ->assertOk();

        $response->assertViewHas('users', fn ($users): bool => $users->modelKeys() === [$buyer->id]);
    }

    public function test_admin_cannot_view_unsupported_or_unapproved_conversations(): void
    {
        $admin = $this->user('admin', 'admin@example.com');
        $unsupported = $this->user('admin', 'other-admin@example.com');
        $pending = $this->user('buyer', 'pending@example.com', 'pending');
        $history = Message::create([
            'sender_id' => $unsupported->id,
            'receiver_id' => $admin->id,
            'body' => 'Private history',
            'read' => false,
        ]);

        $this->actingAs($admin)->get('/admin/chat?user='.$unsupported->id)->assertForbidden();
        $this->actingAs($admin)->get('/admin/chat?user='.$pending->id)->assertForbidden();
        $this->actingAs($admin)->get('/admin/chat?user[]='.$unsupported->id)->assertNotFound();

        $this->assertDatabaseHas('messages', ['id' => $history->id, 'read' => false]);
    }

    public function test_admin_can_send_valid_messages_and_existing_history_is_preserved(): void
    {
        $admin = $this->user('admin', 'admin@example.com');
        $buyer = $this->user('buyer', 'buyer@example.com');
        $oldMessage = Message::create([
            'sender_id' => $buyer->id,
            'receiver_id' => $admin->id,
            'body' => 'Earlier message',
            'read' => false,
        ]);

        $this->actingAs($admin)
            ->post('/admin/chat/send', [
                'receiver_id' => $buyer->id,
                'body' => 'A valid reply',
            ])
            ->assertRedirect(route('admin.chat', ['user' => $buyer->id]));

        $this->assertDatabaseHas('messages', ['id' => $oldMessage->id, 'body' => 'Earlier message']);
        $this->assertDatabaseHas('messages', [
            'sender_id' => $admin->id,
            'receiver_id' => $buyer->id,
            'body' => 'A valid reply',
            'read' => false,
        ]);
    }

    public function test_admin_cannot_send_to_self_or_unapproved_or_unsupported_users(): void
    {
        $admin = $this->user('admin', 'admin@example.com');
        $otherAdmin = $this->user('admin', 'other-admin@example.com');
        $pending = $this->user('buyer', 'pending@example.com', 'pending');

        foreach ([$admin, $otherAdmin, $pending] as $recipient) {
            $this->actingAs($admin)
                ->post('/admin/chat/send', [
                    'receiver_id' => $recipient->id,
                    'body' => 'This must not be sent.',
                ])
                ->assertForbidden();
        }

        $this->assertDatabaseCount('messages', 0);
    }

    public function test_admin_message_body_is_validated_server_side(): void
    {
        $admin = $this->user('admin', 'admin@example.com');
        $buyer = $this->user('buyer', 'buyer@example.com');

        $this->actingAs($admin)
            ->post('/admin/chat/send', ['receiver_id' => $buyer->id, 'body' => ''])
            ->assertSessionHasErrors('body');

        $this->actingAs($admin)
            ->post('/admin/chat/send', ['receiver_id' => $buyer->id, 'body' => str_repeat('x', 2001)])
            ->assertSessionHasErrors('body');

        $this->assertDatabaseCount('messages', 0);
    }
}
