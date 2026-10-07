<?php

namespace Tests\Feature;

use App\Auth\Permission;
use App\Models\AuditLog;
use App\Models\Order;
use App\Models\ReturnRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminReturnManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $buyer;
    private User $seller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = $this->user('admin');
        $this->buyer = $this->user('buyer');
        $this->seller = $this->user('seller');
    }

    private function user(string $role): User
    {
        return User::create([
            'first_name' => ucfirst($role),
            'last_name' => Str::random(8),
            'email' => $role.'.'.Str::random(12).'@returns.test',
            'password' => bcrypt('password'),
            'role' => $role,
            'status' => 'approved',
            'sex' => 'Male',
            'contact_no' => '09171234567',
            'birthday' => '1990-01-01',
            'age' => 36,
            'province' => 'Metro Manila',
            'municipality' => 'Pasig',
            'barangay' => 'San Miguel',
        ]);
    }

    private function returnRequest(string $status = 'requested'): ReturnRequest
    {
        $order = Order::create([
            'order_number' => 'RET-ADMIN-'.Str::upper(Str::random(8)),
            'buyer_id' => $this->buyer->id,
            'seller_id' => $this->seller->id,
            'product_name' => 'Return review item',
            'quantity' => 1,
            'amount' => 1000,
            'commission' => 100,
            'status' => 'completed',
        ]);

        return ReturnRequest::create([
            'order_id' => $order->id,
            'buyer_id' => $this->buyer->id,
            'seller_id' => $this->seller->id,
            'reason' => 'damaged',
            'details' => 'The item was damaged on arrival.',
            'status' => $status,
            'refund_amount' => 850,
        ]);
    }

    public function test_admin_can_approve_a_requested_return_and_audit_the_transition(): void
    {
        $request = $this->returnRequest();

        $this->actingAs($this->admin)
            ->patch(route('admin.returns.approve', $request), ['admin_notes' => 'Evidence supports a return.'])
            ->assertRedirect();

        $request->refresh();
        $this->assertSame('awaiting_item', $request->status);
        $this->assertNotNull($request->approved_at);
        $this->assertSame('awaiting_item', $request->events()->sole()->to_status);
        $this->assertSame(Permission::RETURNS_MANAGE, AuditLog::where('action', 'return.admin_approved')->sole()->permission);
        $this->assertGreaterThan(0, $this->buyer->notifications()->count());
        $this->assertGreaterThan(0, $this->seller->notifications()->count());

        $this->patch(route('admin.returns.approve', $request), ['admin_notes' => 'Duplicate decision.'])
            ->assertStatus(422);
    }

    public function test_admin_can_reject_only_a_requested_return(): void
    {
        $request = $this->returnRequest();

        $this->actingAs($this->admin)
            ->patch(route('admin.returns.reject', $request), ['admin_notes' => 'Evidence does not support this return.'])
            ->assertRedirect();

        $request->refresh();
        $this->assertSame('rejected', $request->status);
        $this->assertSame('admin_rejected', $request->admin_decision);
        $this->assertNotNull($request->rejected_at);
        $this->assertSame('admin_rejected', $request->events()->sole()->event_type);
    }

    public function test_admin_inspection_and_refund_approval_follow_the_required_sequence(): void
    {
        $request = $this->returnRequest('received');

        $this->actingAs($this->admin)
            ->patch(route('admin.returns.approve-refund', $request), [
                'admin_notes' => 'Premature refund.',
                'refund_amount' => 500,
            ])
            ->assertStatus(422);

        $this->patch(route('admin.returns.inspect', $request), ['admin_notes' => 'Item condition verified.'])
            ->assertRedirect();
        $this->assertSame('inspected', $request->fresh()->status);
        $this->assertSame($this->admin->id, $request->fresh()->reviewed_by);
        $this->assertNotNull($request->fresh()->reviewed_at);

        $this->patch(route('admin.returns.approve-refund', $request), [
            'admin_notes' => 'Partial refund approved.',
            'refund_amount' => 500,
        ])->assertRedirect();

        $request->refresh();
        $this->assertSame('approved_for_refund', $request->status);
        $this->assertSame('500.00', $request->refund_amount);
        $this->assertSame('refund_requested', $request->admin_decision);
        $this->assertNull($request->refund_due_at);
        $this->assertSame(2, $request->events()->count());
    }

    public function test_refund_approval_cannot_exceed_the_order_amount(): void
    {
        $request = $this->returnRequest('inspected');

        $this->actingAs($this->admin)
            ->from(route('admin.returns.show', $request))
            ->patch(route('admin.returns.approve-refund', $request), [
                'admin_notes' => 'Too high.',
                'refund_amount' => 1000.01,
            ])
            ->assertSessionHasErrors('refund_amount');

        $this->assertSame('inspected', $request->fresh()->status);
        $this->assertSame(0, AuditLog::where('action', 'return.admin_refund_approved')->count());
    }

    public function test_return_management_requires_its_own_permission(): void
    {
        $request = $this->returnRequest();
        config(['permissions.roles.admin' => [Permission::RETURNS_VIEW]]);

        $this->actingAs($this->admin)
            ->get(route('admin.returns.show', $request))
            ->assertOk()
            ->assertDontSee(route('admin.returns.approve', $request), false);

        $this->patch(route('admin.returns.approve', $request), ['admin_notes' => 'Not authorized.'])
            ->assertForbidden();
        $this->assertSame('requested', $request->fresh()->status);
    }
}
