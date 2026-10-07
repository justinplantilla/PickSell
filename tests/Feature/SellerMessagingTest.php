<?php

namespace Tests\Feature;

use App\Mail\NewMessageMail;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Tests\TestCase;

class SellerMessagingTest extends TestCase
{
    use RefreshDatabase;

    private User $seller;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->seller = $this->user('seller', 'seller@example.test');
        $this->admin = $this->user('admin', 'admin@example.test');
    }

    public function test_seller_can_start_a_support_conversation_and_admin_can_read_it(): void
    {
        $this->actingAs($this->seller)
            ->get(route('seller.chat'))
            ->assertOk()
            ->assertSee('PickSell Support')
            ->assertSee('seller-chat-empty-icon', false);

        $this->actingAs($this->seller)
            ->get(route('seller.chat', ['user' => $this->admin->id]))
            ->assertOk()
            ->assertSee('name="body"', false)
            ->assertSee('name="receiver_id" value="'.$this->admin->id.'"', false)
            ->assertSee('>Send</button>', false);

        $this->actingAs($this->seller)
            ->post(route('seller.chat.send'), [
                'receiver_id' => $this->admin->id,
                'body' => 'I need help with my seller account.',
            ])
            ->assertRedirect(route('seller.chat', ['user' => $this->admin->id]))
            ->assertSessionHas('success', 'Message sent.');

        $this->assertDatabaseHas('messages', [
            'sender_id' => $this->seller->id,
            'receiver_id' => $this->admin->id,
            'body' => 'I need help with my seller account.',
            'read' => false,
        ]);
        Mail::assertSent(NewMessageMail::class, fn (NewMessageMail $mail): bool => $mail->message->receiver_id === $this->admin->id);

        $this->actingAs($this->admin)
            ->get(route('admin.chat', ['user' => $this->seller->id]))
            ->assertOk()
            ->assertSee('I need help with my seller account.');
    }

    public function test_seller_chat_only_opens_and_sends_to_approved_contacts(): void
    {
        $unrelatedBuyer = $this->user('buyer', 'buyer@example.test');
        $pendingSupport = $this->user('admin', 'pending-admin@example.test', 'pending');

        $this->actingAs($this->seller)
            ->get(route('seller.chat', ['user' => $unrelatedBuyer->id]))
            ->assertOk()
            ->assertDontSee('name="body"', false);

        foreach ([$unrelatedBuyer, $pendingSupport, $this->seller] as $recipient) {
            $this->actingAs($this->seller)
                ->post(route('seller.chat.send'), [
                    'receiver_id' => $recipient->id,
                    'body' => 'This message must not be sent.',
                ])
                ->assertNotFound();
        }

        $this->assertSame(0, Message::count());
    }

    public function test_seller_can_reply_to_a_buyer_who_started_the_conversation(): void
    {
        $buyer = $this->user('buyer', 'conversation-buyer@example.test');
        Message::create([
            'sender_id' => $buyer->id,
            'receiver_id' => $this->seller->id,
            'body' => 'Is this item still available?',
            'read' => false,
        ]);

        $this->actingAs($this->seller)
            ->get(route('seller.chat', ['user' => $buyer->id]))
            ->assertOk()
            ->assertSee('Is this item still available?')
            ->assertSee('name="body"', false)
            ->assertSee('>Send</button>', false);

        $this->actingAs($this->seller)
            ->post(route('seller.chat.send'), [
                'receiver_id' => $buyer->id,
                'body' => 'Yes, it is available.',
            ])
            ->assertRedirect(route('seller.chat', ['user' => $buyer->id]));

        $this->assertDatabaseHas('messages', [
            'sender_id' => $this->seller->id,
            'receiver_id' => $buyer->id,
            'body' => 'Yes, it is available.',
        ]);
    }

    public function test_email_delivery_failure_does_not_turn_a_saved_chat_message_into_a_server_error(): void
    {
        Mail::shouldReceive('to')
            ->once()
            ->with($this->admin->email)
            ->andThrow(new RuntimeException('SMTP unavailable'));
        Log::shouldReceive('warning')
            ->once()
            ->with('Chat message email notification failed.', \Mockery::on(
                fn (array $context): bool => isset($context['message_id'])
                    && $context['error'] === 'SMTP unavailable',
            ));

        $this->actingAs($this->seller)
            ->post(route('seller.chat.send'), [
                'receiver_id' => $this->admin->id,
                'body' => 'Please help with my store.',
            ])
            ->assertRedirect(route('seller.chat', ['user' => $this->admin->id]))
            ->assertSessionHas('success', 'Message sent.');

        $this->assertDatabaseHas('messages', [
            'sender_id' => $this->seller->id,
            'receiver_id' => $this->admin->id,
            'body' => 'Please help with my store.',
        ]);
    }

    public function test_seller_account_avoids_repeating_profile_data_in_an_info_card(): void
    {
        $this->actingAs($this->seller)
            ->get(route('seller.account'))
            ->assertOk()
            ->assertSee('Profile Information')
            ->assertSee('badge-seller', false)
            ->assertSee('badge-approved', false)
            ->assertSee('Save Changes')
            ->assertDontSee('Account Info');
    }

    private function user(string $role, string $email, string $status = 'approved'): User
    {
        static $number = 0;
        $number++;

        return User::create([
            'first_name' => ucfirst($role),
            'last_name' => 'SellerChat'.$number,
            'email' => $email,
            'password' => bcrypt('password'),
            'role' => $role,
            'status' => $status,
            'sex' => 'Male',
            'contact_no' => '09171234567',
            'birthday' => '1990-01-01',
            'age' => 35,
            'province' => 'Metro Manila',
            'municipality' => 'Pasig',
            'barangay' => 'San Miguel',
        ]);
    }
}
