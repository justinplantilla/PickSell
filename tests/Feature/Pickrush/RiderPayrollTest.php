<?php

namespace Tests\Feature\Pickrush;

use App\Pickrush\Enums\StaffRole;
use App\Pickrush\Models\Hub;
use App\Pickrush\Models\Parcel;
use App\Pickrush\Models\Rider;
use App\Pickrush\Models\RiderDeduction;
use App\Pickrush\Models\RiderPayslip;
use App\Pickrush\Models\RiderWithdrawal;
use App\Pickrush\Models\Staff;
use App\Pickrush\Services\OperationRefused;
use App\Pickrush\Services\ParcelRegistrar;
use App\Pickrush\Services\Rider\RiderPayroll;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** The rider wallet and weekly payroll: cutoff, release, deductions and disputes, withdrawals. */
class RiderPayrollTest extends TestCase
{
    use BuildsNetwork, RefreshDatabase;

    private Hub $tanauan;

    private Staff $manager;

    private Rider $rider;

    private RiderPayroll $payroll;

    protected function setUp(): void
    {
        parent::setUp();
        // Wednesday, Nov 18 2026: the week of Monday Nov 16.
        Carbon::setTestNow('2026-11-18 10:00:00');
        $this->buildNetwork();
        $this->tanauan = $this->station('041029000');
        $this->manager = $this->staff(StaffRole::HubManager, $this->tanauan);
        $this->rider = Rider::withoutGlobalScopes()->create([
            'station_hub_id' => $this->tanauan->id, 'first_name' => 'Juan', 'last_name' => 'Cruz', 'mobile_no' => '09181234567',
            'email' => 'juan@rider.test', 'password' => 'Pass-word1!', 'vehicle_type' => 'motorcycle', 'plate_no' => 'NAB 1234',
            'status' => 'active', 'shift_status' => 'on_shift', 'last_seen_at' => now(), 'cod_limit' => 5000, 'max_parcels' => 20,
            'payout_method' => 'gcash', 'payout_account' => '09179876543', 'created_by' => $this->manager->id,
        ]);
        $this->payroll = app(RiderPayroll::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function parcel(): Parcel
    {
        return app(ParcelRegistrar::class)->register([
            'shipper_name' => 'Tote House', 'pickup_address' => 'Altura Bata', 'pickup_barangay_code' => '041029001', 'pickup_municipality_code' => '041029000',
            'consignee_name' => 'Ana', 'delivery_address' => 'Altura Bata', 'delivery_barangay_code' => '041029001', 'delivery_municipality_code' => '041029000',
            'cod_amount' => 500,
        ]);
    }

    private function earn(string $kind, float $amount, string $at, array $extra = []): void
    {
        DB::table('pr_rider_earnings')->insert(['rider_id' => $this->rider->id, 'parcel_id' => $this->parcel()->id, 'kind' => $kind,
            'amount' => $amount, 'earned_at' => $at, 'created_at' => now()] + $extra);
    }

    private function deduction(float $amount, string $at): RiderDeduction
    {
        return RiderDeduction::create(['rider_id' => $this->rider->id, 'kind' => 'damage', 'amount' => $amount, 'reason' => 'Broken mug',
            'status' => RiderDeduction::ACTIVE, 'created_by' => $this->manager->id, 'occurred_at' => $at]);
    }

    /** Juan's week of Nov 16 from the report: ₱360 earned, ₱20 short, ₱100 damage. */
    private function juansWeek(): void
    {
        foreach (range(1, 10) as $i) {
            $this->earn('pickup', 15, '2026-11-16 09:00:00');
        }
        foreach (range(1, 5) as $i) {
            $this->earn('delivery', 15, '2026-11-17 14:00:00');
        }
        foreach (range(1, 3) as $i) {
            $this->earn('delivery', 40, '2026-11-18 09:00:00');
        }
        $this->earn('return', 15, '2026-11-20 16:00:00');
        RiderDeduction::create(['rider_id' => $this->rider->id, 'kind' => 'shortage', 'amount' => 20, 'reason' => 'Cash short',
            'status' => RiderDeduction::ACTIVE, 'occurred_at' => '2026-11-19 18:00:00']);
        $this->deduction(100, '2026-11-21 12:00:00');
    }

    public function test_the_week_closes_into_one_payslip_per_rider_on_sunday_night(): void
    {
        $this->juansWeek();
        // Kept out of COD before the wallet: already paid, never on a payslip.
        $this->earn('delivery', 35, '2026-11-10 10:00:00', ['settled_at' => '2026-11-10 18:00:00']);
        // Earned after the cutoff: next week's.
        $this->earn('pickup', 15, '2026-11-23 08:00:00');

        $this->expectRefusal(fn () => $this->payroll->close(Carbon::parse('2026-11-16')), 'has not ended');
        Carbon::setTestNow('2026-11-23 00:05:00');
        $this->assertSame(1, $this->payroll->close(Carbon::parse('2026-11-18')));
        $this->assertSame(0, $this->payroll->close(Carbon::parse('2026-11-16')), 'a week closes once');

        $payslip = RiderPayslip::sole();
        $this->assertSame(['2026-11-16', '2026-11-22', 10, 8, 1, '360.00', '120.00', '240.00', RiderPayslip::CLOSED],
            [$payslip->week_start->toDateString(), $payslip->week_end->toDateString(), $payslip->pickups, $payslip->deliveries, $payslip->returns,
                $payslip->earned, $payslip->deductions, $payslip->net, $payslip->status]);
        $this->assertSame(['available' => 0.0, 'awaiting_release' => 240.0, 'pending_earned' => 15.0, 'pending_deductions' => 0.0, 'disputed' => 0.0],
            $this->payroll->wallet($this->rider));
        $this->assertDatabaseHas('pr_rider_notifications', ['rider_id' => $this->rider->id, 'type' => 'payslip']);
    }

    public function test_the_station_manager_releases_it_or_it_releases_itself_on_tuesday(): void
    {
        $this->juansWeek();
        Carbon::setTestNow('2026-11-23 00:05:00');
        $this->payroll->close(Carbon::parse('2026-11-16'));
        $payslip = RiderPayslip::sole();

        $other = $this->staff(StaffRole::HubManager, $this->station('041005000'));
        $this->expectRefusal(fn () => $this->payroll->release($payslip, $other), 'this station');
        $this->expectRefusal(fn () => $this->payroll->release($payslip, $this->staff(StaffRole::Dispatcher, $this->tanauan)), 'this station');
        $this->assertSame(0, $this->payroll->autoRelease(Carbon::parse('2026-11-23 00:10')), 'not before Tuesday');

        $this->assertSame(1, $this->payroll->autoRelease(Carbon::parse('2026-11-24 00:10')));
        $this->assertSame([RiderPayslip::RELEASED, null], [$payslip->fresh()->status, $payslip->fresh()->released_by]);
        $this->assertSame(240.0, $this->payroll->wallet($this->rider)['available']);
        $this->expectRefusal(fn () => $this->payroll->release($payslip, $this->manager), 'already released');
    }

    public function test_the_rider_withdraws_any_day_up_to_the_balance_and_a_negative_week_carries_over(): void
    {
        $this->juansWeek();
        Carbon::setTestNow('2026-11-23 08:00:00');
        $this->payroll->close(Carbon::parse('2026-11-16'));
        $this->expectRefusal(fn () => $this->payroll->withdraw($this->rider, 50), 'up to ₱0.00');
        $this->payroll->release(RiderPayslip::sole(), $this->manager);

        $first = $this->payroll->withdraw($this->rider, 100);
        $this->assertSame(['100.00', 'gcash', '•••••••6543', 'paid'], [$first->amount, $first->method, $first->account, $first->status]);
        $this->assertStringStartsWith('SIM-', $first->reference);
        $this->expectRefusal(fn () => $this->payroll->withdraw($this->rider, 150), 'up to ₱140.00');

        Carbon::setTestNow('2026-11-28 15:00:00');   // Saturday
        $this->payroll->withdraw($this->rider, 140);
        $this->assertSame(0.0, $this->payroll->wallet($this->rider)['available']);

        // Next week: ₱15 earned, ₱100 damage → −₱85, carried into the wallet.
        $this->earn('pickup', 15, '2026-11-24 09:00:00');
        $this->deduction(100, '2026-11-25 10:00:00');
        Carbon::setTestNow('2026-11-30 00:05:00');
        $this->payroll->close(Carbon::parse('2026-11-23'));
        $this->payroll->autoRelease(Carbon::parse('2026-12-01 00:10'));
        $this->assertSame(-85.0, $this->payroll->wallet($this->rider)['available']);
        $this->expectRefusal(fn () => $this->payroll->withdraw($this->rider, 1), 'up to ₱0.00');

        $this->rider->update(['payout_account' => null]);
        $this->expectRefusal(fn () => $this->payroll->withdraw($this->rider->fresh(), 1), 'no payout account');
        $this->assertSame(2, RiderWithdrawal::count());
    }

    public function test_a_disputed_deduction_waits_for_the_station_manager(): void
    {
        $this->juansWeek();
        Carbon::setTestNow('2026-11-23 00:05:00');
        $this->payroll->close(Carbon::parse('2026-11-16'));
        $payslip = RiderPayslip::sole();
        $damage = RiderDeduction::where('kind', 'damage')->sole();

        $this->payroll->dispute($damage, $this->rider, 'It was already broken when I picked it up');
        $this->assertSame(['20.00', '340.00'], [$payslip->fresh()->deductions, $payslip->fresh()->net], 'off the payslip while disputed');
        $this->assertSame(100.0, $this->payroll->wallet($this->rider)['disputed']);
        $this->expectRefusal(fn () => $this->payroll->dispute($damage, $this->rider, 'again'), 'already disputed');

        $this->payroll->release($payslip, $this->manager);
        $this->expectRefusal(fn () => $this->payroll->dispute(RiderDeduction::where('kind', 'shortage')->sole(), $this->rider, 'No'), 'already released');

        // Upheld: it lands on the next payslip.
        $this->payroll->decide($damage, $this->manager, true, 'The seller photo shows it intact');
        $this->assertSame([RiderDeduction::ACTIVE, null], [$damage->fresh()->status, $damage->fresh()->payslip_id]);
        Carbon::setTestNow('2026-11-30 00:05:00');
        $this->payroll->close(Carbon::parse('2026-11-23'));
        $next = RiderPayslip::whereDate('week_start', '2026-11-23')->sole();
        $this->assertSame(['0.00', '100.00', '-100.00'], [$next->earned, $next->deductions, $next->net]);

        // Waived: dropped.
        $other = $this->deduction(50, '2026-11-30 09:00:00');
        $this->payroll->dispute($other, $this->rider, 'Not mine');
        $this->payroll->decide($other, $this->manager, false, 'Another rider had the parcel');
        $this->assertSame(RiderDeduction::WAIVED, $other->fresh()->status);
        $this->assertSame(0.0, $this->payroll->wallet($this->rider)['pending_deductions']);
    }

    public function test_the_station_manager_deducts_damage_but_not_cash_shortages(): void
    {
        $this->expectRefusal(fn () => $this->payroll->deduct($this->rider, $this->manager, 'shortage', 20, 'Short'), 'remittance variance');
        $this->expectRefusal(fn () => $this->payroll->deduct($this->rider, $this->staff(StaffRole::Dispatcher, $this->tanauan), 'damage', 20, 'Dropped'), 'hub manager');
        $parcel = $this->parcel();
        $deduction = $this->payroll->deduct($this->rider, $this->manager, 'damage', 250, 'Dropped the box', $parcel);
        $this->assertSame(['damage', '250.00', $parcel->id, RiderDeduction::ACTIVE], [$deduction->kind, $deduction->amount, $deduction->parcel_id, $deduction->status]);
        $this->assertDatabaseHas('pr_rider_notifications', ['rider_id' => $this->rider->id, 'type' => 'deduction']);
    }

    public function test_the_hub_manager_runs_payroll_from_the_station_portal(): void
    {
        $this->juansWeek();
        Carbon::setTestNow('2026-11-23 08:00:00');
        $this->payroll->close(Carbon::parse('2026-11-16'));
        $payslip = RiderPayslip::sole();
        $as = fn (Staff $staff) => $this->actingAs($staff, 'pickrush');

        $as($this->staff(StaffRole::Dispatcher, $this->tanauan))->get($this->pickrushUrl('/station/payroll'))->assertForbidden();
        $as($this->staff(StaffRole::HubManager, $this->station('041005000')))->get($this->pickrushUrl("/station/payroll/payslips/{$payslip->id}"))->assertNotFound();

        $as($this->manager)->get($this->pickrushUrl('/station/payroll'))->assertOk()
            ->assertSee('Rider payroll')->assertSeeInOrder(['Juan Cruz', '₱360.00', '−₱120.00', '₱240.00', 'To release']);
        $as($this->manager)->get($this->pickrushUrl("/station/payroll/payslips/{$payslip->id}"))->assertOk()
            ->assertSee('10 pickups · 8 deliveries · 1 returns')->assertSee('Broken mug');

        // A rider disputes; the manager sees it, waives it, and releases the week.
        $this->payroll->dispute(RiderDeduction::where('kind', 'damage')->sole(), $this->rider, 'Already broken at pickup');
        $as($this->manager)->get($this->pickrushUrl('/station/payroll'))->assertSee('Disputed deductions')->assertSee('Already broken at pickup');
        $damage = RiderDeduction::where('kind', 'damage')->sole();
        $as($this->manager)->post($this->pickrushUrl("/station/payroll/deductions/{$damage->id}/decide"), ['decision' => 'waived', 'note' => 'Seller photo shows a crack'])
            ->assertSessionHasNoErrors();
        $as($this->manager)->post($this->pickrushUrl('/station/payroll/release-week'), ['week' => '2026-11-16'])->assertSessionHas('success', 'Released 1 payslip.');
        $this->assertSame([RiderPayslip::RELEASED, $this->manager->id, '340.00'], [$payslip->fresh()->status, $payslip->fresh()->released_by, $payslip->fresh()->net]);
        $as($this->manager)->post($this->pickrushUrl("/station/payroll/payslips/{$payslip->id}/release"))->assertSessionHasErrors('operation');

        // A damage deduction, tied to the parcel by its tracking number.
        $parcel = $this->parcel();
        $as($this->manager)->post($this->pickrushUrl('/station/payroll/deductions'), ['rider_id' => $this->rider->id, 'kind' => 'lost', 'amount' => '450',
            'reason' => 'Parcel left on the seat', 'tracking_no' => strtolower($parcel->tracking_no)])->assertSessionHasNoErrors();
        $this->assertSame([$parcel->id, '450.00'], [RiderDeduction::where('kind', 'lost')->sole()->parcel_id, RiderDeduction::where('kind', 'lost')->sole()->amount]);
        $as($this->manager)->post($this->pickrushUrl('/station/payroll/deductions'), ['rider_id' => $this->rider->id, 'kind' => 'shortage', 'amount' => '5', 'reason' => 'Short cash'])
            ->assertSessionHasErrors('kind');
    }

    public function test_the_rider_app_shows_the_wallet_payslips_and_withdraws(): void
    {
        $this->juansWeek();
        Carbon::setTestNow('2026-11-23 08:00:00');
        $this->payroll->close(Carbon::parse('2026-11-16'));
        $payslip = RiderPayslip::sole();
        $this->earn('pickup', 15, '2026-11-23 07:00:00');
        $api = $this->withHeaders(['Authorization' => 'Bearer '.\App\Pickrush\Models\RiderToken::issue($this->rider), 'Accept' => 'application/json']);

        $api->getJson('/api/rider/earnings')->assertOk()
            ->assertJsonPath('wallet.available', 0)
            ->assertJsonPath('wallet.awaiting_release', 240)
            ->assertJsonPath('wallet.pending_earned', 15)
            ->assertJsonPath('wallet.week_start', '2026-11-23')
            ->assertJsonPath('wallet.payout.account', '•••••••6543')
            ->assertJsonCount(2, 'wallet.deductions')
            ->assertJsonPath('wallet.deductions.0.can_dispute', true)
            ->assertJsonPath('cod.pay_kept', 0);
        $api->getJson('/api/rider/payslips')->assertOk()->assertJsonPath('payslips.0.net', 240)->assertJsonPath('payslips.0.status', 'closed');
        $api->getJson("/api/rider/payslips/{$payslip->id}")->assertOk()
            ->assertJsonCount(19, 'payslip.earnings')->assertJsonCount(2, 'payslip.deduction_items');

        $damage = RiderDeduction::where('kind', 'damage')->sole();
        $api->postJson("/api/rider/deductions/{$damage->id}/dispute")->assertUnprocessable()->assertJsonValidationErrors('reason');
        $api->postJson("/api/rider/deductions/{$damage->id}/dispute", ['reason' => 'It was already broken'])->assertOk()
            ->assertJsonPath('wallet.disputed', 100)->assertJsonPath('wallet.awaiting_release', 340);

        $api->postJson('/api/rider/withdrawals', ['amount' => 50])->assertUnprocessable()->assertJsonPath('message', 'You can withdraw up to ₱0.00.');
        $this->payroll->release($payslip, $this->manager);
        $api->postJson('/api/rider/withdrawals', ['amount' => 300])->assertOk()
            ->assertJsonPath('message', '₱300.00 sent to your GCash •••••••6543.')
            ->assertJsonPath('wallet.available', 40)
            ->assertJsonPath('wallet.withdrawals.0.amount', 300)
            ->assertJsonPath('wallet.deductions', fn ($list) => collect($list)->where('status', 'disputed')->count() === 1, 'still waiting for the manager');

        $other = Rider::withoutGlobalScopes()->create(['station_hub_id' => $this->tanauan->id, 'first_name' => 'Ana', 'last_name' => 'Reyes', 'mobile_no' => '09181230000',
            'email' => 'ana@rider.test', 'password' => 'Pass-word1!', 'vehicle_type' => 'motorcycle', 'status' => 'active', 'cod_limit' => 5000, 'max_parcels' => 20]);
        $this->withHeaders(['Authorization' => 'Bearer '.\App\Pickrush\Models\RiderToken::issue($other)])
            ->getJson("/api/rider/payslips/{$payslip->id}")->assertNotFound();
    }

    public function test_hq_sees_the_payroll_and_picksell_takes_pickup_pay_off_the_company_share(): void
    {
        $this->juansWeek();
        Carbon::setTestNow('2026-11-23 08:00:00');
        $this->payroll->close(Carbon::parse('2026-11-16'));
        $finance = $this->staff(StaffRole::Finance);
        // Closed, not released: waiting, whatever the period (the week started before today).
        $this->actingAs($finance, 'pickrush')->get($this->pickrushUrl('/hq/finance?period=today'))->assertOk()
            ->assertSeeInOrder(['Payroll payable (released, not withdrawn)', '₱0.00', 'Waiting for release (closed, not released)', '₱240.00']);
        $this->payroll->release(RiderPayslip::sole(), $this->manager);
        $this->payroll->withdraw($this->rider, 100);

        $this->actingAs($finance, 'pickrush')->get($this->pickrushUrl('/hq/finance'))->assertOk()
            ->assertSee('Rider payroll')
            ->assertSeeInOrder(['Pickups', '10', '₱150.00', 'Deliveries', '8', '₱195.00', 'Returns to sellers', '1', '₱15.00', '₱360.00'])
            ->assertSeeInOrder(['Payroll payable (released, not withdrawn)', '₱140.00', 'Waiting for release (closed, not released)', '₱0.00', 'Withdrawn in this period', '₱100.00'])
            ->assertSeeInOrder([$this->tanauan->name, '1', '₱360.00', '−₱120.00', '₱240.00']);

        // The ten pickups are PickSell parcels: their pay comes off the company's share; the ₱100 damage is recovered from Juan.
        DB::table('pr_parcels')->whereIn('id', DB::table('pr_rider_earnings')->where('kind', 'pickup')->select('parcel_id'))->update(['source' => 'picksell']);
        $row = app(\App\Services\Finance\LogisticsSettlement::class)->byCompany()->firstWhere('company.id', $this->rider->company_id);
        $this->assertSame([150.0, 100.0, -50.0], [$row['pickup_pay'], $row['rider_recovered'], $row['net_logistics']]);
    }

    private function expectRefusal(callable $action, string $message): void
    {
        try {
            $action();
            $this->fail("Expected a refusal containing \"{$message}\".");
        } catch (OperationRefused $e) {
            $this->assertStringContainsString($message, $e->getMessage());
        }
    }
}
