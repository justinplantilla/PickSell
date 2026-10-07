<?php

namespace Tests\Feature;

use App\Auth\Permission;
use App\Models\AuditLog;
use App\Models\FinancialTransaction;
use App\Models\Order;
use App\Models\Refund;
use App\Models\ReturnRequest;
use App\Models\User;
use App\Services\Finance\FinancialLedgerService;
use App\Services\Finance\FinancialSummary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminRefundManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $buyer;

    private User $seller;

    private Order $order;

    private ReturnRequest $returnRequest;

    private Refund $refund;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = $this->user('admin');
        $this->buyer = $this->user('buyer');
        $this->seller = $this->user('seller');
        $this->order = Order::create([
            'order_number' => 'REF-'.Str::upper(Str::random(10)),
            'buyer_id' => $this->buyer->id,
            'seller_id' => $this->seller->id,
            'product_name' => 'Refund ledger item',
            'quantity' => 1,
            'amount' => 1000,
            'commission' => 100,
            'status' => 'completed',
        ]);
        $this->returnRequest = ReturnRequest::create([
            'order_id' => $this->order->id,
            'buyer_id' => $this->buyer->id,
            'seller_id' => $this->seller->id,
            'reason' => 'damaged',
            'details' => 'Item arrived damaged.',
            'status' => 'approved_for_refund',
            'refund_amount' => 500,
        ]);
        $this->refund = Refund::create([
            'order_id' => $this->order->id,
            'return_request_id' => $this->returnRequest->id,
            'requested_by' => $this->buyer->id,
            'amount' => 500,
            'status' => 'requested',
            'reason' => 'Partial refund for damaged item.',
        ]);
    }

    private function user(string $role): User
    {
        return User::create([
            'first_name' => ucfirst($role),
            'last_name' => Str::random(8),
            'email' => $role.'.'.Str::random(12).'@refund.test',
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

    public function test_partial_refund_posts_balanced_commission_and_seller_ledger_without_changing_order_totals(): void
    {
        $amount = $this->order->amount;
        $commission = $this->order->commission;
        config(['app.platform_commission_rate' => 25]);
        app(FinancialLedgerService::class)->postCompletedOrder($this->order);

        $this->actingAs($this->admin)
            ->patch(route('admin.refunds.approve', $this->refund), [
                'reason' => 'Partial refund approved based on reviewed evidence.',
            ])
            ->assertRedirect();

        $this->refund->refresh();
        $this->assertSame('approved', $this->refund->status);
        $this->assertSame('50.00', $this->refund->commission_reversal);
        $this->assertSame('450.00', $this->refund->seller_adjustment);
        $this->assertSame($this->admin->id, $this->refund->approved_by);
        $this->assertSame('Partial refund for damaged item.', $this->refund->reason);
        $this->assertSame('Partial refund approved based on reviewed evidence.', $this->refund->decision_notes);

        $entries = $this->refund->financialTransactions;
        $this->assertCount(3, $entries);
        $debits = $entries->sum(fn ($entry) => (float) $entry->debit);
        $credits = $entries->sum(fn ($entry) => (float) $entry->credit);
        $this->assertSame(500.0, $debits);
        $this->assertSame(500.0, $credits);
        $this->assertSame($this->seller->id, $entries->first()->seller_id);
        $this->assertSame('refund', $entries->first()->reference_type);
        $this->assertSame($this->refund->id, $entries->first()->reference_id);
        $orderTransactions = FinancialTransaction::where('order_id', $this->order->id)->get();
        $this->assertSame(1500.0, $orderTransactions->sum(fn ($entry) => (float) $entry->debit));
        $this->assertSame(1500.0, $orderTransactions->sum(fn ($entry) => (float) $entry->credit));
        $this->assertSame($amount, $this->order->fresh()->amount);
        $this->assertSame($commission, $this->order->fresh()->commission);
        $summary = app(FinancialSummary::class)->forPeriod(now()->startOfDay(), now()->endOfDay());
        $this->assertSame(50.0, $summary['commission']);
        $this->assertSame(450.0, $summary['net_to_sellers']);
        $this->assertSame(Permission::REFUNDS_APPROVE, AuditLog::where('action', 'refund.approved')->sole()->permission);
        $this->assertGreaterThan(0, $this->buyer->notifications()->count());
        $this->assertGreaterThan(0, $this->seller->notifications()->count());

        $this->patch(route('admin.refunds.approve', $this->refund), [
            'reason' => 'Duplicate approval.',
        ])->assertStatus(422);
        app(FinancialLedgerService::class)->postApprovedRefund($this->refund, $this->admin);
        $this->assertDatabaseCount('financial_transactions', 6);
    }

    public function test_full_refund_reverses_all_commission_and_records_no_seller_adjustment(): void
    {
        $this->refund->update(['amount' => 1000]);

        $this->actingAs($this->admin)
            ->patch(route('admin.refunds.approve', $this->refund), [
                'reason' => 'Full refund approved.',
            ])
            ->assertRedirect();

        $this->assertSame('100.00', $this->refund->fresh()->commission_reversal);
        $this->assertSame('900.00', $this->refund->fresh()->seller_adjustment);
        $this->assertSame(1000.0, (float) FinancialTransaction::where('reference_type', 'refund')
            ->where('reference_id', $this->refund->id)->sum('credit'));
    }

    public function test_refund_rejection_releases_the_return_for_a_corrected_refund_request(): void
    {
        $this->actingAs($this->admin)
            ->patch(route('admin.refunds.reject', $this->refund), [
                'reason' => 'The submitted refund amount needs correction.',
            ])
            ->assertRedirect();

        $this->assertSame('rejected', $this->refund->fresh()->status);
        $this->assertSame('inspected', $this->returnRequest->fresh()->status);
        $this->assertSame('The submitted refund amount needs correction.', $this->refund->fresh()->decision_notes);
        $this->assertDatabaseCount('financial_transactions', 0);
        $this->assertSame(Permission::REFUNDS_MANAGE, AuditLog::where('action', 'refund.rejected')->sole()->permission);
    }

    public function test_refund_amounts_cannot_exceed_the_order_balance_across_multiple_refunds(): void
    {
        $this->refund->update(['amount' => 600]);
        $second = Refund::create([
            'order_id' => $this->order->id,
            'requested_by' => $this->buyer->id,
            'amount' => 500,
            'status' => 'requested',
            'reason' => 'Another pending refund.',
        ]);

        $this->actingAs($this->admin)
            ->patch(route('admin.refunds.approve', $this->refund), [
                'reason' => 'Approve first refund.',
            ])
            ->assertSessionHasErrors('refund');

        $this->assertSame('requested', $this->refund->fresh()->status);
        $this->assertSame('requested', $second->fresh()->status);
        $this->assertDatabaseCount('financial_transactions', 0);
    }

    public function test_refund_approval_requires_refund_management_permission(): void
    {
        config(['permissions.roles.admin' => [Permission::REFUNDS_VIEW]]);

        $this->actingAs($this->admin)
            ->get(route('admin.refunds'))
            ->assertOk()
            ->assertDontSee(route('admin.refunds.approve', $this->refund), false);

        $this->patch(route('admin.refunds.approve', $this->refund), [
            'reason' => 'Not authorized.',
        ])->assertForbidden();

        $this->assertSame('requested', $this->refund->fresh()->status);
        $this->assertDatabaseCount('financial_transactions', 0);
    }

    public function test_seller_cannot_mark_a_refund_processed_before_finance_approves_it(): void
    {
        $this->actingAs($this->seller)
            ->patch(route('seller.returns.complete', $this->returnRequest))
            ->assertStatus(422);

        $this->assertSame('approved_for_refund', $this->returnRequest->fresh()->status);
        $this->assertSame('requested', $this->refund->fresh()->status);
    }
}
