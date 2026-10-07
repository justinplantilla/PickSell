<?php

namespace Tests\Feature;

use App\Auth\Permission;
use App\Mail\AccountStatusChangedMail;
use App\Models\AuditLog;
use App\Models\Complaint;
use App\Models\Order;
use App\Models\RegistrationReview;
use App\Models\User;
use App\Models\UserStatusHistory;
use App\Support\RegistrationChecklist;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/** Module 3 — User Accounts: status history, transitions, confirmation, profile inspection, audit. */
class UserAccountModerationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Notification::fake();
        $this->admin = $this->user('admin');
    }

    private function user(string $role, string $status = 'approved', array $overrides = []): User
    {
        static $n = 0;
        $n++;

        return User::create(array_merge([
            'first_name' => ucfirst($role), 'last_name' => "Acct{$n}", 'email' => "{$role}{$n}@acct.test",
            'password' => bcrypt('password'), 'role' => $role, 'status' => $status, 'sex' => 'Male',
            'contact_no' => '09171234567', 'birthday' => '1990-01-01', 'age' => 35,
            'province' => 'Metro Manila', 'municipality' => 'Pasig', 'barangay' => 'San Miguel',
            'id_upload' => 'uploads/ids/id.jpg',
            'business_name' => $role === 'seller' ? 'Acct Store' : null,
            'business_permit' => $role === 'seller' ? 'uploads/permits/p.pdf' : null,
        ], $overrides));
    }

    private function suspend(User $target, array $overrides = [])
    {
        return $this->actingAs($this->admin)->patch(route('admin.users.status', $target), array_merge([
            'status' => 'suspended', 'reason' => 'Repeated late shipments reported by buyers.', 'confirm' => '1',
        ], $overrides));
    }

    // Every status change is recorded ------------------------------------------------------

    public function test_admin_status_change_is_recorded_with_actor_and_reason(): void
    {
        $seller = $this->user('seller');

        $this->suspend($seller)->assertRedirect()->assertSessionHasNoErrors();

        $history = UserStatusHistory::sole();
        $this->assertSame($seller->id, $history->user_id);
        $this->assertSame($this->admin->id, $history->changed_by);
        $this->assertSame('approved', $history->from_status);
        $this->assertSame('suspended', $history->to_status);
        $this->assertSame('Repeated late shipments reported by buyers.', $history->reason);
        Mail::assertSent(AccountStatusChangedMail::class, fn ($mail) => $mail->hasTo($seller->email));
    }

    public function test_status_changes_outside_the_users_module_are_recorded_too(): void
    {
        // Registration decision (Admin)
        $applicant = $this->user('buyer', 'pending');
        $this->actingAs($this->admin)->patch(route('admin.registrations.disapprove', $applicant), ['reason' => 'The ID photo is unreadable.']);
        $this->assertDatabaseHas('user_status_histories', ['user_id' => $applicant->id, 'from_status' => 'pending', 'to_status' => 'disapproved', 'reason' => 'The ID photo is unreadable.', 'changed_by' => $this->admin->id]);

        $seller = $this->user('seller', 'pending');
        $this->actingAs($this->admin)->patch(route('admin.registrations.approve', $seller), [
            'checklist' => array_fill_keys(array_keys(RegistrationChecklist::for($seller)), '1'),
        ]);
        $this->assertDatabaseHas('user_status_histories', ['user_id' => $seller->id, 'to_status' => 'approved']);

        // Logistics portal suspending a rider
        $logistics = $this->user('logistics');
        $rider = $this->user('courier');
        $this->actingAs($logistics)->patch(route('logistics.riders.status', $rider), ['status' => 'suspended']);
        $this->assertDatabaseHas('user_status_histories', ['user_id' => $rider->id, 'from_status' => 'approved', 'to_status' => 'suspended', 'changed_by' => $logistics->id]);
    }

    // Invalid transitions are rejected ------------------------------------------------------

    public function test_invalid_transitions_are_rejected(): void
    {
        $active = $this->user('buyer');
        $this->actingAs($this->admin)->patch(route('admin.users.status', $active), ['status' => 'approved'])->assertStatus(409);

        $deactivated = $this->user('buyer', 'deactivated');
        $this->suspend($deactivated)->assertForbidden(); // deactivated → suspended is not a transition

        $pending = $this->user('seller', 'pending');
        $this->actingAs($this->admin)->patch(route('admin.users.status', $pending), ['status' => 'approved'])->assertForbidden();
        $this->suspend($pending)->assertForbidden();

        $disapproved = $this->user('buyer', 'disapproved');
        $this->actingAs($this->admin)->patch(route('admin.users.status', $disapproved), ['status' => 'approved'])->assertForbidden();

        $this->actingAs($this->admin)->patch(route('admin.users.status', $active), ['status' => 'banned', 'reason' => 'Not a PickSell status.', 'confirm' => '1'])->assertForbidden();

        $this->assertSame(0, UserStatusHistory::count());
        $this->assertSame(['approved', 'deactivated', 'pending', 'disapproved'],
            [$active->fresh()->status, $deactivated->fresh()->status, $pending->fresh()->status, $disapproved->fresh()->status]);
    }

    public function test_valid_transition_path(): void
    {
        $buyer = $this->user('buyer');

        $this->suspend($buyer)->assertSessionHasNoErrors();
        $this->actingAs($this->admin)->patch(route('admin.users.status', $buyer), ['status' => 'approved'])->assertSessionHasNoErrors();
        $this->actingAs($this->admin)->patch(route('admin.users.status', $buyer), ['status' => 'deactivated', 'reason' => 'Account closed at the owner’s request.', 'confirm' => '1'])->assertSessionHasNoErrors();
        $this->actingAs($this->admin)->patch(route('admin.users.status', $buyer), ['status' => 'approved', 'reason' => 'Owner asked to reopen.'])->assertSessionHasNoErrors();

        $this->assertSame(
            [['approved', 'suspended'], ['suspended', 'approved'], ['approved', 'deactivated'], ['deactivated', 'approved']],
            UserStatusHistory::orderBy('id')->get()->map(fn ($h) => [$h->from_status, $h->to_status])->all(),
        );
    }

    public function test_concurrent_change_is_rejected_by_the_service_lock(): void
    {
        $buyer = $this->user('buyer');
        $stale = User::find($buyer->id);
        $buyer->update(['status' => 'deactivated']); // another admin acted first

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(\App\Services\Admin\UserModerationService::class)->changeStatus($stale, $this->admin, 'suspended', 'Too late to suspend this.');
    }

    // Suspension / deactivation requires confirmation ---------------------------------------

    public function test_suspension_and_deactivation_require_reason_and_confirmation(): void
    {
        $buyer = $this->user('buyer');

        $this->suspend($buyer, ['confirm' => null])->assertSessionHasErrors('confirm');
        $this->suspend($buyer, ['reason' => ''])->assertSessionHasErrors('reason');
        $this->suspend($buyer, ['reason' => 'Bad'])->assertSessionHasErrors('reason');
        $this->actingAs($this->admin)->patch(route('admin.users.status', $buyer), ['status' => 'deactivated'])
            ->assertSessionHasErrors(['reason', 'confirm']);
        $this->assertSame('approved', $buyer->fresh()->status);
        $this->assertSame(0, UserStatusHistory::count());

        // Reactivation needs neither.
        $suspended = $this->user('buyer', 'suspended');
        $this->actingAs($this->admin)->patch(route('admin.users.status', $suspended), ['status' => 'approved'])->assertSessionHasNoErrors();
        $this->assertSame('approved', $suspended->fresh()->status);
    }

    public function test_detail_page_offers_only_valid_actions_with_confirmation(): void
    {
        $buyer = $this->user('buyer');
        $page = $this->actingAs($this->admin)->get(route('admin.users.show', $buyer))->assertOk();
        $page->assertSee('Suspend account')->assertSee('Deactivate account')->assertDontSee('Reactivate account')
            ->assertSee('name="confirm"', false)->assertSee('I confirm I want to suspend this account.');

        $deactivated = $this->user('buyer', 'deactivated');
        $this->actingAs($this->admin)->get(route('admin.users.show', $deactivated))
            ->assertSee('Reactivate account')->assertDontSee('Suspend account');

        config(['permissions.roles.admin' => [Permission::USERS_VIEW]]);
        $this->actingAs($this->admin)->get(route('admin.users.show', $buyer))->assertOk()
            ->assertDontSee('Suspend account')->assertSee('requires the user management permission');
    }

    public function test_user_list_shows_only_authorized_status_actions_for_each_account(): void
    {
        $active = $this->user('buyer');
        $suspended = $this->user('seller', 'suspended');
        $pending = $this->user('buyer', 'pending');
        $pendingCourier = $this->user('courier', 'pending');

        $this->actingAs($this->admin)->get(route('admin.users'))
            ->assertOk()
            ->assertSee('Review / Decide')
            ->assertSee('registration-review-link')
            ->assertSee(route('admin.registrations.show', $pending), false)
            ->assertSee('Review application')
            ->assertSee('btn-suspend', false)
            ->assertSee('btn-deactivate', false)
            ->assertSee('Suspend', false)
            ->assertSee('Deactivate', false)
            ->assertSee('Reactivate', false)
            ->assertSee('name="confirm"', false);

        $this->actingAs($this->admin)->get(route('admin.registrations.show', $pending))
            ->assertOk()
            ->assertSee('>Approve</button>', false);
        $this->actingAs($this->admin)->get(route('admin.registrations.show', $pendingCourier))
            ->assertOk()
            ->assertSee('Courier applications are reviewed in the Logistics portal.');

        config(['permissions.roles.admin' => [Permission::USERS_VIEW]]);
        $this->actingAs($this->admin)->get(route('admin.users'))
            ->assertOk()
            ->assertSee($active->email)
            ->assertSee($suspended->email)
            ->assertSee($pending->email)
            ->assertDontSee('Review / Decide')
            ->assertDontSee('Suspend account')
            ->assertDontSee('Deactivate account')
            ->assertDontSee('Reactivate account');
    }

    // User history is visible ---------------------------------------------------------------

    public function test_profile_shows_history_orders_complaints_and_compliance(): void
    {
        $seller = $this->user('seller', 'pending');
        $buyer = $this->user('buyer');
        $this->actingAs($this->admin)->patch(route('admin.registrations.approve', $seller), [
            'checklist' => array_fill_keys(array_keys(RegistrationChecklist::for($seller)), '1'), 'reason' => 'Permit verified.',
        ]);
        $this->suspend($seller->fresh());
        Order::create(['order_number' => 'ORD-ACCT-1', 'buyer_id' => $buyer->id, 'seller_id' => $seller->id, 'product_name' => 'Tote',
            'quantity' => 1, 'amount' => 750, 'commission' => 75, 'status' => 'completed']);
        Complaint::create(['filed_by' => $buyer->id, 'against_user_id' => $seller->id, 'subject' => 'Item not as described', 'details' => 'x', 'status' => 'open']);
        $this->actingAs($this->admin)->patch(route('admin.users.status', $seller), ['status' => 'approved']);
        $this->actingAs($this->admin)->patch(route('admin.compliance.warn', $seller), ['warning' => 'Update your product photos.']);

        $this->actingAs($this->admin)->get(route('admin.users.show', $seller))->assertOk()
            ->assertSeeInOrder(['Profile', 'Account status', 'Status history', 'Registration history', 'Order summary', 'Complaint history', 'Compliance history', 'Audit history'])
            ->assertSee('Repeated late shipments reported by buyers.')
            ->assertSee('Permit verified.')
            ->assertSee('ORD-ACCT-1')->assertSee('₱750.00')
            ->assertSee('Item not as described')->assertSee('Filed against this user by')
            ->assertSee('Update your product photos.')
            ->assertSee('user.status_changed');

        $this->actingAs($this->admin)->get(route('admin.users'))->assertOk()
            ->assertSee(route('admin.users.show', $seller), false);
    }

    public function test_list_filters_and_order_counts(): void
    {
        $seller = $this->user('seller');
        $buyer = $this->user('buyer');
        $old = $this->user('buyer', 'suspended');
        User::whereKey($old->id)->update(['created_at' => now()->subDays(120)]);
        foreach (range(1, 3) as $i) {
            Order::create(['order_number' => "ORD-L-{$i}", 'buyer_id' => $buyer->id, 'seller_id' => $seller->id, 'product_name' => 'x',
                'quantity' => 1, 'amount' => 100, 'commission' => 10, 'status' => 'placed']);
        }

        $html = $this->actingAs($this->admin)->get(route('admin.users', ['role' => 'buyer', 'status' => 'approved']))->assertOk()->getContent();
        $this->assertStringContainsString($buyer->email, $html);
        $this->assertStringNotContainsString($old->email, $html);
        $this->assertMatchesRegularExpression('/' . preg_quote($buyer->email, '/') . '.*?oversight-number">3</s', $html);

        $this->actingAs($this->admin)->get(route('admin.users', ['joined' => 'over_90d']))->assertSee($old->email)->assertDontSee($buyer->email);
        $this->actingAs($this->admin)->get(route('admin.users', ['search' => 'Acct Store']))->assertSee($seller->email)->assertDontSee($buyer->email);
        $this->actingAs($this->admin)->get(route('admin.users.show', $this->admin))->assertNotFound();
    }

    // Action is audited ---------------------------------------------------------------------

    public function test_status_change_is_audited_with_reason_and_history_link(): void
    {
        $buyer = $this->user('buyer');
        $this->suspend($buyer);

        $log = AuditLog::where('action', 'user.status_changed')->sole();
        $this->assertSame($this->admin->id, $log->actor_id);
        $this->assertTrue($log->subject->is($buyer));
        $this->assertSame(Permission::USERS_MANAGE, $log->permission);
        $this->assertSame(['status' => ['from' => 'approved', 'to' => 'suspended']], $log->changes);
        $this->assertSame('Repeated late shipments reported by buyers.', $log->metadata['reason']);
        $this->assertSame(UserStatusHistory::sole()->id, $log->metadata['history_id']);
    }
}
