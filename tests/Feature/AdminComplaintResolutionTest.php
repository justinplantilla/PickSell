<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Complaint;
use App\Models\Order;
use App\Models\ReturnRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminComplaintResolutionTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $buyer;
    private User $seller;
    private Order $order;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = $this->user('admin');
        $this->buyer = $this->user('buyer');
        $this->seller = $this->user('seller');
        $this->order = Order::create([
            'order_number' => 'ORD-COMPLAINT-1',
            'buyer_id' => $this->buyer->id,
            'seller_id' => $this->seller->id,
            'product_name' => 'Complaint test item',
            'quantity' => 2,
            'amount' => 1200,
            'commission' => 120,
            'status' => 'completed',
        ]);
    }

    private function user(string $role): User
    {
        static $id = 0;
        $id++;

        return User::create([
            'first_name' => ucfirst($role),
            'last_name' => 'Complaint'.$id,
            'email' => $role.$id.'@complaint.test',
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

    private function complaint(): Complaint
    {
        return Complaint::create([
            'filed_by' => $this->buyer->id,
            'against_user_id' => $this->seller->id,
            'order_id' => $this->order->id,
            'subject' => 'Item issue',
            'details' => 'The delivered item does not match the listing.',
            'evidence_path' => 'complaints/item-photo.jpg',
            'status' => 'open',
        ]);
    }

    public function test_complaint_detail_shows_order_participants_and_evidence(): void
    {
        $complaint = $this->complaint();

        $this->actingAs($this->admin)
            ->get(route('admin.complaints.show', $complaint))
            ->assertOk()
            ->assertSee($this->order->order_number)
            ->assertSee($this->buyer->full_name)
            ->assertSee($this->seller->full_name)
            ->assertSee('complaints/item-photo.jpg');
    }

    public function test_no_financial_action_resolution_is_explicit_notified_and_audited(): void
    {
        $complaint = $this->complaint();

        $this->actingAs($this->admin)
            ->patch(route('admin.complaints.resolve', $complaint), [
                'resolution_type' => 'no_financial_action',
                'resolution_notes' => 'Evidence reviewed; no refund or return is warranted.',
            ])
            ->assertRedirect(route('admin.complaints.show', $complaint));

        $complaint->refresh();
        $this->assertSame('resolved', $complaint->status);
        $this->assertSame('no_financial_action', $complaint->resolution_type);
        $this->assertNotNull($complaint->resolved_at);
        $this->assertSame(1, AuditLog::where('action', 'complaint.resolved')->count());
        $this->assertGreaterThan(0, $this->buyer->notifications()->count());
        $this->assertGreaterThan(0, $this->seller->notifications()->count());
        $this->assertDatabaseCount('return_requests', 0);
    }

    public function test_refund_resolution_uses_existing_refund_tracking_and_audits_the_amount(): void
    {
        $complaint = $this->complaint();

        $this->actingAs($this->admin)
            ->patch(route('admin.complaints.resolve', $complaint), [
                'resolution_type' => 'refund',
                'refund_amount' => '450.00',
                'resolution_notes' => 'Partial refund approved after evidence review.',
            ])
            ->assertRedirect(route('admin.complaints.show', $complaint));

        $request = ReturnRequest::sole();
        $this->assertSame($this->order->id, $request->order_id);
        $this->assertSame('approved_for_refund', $request->status);
        $this->assertSame('450.00', $request->refund_amount);
        $this->assertSame('resolved', $complaint->fresh()->status);
        $this->assertSame('requested', $request->refunds()->sole()->status);
        $this->assertSame(450.0, (float) AuditLog::where('action', 'complaint.resolved')->sole()->metadata['refund_amount']);
    }

    public function test_refund_requires_an_order_and_cannot_exceed_its_amount(): void
    {
        $complaint = $this->complaint();
        $complaint->update(['order_id' => null]);

        $this->actingAs($this->admin)
            ->from(route('admin.complaints.show', $complaint))
            ->patch(route('admin.complaints.resolve', $complaint), [
                'resolution_type' => 'refund',
                'refund_amount' => '100.00',
                'resolution_notes' => 'Refund requested.',
            ])
            ->assertSessionHasErrors('order_id');
        $this->assertSame('open', $complaint->fresh()->status);

        $complaint->update(['order_id' => $this->order->id]);
        $this->from(route('admin.complaints.show', $complaint))
            ->patch(route('admin.complaints.resolve', $complaint), [
                'resolution_type' => 'refund',
                'refund_amount' => '1200.01',
                'resolution_notes' => 'Refund requested.',
            ])
            ->assertSessionHasErrors('refund_amount');
        $this->assertSame('open', $complaint->fresh()->status);
        $this->assertDatabaseCount('return_requests', 0);
    }

    public function test_return_resolution_authorizes_a_return_in_the_existing_return_workflow(): void
    {
        $complaint = $this->complaint();

        $this->actingAs($this->admin)
            ->patch(route('admin.complaints.resolve', $complaint), [
                'resolution_type' => 'return',
                'resolution_notes' => 'Return the item for seller inspection.',
            ])
            ->assertRedirect(route('admin.complaints.show', $complaint));

        $request = ReturnRequest::sole();
        $this->assertSame('awaiting_item', $request->status);
        $this->assertSame('complaint_return', $request->admin_decision);
        $this->assertSame('resolved', $complaint->fresh()->status);
        $this->assertDatabaseHas('return_request_events', [
            'return_request_id' => $request->id,
            'event_type' => 'complaint_return_authorized',
        ]);
    }
}
