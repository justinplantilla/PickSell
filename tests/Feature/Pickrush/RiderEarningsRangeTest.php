<?php

namespace Tests\Feature\Pickrush;

use App\Pickrush\Enums\StaffRole;
use App\Pickrush\Models\Parcel;
use App\Pickrush\Models\Rider;
use App\Pickrush\Models\RiderToken;
use App\Pickrush\Services\ParcelRegistrar;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** The rider app's Earnings page for a date range the rider picks. */
class RiderEarningsRangeTest extends TestCase
{
    use BuildsNetwork, RefreshDatabase;

    private Rider $rider;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-18 10:00:00');
        $this->buildNetwork();
        $hub = $this->station('041029000');
        $manager = $this->staff(StaffRole::HubManager, $hub);
        $this->rider = Rider::withoutGlobalScopes()->create([
            'station_hub_id' => $hub->id, 'first_name' => 'Juan', 'last_name' => 'Cruz', 'mobile_no' => '09181234567',
            'email' => 'juan@rider.test', 'password' => 'Pass-word1!', 'vehicle_type' => 'motorcycle', 'plate_no' => 'NAB 1234',
            'status' => 'active', 'shift_status' => 'on_shift', 'last_seen_at' => now(), 'cod_limit' => 5000, 'max_parcels' => 20,
            'payout_method' => 'gcash', 'payout_account' => '09179876543', 'created_by' => $manager->id,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function api(Rider $rider): static
    {
        return $this->withHeaders(['Authorization' => 'Bearer '.RiderToken::issue($rider), 'Accept' => 'application/json']);
    }

    private function parcel(): Parcel
    {
        return app(ParcelRegistrar::class)->register([
            'shipper_name' => 'Tote House', 'pickup_address' => 'Altura Bata', 'pickup_barangay_code' => '041029001', 'pickup_municipality_code' => '041029000',
            'consignee_name' => 'Ana', 'delivery_address' => 'Altura Bata', 'delivery_barangay_code' => '041029001', 'delivery_municipality_code' => '041029000',
            'cod_amount' => 500,
        ]);
    }

    private function earn(Rider $rider, string $kind, float $amount, string $at): void
    {
        DB::table('pr_rider_earnings')->insert(['rider_id' => $rider->id, 'parcel_id' => $this->parcel()->id, 'kind' => $kind,
            'amount' => $amount, 'earned_at' => $at, 'created_at' => now()]);
    }

    public function test_totals_and_days_cover_exactly_the_picked_range(): void
    {
        $this->earn($this->rider, 'pickup', 15, '2026-11-09 23:59:00');    // the day before: left out
        $this->earn($this->rider, 'pickup', 15, '2026-11-10 00:00:00');
        $this->earn($this->rider, 'delivery', 40, '2026-11-12 14:00:00');
        $this->earn($this->rider, 'return', 15, '2026-11-15 23:59:59');
        $this->earn($this->rider, 'delivery', 60, '2026-11-16 08:00:00');  // the day after: left out

        $this->api($this->rider)->getJson('/api/rider/earnings/range?from=2026-11-10&to=2026-11-15')->assertOk()
            ->assertJsonPath('from', '2026-11-10')
            ->assertJsonPath('to', '2026-11-15')
            ->assertJsonPath('total.earnings', fn ($v) => (float) $v === 70.0)
            ->assertJsonPath('total.pickups', 1)
            ->assertJsonPath('total.deliveries', 2)
            ->assertJsonCount(6, 'days')
            ->assertJsonPath('days.0.date', '2026-11-10')
            ->assertJsonPath('days.0.pickups', 1)
            ->assertJsonPath('days.2.earnings', fn ($v) => (float) $v === 40.0)
            ->assertJsonPath('days.5.date', '2026-11-15');
    }

    public function test_one_rider_never_sees_another_riders_earnings(): void
    {
        $other = $this->rider->replicate()->fill(['email' => 'other@rider.test', 'mobile_no' => '09180000000', 'plate_no' => 'NAB 9999']);
        $other->save();
        $this->earn($other, 'delivery', 60, '2026-11-12 14:00:00');

        $this->api($this->rider)->getJson('/api/rider/earnings/range?from=2026-11-01&to=2026-11-18')->assertOk()
            ->assertJsonPath('total.earnings', fn ($v) => (float) $v === 0.0);
    }

    public function test_long_ranges_skip_the_daily_lines_and_bad_ranges_are_refused(): void
    {
        $this->earn($this->rider, 'pickup', 15, '2026-03-01 09:00:00');

        $this->api($this->rider)->getJson('/api/rider/earnings/range?from=2026-01-01&to=2026-11-18')->assertOk()
            ->assertJsonPath('total.pickups', 1)
            ->assertJsonCount(0, 'days');

        $this->api($this->rider)->getJson('/api/rider/earnings/range?from=2026-11-15&to=2026-11-10')->assertStatus(422);
        $this->api($this->rider)->getJson('/api/rider/earnings/range?from=2026-11-15&to=2026-11-19')->assertStatus(422);  // the future
        $this->api($this->rider)->getJson('/api/rider/earnings/range?from=2025-01-01&to=2026-11-18')->assertStatus(422);  // over a year
        $this->api($this->rider)->getJson('/api/rider/earnings/range')->assertStatus(422);
    }
}
