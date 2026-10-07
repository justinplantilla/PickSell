<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Complaint;
use App\Models\FinancialTransaction;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\ReturnRequest;
use App\Models\User;
use App\Services\Finance\FinancialLedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminReportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $buyer;

    private User $seller;

    private User $otherSeller;

    private User $courier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = $this->makeUser('admin');
        $this->buyer = $this->makeUser('buyer');
        $this->seller = $this->makeUser('seller');
        $this->otherSeller = $this->makeUser('seller');
        $this->courier = $this->makeUser('courier');
    }

    private function makeUser(string $role): User
    {
        return User::create([
            'first_name' => ucfirst($role),
            'last_name' => Str::random(8),
            'email' => $role.'.'.Str::random(12).'@report.test',
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

    private function makeOrder(string $status, User $seller, array $values = []): Order
    {
        static $sequence = 0;
        $sequence++;

        return Order::create(array_merge([
            'order_number' => 'RPT-'.$sequence.'-'.Str::upper(Str::random(6)),
            'buyer_id' => $this->buyer->id,
            'seller_id' => $seller->id,
            'product_name' => 'Report item',
            'quantity' => 1,
            'amount' => 1000,
            'commission' => 100,
            'status' => $status,
        ], $values));
    }

    private function makeTransaction(Order $order, string $type, float $debit, float $credit, string $referenceType = 'order', ?int $referenceId = null): FinancialTransaction
    {
        return FinancialTransaction::create([
            'order_id' => $order->id,
            'seller_id' => $order->seller_id,
            'type' => $type,
            'debit' => $debit,
            'credit' => $credit,
            'amount' => max($debit, $credit),
            'reference_type' => $referenceType,
            'reference_id' => $referenceId ?? $order->id,
            'status' => 'posted',
            'description' => ucfirst(str_replace('_', ' ', $type)),
        ]);
    }

    public function test_operational_report_applies_date_status_and_seller_filters_to_all_order_metrics(): void
    {
        $completed = $this->makeOrder('completed', $this->seller, ['courier_id' => $this->courier->id]);
        Order::whereKey($completed->id)->update([
            'created_at' => '2026-10-02 09:00:00',
            'completed_at' => '2026-10-02 11:00:00',
        ]);
        $failed = $this->makeOrder('delivery_failed', $this->seller, ['courier_id' => $this->courier->id]);
        Order::whereKey($failed->id)->update(['created_at' => '2026-10-02 10:00:00']);
        OrderStatusHistory::create([
            'order_id' => $failed->id,
            'to_status' => 'delivery_failed',
            'source' => 'test',
        ])->forceFill(['created_at' => '2026-10-02 10:30:00'])->save();
        $outsideDate = $this->makeOrder('delivery_failed', $this->seller);
        Order::whereKey($outsideDate->id)->update(['created_at' => '2026-09-30 10:00:00']);
        $otherSellerOrder = $this->makeOrder('delivery_failed', $this->otherSeller);
        Order::whereKey($otherSellerOrder->id)->update(['created_at' => '2026-10-02 10:00:00']);
        $return = ReturnRequest::create([
            'order_id' => $completed->id,
            'buyer_id' => $this->buyer->id,
            'seller_id' => $this->seller->id,
            'reason' => 'damaged',
            'details' => 'Damaged item.',
            'status' => 'requested',
        ]);
        $return->forceFill(['created_at' => '2026-10-02 12:00:00'])->save();
        $complaint = Complaint::create([
            'filed_by' => $this->buyer->id,
            'against_user_id' => $this->seller->id,
            'order_id' => $completed->id,
            'subject' => 'Damaged item',
            'details' => 'The item arrived damaged.',
            'status' => 'open',
        ]);
        $complaint->forceFill(['created_at' => '2026-10-02 12:00:00'])->save();

        $response = $this->actingAs($this->admin)->get(route('admin.reports', [
            'tab' => 'operational',
            'from' => '2026-10-01',
            'to' => '2026-10-03',
            'seller_id' => $this->seller->id,
            'status' => 'all',
        ]));

        $response->assertOk()
            ->assertSee('Operational')
            ->assertSee('Orders in report')
            ->assertSee('Average fulfillment time')
            ->assertSee('2.00 h')
            ->assertSee('Delivery failures')
            ->assertSee('Returns')
            ->assertSee('Complaints')
            ->assertSee($completed->order_number)
            ->assertDontSee($outsideDate->order_number)
            ->assertDontSee($otherSellerOrder->order_number);

        $this->assertSame(1, $response->viewData('data')['return_count']);
        $this->assertSame(1, $response->viewData('data')['complaint_count']);
        $this->assertSame(1, $response->viewData('data')['delivery_failures']);
        $this->assertSame(2, $response->viewData('data')['order_count']);

        $this->actingAs($this->admin)->get(route('admin.reports', [
            'tab' => 'operational',
            'from' => '2026-10-01',
            'to' => '2026-10-03',
            'seller_id' => $this->seller->id,
            'status' => 'completed',
        ]))->assertOk()->assertSee($completed->order_number)->assertDontSee($failed->order_number);
    }

    public function test_reports_page_defaults_to_operational_report(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.reports'));

        $response->assertOk()
            ->assertSee('Orders in report')
            ->assertSee('Average fulfillment time')
            ->assertDontSee('Ledger Transactions');
        $this->assertSame(0, $response->viewData('data')['order_count']);
    }

    public function test_financial_tab_redirects_with_a_clear_warning_when_ledger_migration_is_missing(): void
    {
        Schema::shouldReceive('hasTable')
            ->once()
            ->with('financial_transactions')
            ->andReturnFalse();

        $this->actingAs($this->admin)->get(route('admin.reports', ['tab' => 'financial']))
            ->assertRedirect(route('admin.reports', [
                'tab' => 'operational',
                'from' => now()->startOfMonth()->toDateString(),
                'to' => now()->toDateString(),
                'status' => 'all',
                'seller_id' => null,
            ]))
            ->assertSessionHas('warning');
    }

    public function test_financial_report_aggregates_posted_ledger_entries_and_reconciles_refund_adjustments(): void
    {
        $order = $this->makeOrder('completed', $this->seller);
        app(FinancialLedgerService::class)->postCompletedOrder($order);
        $this->makeTransaction($order, 'refund_gross', 200, 0, 'refund', 55);
        $this->makeTransaction($order, 'commission_reversal', 0, 20, 'refund', 55);
        $this->makeTransaction($order, 'seller_adjustment', 0, 180, 'refund', 55);

        $response = $this->actingAs($this->admin)->get(route('admin.reports', [
            'tab' => 'financial',
            'from' => now()->startOfMonth()->toDateString(),
            'to' => now()->toDateString(),
            'seller_id' => $this->seller->id,
        ]));

        $response->assertOk()
            ->assertSee('Financial Totals by Seller')
            ->assertSee('₱1,000.00')
            ->assertSee('₱80.00')
            ->assertSee('₱720.00')
            ->assertSee('₱200.00')
            ->assertSee('Sales less refunds');

        $data = $response->viewData('data');
        $this->assertSame(1000.0, $data['gross_sales']);
        $this->assertSame(80.0, $data['commission']);
        $this->assertSame(720.0, $data['seller_net']);
        $this->assertSame(200.0, $data['refunds']);
        $this->assertSame(200.0, $data['adjustments']);
        $this->assertSame($data['financial_total'], $data['commission'] + $data['seller_net']);
        $this->assertSame(6, $data['transaction_count']);

        $this->actingAs($this->admin)->get(route('admin.reports', [
            'tab' => 'financial',
            'from' => now()->startOfMonth()->toDateString(),
            'to' => now()->toDateString(),
            'seller_id' => $this->otherSeller->id,
        ]))->assertOk()->assertSee('₱0.00');
    }

    public function test_report_pdf_export_is_filtered_and_audited(): void
    {
        $order = $this->makeOrder('completed', $this->seller);
        app(FinancialLedgerService::class)->postCompletedOrder($order);

        $response = $this->actingAs($this->admin)->get(route('admin.reports.export', [
            'type' => 'financial',
            'from' => now()->startOfMonth()->toDateString(),
            'to' => now()->toDateString(),
            'status' => 'posted',
            'seller_id' => $this->seller->id,
        ]));

        $response->assertOk()->assertHeader('content-type', 'application/pdf');
        $log = AuditLog::where('action', 'report.exported')->sole();
        $this->assertSame($this->admin->id, $log->actor_id);
        $this->assertSame([
            'report_type' => 'financial',
            'from' => now()->startOfMonth()->toDateString(),
            'to' => now()->toDateString(),
            'status' => 'posted',
            'seller_id' => $this->seller->id,
        ], $log->metadata);
    }

    public function test_report_rejects_invalid_filter_combinations_and_does_not_audit_failed_export(): void
    {
        $this->actingAs($this->admin)->get(route('admin.reports', [
            'tab' => 'financial',
            'status' => 'completed',
        ]))->assertSessionHasErrors('status');

        $this->get(route('admin.reports.export', [
            'type' => 'operational',
            'from' => '2026-10-03',
            'to' => '2026-10-01',
            'status' => 'all',
        ]))->assertSessionHasErrors('to');

        $this->assertDatabaseCount('audit_logs', 0);
    }
}
