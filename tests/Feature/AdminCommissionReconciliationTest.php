<?php

namespace Tests\Feature;

use App\Models\FinancialTransaction;
use App\Models\Order;
use App\Models\Refund;
use App\Models\User;
use App\Services\Finance\FinancialLedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminCommissionReconciliationTest extends TestCase
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
            'email' => $role.'.'.Str::random(12).'@reconciliation.test',
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

    private function order(): Order
    {
        return Order::create([
            'order_number' => 'REC-'.Str::upper(Str::random(10)),
            'buyer_id' => $this->buyer->id,
            'seller_id' => $this->seller->id,
            'product_name' => 'Reconciliation item',
            'quantity' => 1,
            'amount' => 1000,
            'commission' => 100,
            'status' => 'completed',
        ]);
    }

    public function test_commission_page_reports_a_clean_order_and_refund_ledger(): void
    {
        $order = $this->order();
        app(FinancialLedgerService::class)->postCompletedOrder($order, $this->admin);
        $refund = Refund::create([
            'order_id' => $order->id,
            'requested_by' => $this->buyer->id,
            'approved_by' => $this->admin->id,
            'amount' => 200,
            'commission_reversal' => 20,
            'seller_adjustment' => 180,
            'status' => 'approved',
            'reason' => 'Approved test refund.',
            'approved_at' => now(),
        ]);
        app(FinancialLedgerService::class)->postApprovedRefund($refund, $this->admin);

        $response = $this->actingAs($this->admin)->get('/admin/commission');

        $response->assertOk()
            ->assertSee('Commission reconciliation')
            ->assertSee('All source records in this period reconcile with the financial ledger.');
        $this->assertSame(2, $response->viewData('reconciliation')['checked']);
        $this->assertSame(2, $response->viewData('reconciliation')['reconciled']);
        $this->assertCount(0, $response->viewData('reconciliation')['discrepancies']);
    }

    public function test_commission_page_flags_missing_duplicate_and_mismatched_ledger_entries(): void
    {
        $order = $this->order();
        app(FinancialLedgerService::class)->postCompletedOrder($order, $this->admin);
        FinancialTransaction::where('order_id', $order->id)->where('type', 'commission')->delete();
        FinancialTransaction::create([
            'order_id' => $order->id,
            'seller_id' => $this->seller->id,
            'type' => 'order_gross',
            'debit' => 999,
            'credit' => 0,
            'amount' => 999,
            'reference_type' => 'order',
            'reference_id' => $order->id,
            'status' => 'posted',
        ]);

        $response = $this->actingAs($this->admin)->get('/admin/commission');

        $response->assertOk()
            ->assertSee('Commission reconciliation')
            ->assertSee('source record(s) need review.')
            ->assertSee('Missing commission entry.')
            ->assertSee('Expected one order_gross entry; found 2.')
            ->assertSee('order_gross amount or debit/credit direction does not match its source record.');
        $this->assertSame(0, $response->viewData('reconciliation')['reconciled']);
        $this->assertCount(1, $response->viewData('reconciliation')['discrepancies']);
    }
}
