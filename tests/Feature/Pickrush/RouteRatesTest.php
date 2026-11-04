<?php

namespace Tests\Feature\Pickrush;

use App\Models\User;
use App\Pickrush\Enums\StaffRole;
use App\Pickrush\Models\AuditLog;
use App\Pickrush\Models\ShippingOverweightRate;
use App\Pickrush\Models\ShippingRate;
use App\Pickrush\Services\OperationRefused;
use App\Pickrush\Services\Shipping\RouteRateGenerator;
use App\Pickrush\Services\Shipping\ShippingCalculator;
use App\Pickrush\Services\Shipping\ShippingQuote;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** Delivery fees by (seller region → buyer region), generated from price levels and the route grid. */
class RouteRatesTest extends TestCase
{
    use BuildsNetwork, RefreshDatabase;

    private const AS_OF = '2026-11-10';

    private function quote(string $kg, string $buyerProvince, ?string $sellerProvince, string $asOf = self::AS_OF): ShippingQuote
    {
        return app(ShippingCalculator::class)->quote([['weight_kg' => $kg, 'quantity' => 1]], $buyerProvince, $sellerProvince, $asOf);
    }

    /** @return array<string, string> "ORIGIN|DEST|kg" => fee for rows starting on the first route date */
    private function routeRows(): array
    {
        return DB::table('pr_shipping_rates')->whereNotNull('origin_region')->get()
            ->mapWithKeys(fn ($r) => ["{$r->origin_region}|{$r->destination_region}|".bcadd((string) $r->max_weight_kg, '0', 2) => bcadd((string) $r->fee, '0', 2)])->all();
    }

    private static function csv(string $name): array
    {
        $lines = array_filter(explode("\n", trim(file_get_contents(base_path("tests/Fixtures/shipping/{$name}")))));
        $header = str_getcsv(array_shift($lines), escape: '');

        return array_map(fn ($line) => array_combine($header, str_getcsv($line, escape: '')), $lines);
    }

    // The matrix -------------------------------------------------------------------------------

    public function test_the_generated_rows_match_the_reference_csvs(): void
    {
        $expected = collect(self::csv('pickrush_origin_destination_rates.csv'))->mapWithKeys(fn ($r) => [
            "{$r['origin_region']}|{$r['destination_region']}|".bcadd($r['max_weight_kg'], '0', 2) => bcadd($r['fee'], '0', 2),
        ])->all();
        ksort($expected);
        $actual = $this->routeRows();
        ksort($actual);
        $this->assertCount(150, $actual);
        $this->assertSame($expected, $actual);
        $this->assertSame([RouteRateGenerator::FIRST_ROUTE_DATE], DB::table('pr_shipping_rates')->whereNotNull('origin_region')
            ->pluck('effective_from')->map(fn ($d) => substr((string) $d, 0, 10))->unique()->values()->all());

        $perKg = DB::table('pr_shipping_overweight_rates')->whereNotNull('origin_region')->get()
            ->mapWithKeys(fn ($r) => ["{$r->origin_region}|{$r->destination_region}" => [bcadd((string) $r->over_kg, '0', 2), bcadd((string) $r->per_kg_fee, '0', 2)]])->sortKeys()->all();
        $expectedPerKg = collect(self::csv('pickrush_origin_destination_perkg.csv'))->mapWithKeys(fn ($r) => [
            "{$r['origin_region']}|{$r['destination_region']}" => [bcadd($r['over_kg'], '0', 2), bcadd($r['per_kg_fee'], '0', 2)],
        ])->sortKeys()->all();
        $this->assertCount(25, $perKg);
        $this->assertSame($expectedPerKg, $perKg);
    }

    public function test_ncr_origin_rows_equal_the_generic_rows_exactly(): void
    {
        $generic = DB::table('pr_shipping_rates')->whereNull('origin_region')->get()
            ->mapWithKeys(fn ($r) => ["{$r->destination_region}|".bcadd((string) $r->max_weight_kg, '0', 2) => bcadd((string) $r->fee, '0', 2)])->sortKeys()->all();
        $ncr = DB::table('pr_shipping_rates')->where('origin_region', 'NCR')->get()
            ->mapWithKeys(fn ($r) => ["{$r->destination_region}|".bcadd((string) $r->max_weight_kg, '0', 2) => bcadd((string) $r->fee, '0', 2)])->sortKeys()->all();

        $this->assertCount(30, $generic);
        $this->assertSame($generic, $ncr);
    }

    public function test_prices_rise_with_weight_on_every_route_and_stay_above_the_minimum_rider_pay(): void
    {
        $byRoute = collect($this->routeRows())->groupBy(fn ($fee, $key) => implode('|', array_slice(explode('|', $key), 0, 2)), preserveKeys: true);
        $this->assertCount(25, $byRoute);
        foreach ($byRoute as $route => $fees) {
            $fees = $fees->sortKeys(SORT_NATURAL)->values();
            foreach ($fees as $i => $fee) {
                $this->assertGreaterThan(15, (float) $fee, $route);
                if ($i > 0) {
                    $this->assertGreaterThan((float) $fees[$i - 1], (float) $fee, $route);
                }
            }
        }
    }

    public function test_the_route_grid_is_symmetric_and_an_asymmetric_one_is_refused(): void
    {
        $grid = app(RouteRateGenerator::class)->grid();
        foreach ($grid as $origin => $row) {
            foreach ($row as $destination => $level) {
                $this->assertSame($level, $grid[$destination][$origin], "{$origin} ↔ {$destination}");
            }
        }

        $grid['LUZON']['NCR'] = 0;     // NCR → LUZON stays 1
        $this->expectException(OperationRefused::class);
        $this->expectExceptionMessage('A route costs the same both ways');
        app(RouteRateGenerator::class)->updateGrid($grid, '2026-11-20');
    }

    public function test_generating_twice_changes_nothing(): void
    {
        $before = DB::table('pr_shipping_rates')->count() + DB::table('pr_shipping_overweight_rates')->count();

        $this->artisan('pickrush:generate-route-rates')->expectsOutputToContain('0 created, 0 ended, 0 corrected, 175 unchanged')->assertSuccessful();
        $this->assertSame($before, DB::table('pr_shipping_rates')->count() + DB::table('pr_shipping_overweight_rates')->count());
        $this->assertSame(180, DB::table('pr_shipping_rates')->count());             // 30 generic + 150 routes
        $this->assertSame(30, DB::table('pr_shipping_overweight_rates')->count());   // 5 generic + 25 routes
    }

    public function test_generation_refuses_without_the_generic_base_rows(): void
    {
        DB::table('pr_shipping_rates')->whereNull('origin_region')->where('destination_region', 'VISAYAS')->delete();
        $before = DB::table('pr_shipping_rates')->count();

        $this->artisan('pickrush:generate-route-rates')->expectsOutputToContain('generic VISAYAS rates')->assertFailed();
        $this->assertSame($before, DB::table('pr_shipping_rates')->count());
    }

    public function test_generation_refuses_when_the_generic_rows_differ_from_the_ncr_route(): void
    {
        DB::table('pr_shipping_rates')->whereNull('origin_region')->where('destination_region', 'NCR')->where('max_weight_kg', 1)->update(['fee' => 76]);

        $this->artisan('pickrush:generate-route-rates')->expectsOutputToContain('must be the same price list')->assertFailed();
    }

    // The calculator ---------------------------------------------------------------------------

    public static function routes(): array
    {
        return [
            'Laguna → Quezon City 1 kg' => ['1', 'Laguna', 'Quezon City', '85.00', 'STANDARD'],
            'Davao → Quezon City 1 kg' => ['1', 'Davao del Sur', 'Quezon City', '100.00', 'STANDARD'],
            'NCR → NCR 0.5 kg' => ['0.5', 'Metro Manila', 'Quezon City', '60.00', 'STANDARD'],
            'NCR → NCR 1 kg' => ['1', 'Metro Manila', 'Makati', '75.00', 'STANDARD'],
            'NCR → Visayas 2 kg' => ['2', 'Quezon City', 'Cebu', '115.00', 'STANDARD'],
            // Within one region is local, priced like NCR → NCR.
            'Luzon → Luzon 2 kg' => ['2', 'Laguna', 'Batangas', '95.00', 'STANDARD'],
            'Visayas → Visayas 2 kg' => ['2', 'Cebu', 'Bohol', '95.00', 'STANDARD'],
            'Mindanao → Mindanao 2 kg' => ['2', 'Davao del Sur', 'Bukidnon', '95.00', 'STANDARD'],
            'Mindanao → Luzon 5 kg' => ['5', 'Davao del Sur', 'Laguna', '190.00', 'STANDARD'],
            'Island → NCR 10 kg' => ['10', 'Palawan', 'Metro Manila', '370.00', 'STANDARD'],
            'Island → Island 1 kg' => ['1', 'Palawan', 'Siquijor', '100.00', 'STANDARD'],          // islands are not connected: level 3
            'Island → Island 15 kg' => ['15', 'Palawan', 'Siquijor', '500.00', 'HEAVY'],           // 345 + 5 × 31
            'Mindanao → NCR 15 kg' => ['15', 'Davao del Sur', 'Quezon City', '500.00', 'HEAVY'],       // 345 + 5 × 31
            'Mindanao → Mindanao 20 kg' => ['20', 'Davao del Sur', 'Davao de Oro', '530.00', 'HEAVY'], // 280 + 10 × 25, same as NCR → NCR
            'Mindanao → Mindanao 25 kg' => ['25', 'Davao del Sur', 'Davao de Oro', '655.00', 'BULKY'], // 280 + 15 × 25, no handling fee
        ];
    }

    #[DataProvider('routes')]
    public function test_the_fee_depends_on_the_sellers_and_the_buyers_region(string $kg, string $seller, string $buyer, string $fee, string $class): void
    {
        $quote = $this->quote($kg, $buyer, $seller);

        $this->assertSame([$fee, $class, ShippingQuote::ORIGIN_DESTINATION, true], [$quote->deliveryFee, $quote->parcelClass, $quote->priceSource, $quote->originResolved()]);
    }

    public function test_a_davao_seller_pays_more_than_a_laguna_seller_to_the_same_buyer(): void
    {
        $this->assertSame(1, bccomp($this->quote('1', 'Quezon City', 'Davao del Sur')->deliveryFee, $this->quote('1', 'Quezon City', 'Laguna')->deliveryFee, 2));
    }

    public function test_an_unknown_seller_province_uses_the_generic_price_and_says_so(): void
    {
        $quote = $this->quote('1', 'Quezon City', 'Atlantis');

        $this->assertSame(['75.00', ShippingQuote::GENERIC_FALLBACK, false, null], [$quote->deliveryFee, $quote->priceSource, $quote->originResolved(), $quote->originRegion]);
        $this->assertFalse($quote->toArray()['origin_resolved']);
        $this->assertSame(ShippingQuote::GENERIC_FALLBACK, $this->quote('1', 'Quezon City', null)->priceSource);
    }

    public function test_a_route_without_rows_or_with_expired_rows_uses_the_generic_price(): void
    {
        ShippingRate::where('origin_region', 'LUZON')->where('destination_region', 'NCR')->update(['effective_to' => '2026-11-06']);

        $this->assertSame('85.00', $this->quote('1', 'Quezon City', 'Laguna', '2026-11-06')->deliveryFee);
        $late = $this->quote('1', 'Quezon City', 'Laguna', '2026-11-07');
        $this->assertSame(['75.00', ShippingQuote::GENERIC_FALLBACK], [$late->deliveryFee, $late->priceSource]);

        ShippingRate::where('origin_region', 'LUZON')->where('destination_region', 'NCR')->delete();
        $this->assertSame('75.00', $this->quote('1', 'Quezon City', 'Laguna')->deliveryFee);
    }

    public function test_the_per_kg_rate_above_10_kg_follows_the_route_too(): void
    {
        // The route's per-kg rate (31) beats the generic NCR one (25).
        $this->assertSame('500.00', $this->quote('15', 'Quezon City', 'Davao del Sur')->deliveryFee);

        ShippingOverweightRate::where('origin_region', 'MINDANAO')->where('destination_region', 'NCR')->delete();
        $quote = $this->quote('15', 'Quezon City', 'Davao del Sur');
        $this->assertSame(['470.00', ShippingQuote::GENERIC_FALLBACK], [$quote->deliveryFee, $quote->priceSource]);   // 345 + 5 × 25
    }

    // HQ ---------------------------------------------------------------------------------------

    private function hq(): static
    {
        $this->buildNetwork();

        return $this->actingAs($this->staff(StaffRole::SuperAdmin), 'pickrush');
    }

    public function test_changing_a_route_level_adds_dated_rows_and_keeps_history(): void
    {
        $grid = app(RouteRateGenerator::class)->grid();
        $grid['LUZON']['NCR'] = $grid['NCR']['LUZON'] = 0;    // treat Luzon ↔ Metro Manila as local
        $levels = collect($grid)->map(fn ($row) => collect($row)->map(fn ($l) => (string) $l)->all())->all();

        $this->hq()->put($this->pickrushUrl('/hq/shipping/route-levels'), ['levels' => $levels, 'effective_from' => today()->addDay()->toDateString()])
            ->assertSessionHasNoErrors();

        $tomorrow = today()->addDay()->toDateString();
        $this->assertSame('85.00', $this->quote('1', 'Quezon City', 'Laguna', today()->toDateString())->deliveryFee, 'today keeps the old price');
        $this->assertSame('75.00', $this->quote('1', 'Quezon City', 'Laguna', $tomorrow)->deliveryFee);
        $this->assertSame('75.00', $this->quote('1', 'Laguna', 'Quezon City', $tomorrow)->deliveryFee, 'both ways');
        $this->assertSame('75.00', $this->quote('1', 'Laguna', null, $tomorrow)->deliveryFee, 'the generic row follows the NCR route');

        $old = ShippingRate::where('origin_region', 'LUZON')->where('destination_region', 'NCR')->where('max_weight_kg', 1)->orderBy('effective_from')->get();
        $this->assertSame([['85.00', today()->toDateString()], ['75.00', null]], $old->map(fn ($r) => [(string) $r->fee, $r->effective_to?->toDateString()])->all());
        $this->assertTrue(AuditLog::where('action', 'shipping_route_levels.updated')->exists());

        $grid['VISAYAS']['NCR'] = 4;    // NCR → VISAYAS stays 2
        $this->put($this->pickrushUrl('/hq/shipping/route-levels'), ['levels' => $grid, 'effective_from' => $tomorrow])
            ->assertSessionHasErrors('levels');
    }

    public function test_changing_level_prices_regenerates_routes_and_refuses_prices_that_do_not_rise(): void
    {
        $levels = app(RouteRateGenerator::class)->levels();
        $form = collect($levels)->map(fn ($l) => ['fees' => collect($l['fees'])->mapWithKeys(fn ($fee, $kg) => [str_replace('.', '_', $kg) => $fee])->all(), 'per_kg' => $l['per_kg']])->all();
        $form[1]['fees']['1_00'] = '88.00';
        $tomorrow = today()->addDay()->toDateString();

        $this->hq()->put($this->pickrushUrl('/hq/shipping/price-levels'), ['prices' => $form, 'effective_from' => $tomorrow])->assertSessionHasNoErrors();
        $this->assertSame('88.00', $this->quote('1', 'Quezon City', 'Laguna', $tomorrow)->deliveryFee);       // LUZON → NCR is level 1
        $this->assertSame('88.00', $this->quote('1', 'Laguna', 'Metro Manila', $tomorrow)->deliveryFee);      // so is NCR → LUZON
        $this->assertSame('95.00', $this->quote('2', 'Cebu', 'Bohol', $tomorrow)->deliveryFee);               // VISAYAS → VISAYAS is level 0
        $this->assertSame('88.00', $this->quote('1', 'Laguna', null, $tomorrow)->deliveryFee);                // generic LUZON = NCR → LUZON
        $this->assertSame('85.00', $this->quote('1', 'Quezon City', 'Laguna', today()->toDateString())->deliveryFee);

        $form[1]['fees']['2_00'] = '80.00';     // below the 1 kg price
        $this->put($this->pickrushUrl('/hq/shipping/price-levels'), ['prices' => $form, 'effective_from' => $tomorrow])
            ->assertSessionHasErrors('prices');
    }

    public function test_hq_sees_the_route_grid_prices_and_sellers_priced_generically(): void
    {
        User::create([
            'role' => 'seller', 'status' => 'approved', 'first_name' => 'No', 'last_name' => 'Province', 'sex' => 'Female',
            'email' => 'noprov@ship.test', 'password' => bcrypt('secret-password'), 'contact_no' => '09170000000',
            'birthday' => '1995-01-01', 'age' => 30, 'province' => 'Atlantis', 'municipality' => 'Nowhere', 'barangay' => 'X',
            'business_name' => 'Lost Shop',
        ]);

        $this->hq()->get($this->pickrushUrl('/hq/shipping?tier=1.00&from=MINDANAO'))->assertOk()
            ->assertSee('Route levels')->assertSee('Price levels')->assertSee('Unknown (generic)')
            ->assertSee('Lost Shop (Atlantis)')->assertSee('₱100.00');
    }
}
