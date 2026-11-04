<?php

namespace Tests\Feature\Pickrush;

use App\Pickrush\Models\ParcelClass;
use App\Pickrush\Models\ShippingRate;
use App\Pickrush\Services\OperationRefused;
use App\Pickrush\Services\Shipping\QuoteFailure;
use App\Pickrush\Services\Shipping\RegionResolver;
use App\Pickrush\Services\Shipping\ShippingCalculator;
use App\Pickrush\Services\Shipping\ShippingQuote;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ShippingCalculatorTest extends TestCase
{
    use RefreshDatabase;

    /** A province in each region. */
    private const PROVINCE = ['NCR' => 'Metro Manila', 'LUZON' => 'Batangas', 'VISAYAS' => 'Cebu', 'MINDANAO' => 'Davao del Sur', 'ISLAND' => 'Palawan'];

    /** Without a seller province these are the generic (Metro Manila-origin) prices; routes are covered in RouteRatesTest. */
    private function quote(string|float $kg, string $province, array $item = [], ?string $asOf = null, ?string $sellerProvince = null): ShippingQuote
    {
        return app(ShippingCalculator::class)->quote([array_merge(['weight_kg' => $kg, 'quantity' => 1], $item)], $province, $sellerProvince, $asOf);
    }

    public static function publishedFees(): array
    {
        $table = [
            'NCR' => [60, 75, 95, 115, 155, 280],
            'LUZON' => [75, 85, 105, 125, 170, 310],
            'VISAYAS' => [85, 95, 115, 135, 180, 330],
            'MINDANAO' => [90, 100, 120, 140, 190, 345],
            'ISLAND' => [98, 110, 130, 152, 205, 370],
        ];
        $cases = [];
        foreach ($table as $region => $fees) {
            foreach (['0.5', '1', '2', '3', '5', '10'] as $i => $kg) {
                $cases["{$region} {$kg} kg"] = [$region, $kg, $fees[$i]];
            }
        }

        return $cases;
    }

    #[DataProvider('publishedFees')]
    public function test_every_region_and_tier_returns_the_published_fee(string $region, string $kg, int $fee): void
    {
        $quote = $this->quote($kg, self::PROVINCE[$region]);

        $this->assertTrue($quote->ok());
        $this->assertSame($region, $quote->destinationRegion);
        $this->assertSame(number_format($fee, 2, '.', ''), $quote->deliveryFee);
        $this->assertSame('STANDARD', $quote->parcelClass);
        $this->assertSame('15.00', $quote->riderPay);
    }

    public function test_a_weight_between_tiers_uses_the_next_tier_up(): void
    {
        $this->assertSame('2.00', $this->quote('1.2', 'Metro Manila')->weightTierKg);
        $this->assertSame('95.00', $this->quote('1.2', 'Metro Manila')->deliveryFee);
        $this->assertSame('1.00', $this->quote('0.51', 'Metro Manila')->weightTierKg);
        $this->assertSame('75.00', $this->quote('0.51', 'Metro Manila')->deliveryFee);
        $this->assertSame('0.51', $this->quote('0.505', 'Metro Manila')->chargeableWeightKg);   // half up
        $this->assertSame('0.50', $this->quote('0.504', 'Metro Manila')->chargeableWeightKg);
        $this->assertSame('11.00', $this->quote('10.01', 'Metro Manila')->weightTierKg);         // any part kg counts
        $this->assertSame('305.00', $this->quote('10.01', 'Metro Manila')->deliveryFee);        // 280 + 1 × 25
    }

    public function test_weights_add_up_across_items_and_quantities(): void
    {
        $this->assertSame('3.70', ShippingCalculator::chargeableWeight([
            ['weight_kg' => '0.4', 'quantity' => 3],
            ['weight_kg' => '1.25', 'quantity' => 2],
        ]));   // 1.2 + 2.5

        $quote = app(ShippingCalculator::class)->quote([['weight_kg' => '0.4', 'quantity' => 3], ['weight_kg' => '1.25', 'quantity' => 2]], 'Metro Manila');
        $this->assertSame(['3.70', '5.00', '155.00'], [$quote->chargeableWeightKg, $quote->weightTierKg, $quote->deliveryFee]);
    }

    public function test_box_size_is_not_charged(): void
    {
        $quote = $this->quote('2', 'Metro Manila', ['length_cm' => 40, 'width_cm' => 30, 'height_cm' => 20]);

        $this->assertSame(['2.00', '95.00'], [$quote->chargeableWeightKg, $quote->deliveryFee]);
    }

    public function test_heavy_parcels_add_the_per_kg_rate_above_10_kg(): void
    {
        $visayas = $this->quote('12.3', 'Cebu');
        $this->assertSame('420.00', $visayas->deliveryFee);    // 330 + 3 × 30
        $this->assertSame('HEAVY', $visayas->parcelClass);
        $this->assertSame('25.00', $visayas->riderPay);
        $this->assertSame('13.00', $visayas->weightTierKg);

        $this->assertSame('405.00', $this->quote('15', 'Metro Manila')->deliveryFee);   // 280 + 5 × 25
    }

    public function test_bulky_parcels_use_the_same_per_kg_formula_with_bulky_rider_pay(): void
    {
        $quote = $this->quote('25', 'Davao del Sur');

        $this->assertSame('810.00', $quote->deliveryFee);      // 345 + 15 × 31, no handling fee
        $this->assertSame('BULKY', $quote->parcelClass);
        $this->assertSame('50.00', $quote->riderPay);
        $this->assertSame('1690.00', $this->quote('50', 'Palawan')->deliveryFee);   // ISLAND: 370 + 40 × 33
    }

    public function test_class_boundaries_are_inclusive_at_the_top(): void
    {
        $this->assertSame('STANDARD', $this->quote('10', 'Metro Manila')->parcelClass);
        $this->assertSame('HEAVY', $this->quote('10.01', 'Metro Manila')->parcelClass);
        $this->assertSame('HEAVY', $this->quote('20', 'Metro Manila')->parcelClass);
        $this->assertSame('BULKY', $this->quote('20.01', 'Metro Manila')->parcelClass);
        $this->assertSame('BULKY', $this->quote('50', 'Metro Manila')->parcelClass);
    }

    public function test_parcels_over_the_priced_limit_require_a_manual_quote(): void
    {
        $quote = $this->quote('50.01', 'Metro Manila');

        $this->assertFalse($quote->ok());
        $this->assertSame(QuoteFailure::RequiresQuote, $quote->failure);
        $this->assertNull($quote->deliveryFee);
        $this->assertSame('Heavy parcel: shipping will be quoted by PickSell.', $quote->failure->message());
    }

    public function test_an_unknown_province_is_refused_never_priced(): void
    {
        $this->assertSame(QuoteFailure::UnknownProvince, $this->quote('1', 'Atlantis')->failure);
        $this->assertSame(QuoteFailure::UnknownProvince, $this->quote('1', '')->failure);
    }

    public function test_a_region_without_rates_has_no_rate(): void
    {
        ShippingRate::where('destination_region', 'VISAYAS')->delete();

        $this->assertSame(QuoteFailure::NoRate, $this->quote('1', 'Cebu')->failure);
        $this->assertSame(QuoteFailure::NoRate, $this->quote('1', 'Cebu', sellerProvince: 'Bohol')->failure);
    }

    public function test_expired_rates_are_not_used(): void
    {
        ShippingRate::where('destination_region', 'VISAYAS')->update(['effective_to' => '2026-06-30']);

        $this->assertSame('95.00', $this->quote('1', 'Cebu', asOf: '2026-06-30')->deliveryFee);
        $this->assertSame(QuoteFailure::NoRate, $this->quote('1', 'Cebu', asOf: '2026-07-01')->failure);
        $this->assertSame(QuoteFailure::NoRate, $this->quote('1', 'Cebu', asOf: '2026-03-31')->failure);
    }

    public function test_a_new_rate_applies_from_its_start_date_only(): void
    {
        ShippingRate::where('destination_region', 'NCR')->where('max_weight_kg', 1)->update(['effective_to' => '2026-12-31']);
        ShippingRate::create(['destination_region' => 'NCR', 'max_weight_kg' => 1, 'fee' => 80,
            'effective_from' => '2027-01-01']);

        $this->assertSame('75.00', $this->quote('1', 'Metro Manila', asOf: '2026-12-31')->deliveryFee);
        $this->assertSame('80.00', $this->quote('1', 'Metro Manila', asOf: '2027-01-01')->deliveryFee);
    }

    public function test_an_origin_specific_rate_beats_the_generic_one(): void
    {
        // Before the generated route prices start: only this exception and the generic rows exist.
        ShippingRate::create(['origin_region' => 'VISAYAS', 'destination_region' => 'VISAYAS',
            'max_weight_kg' => 1, 'fee' => 70, 'effective_from' => '2026-04-01']);

        $this->assertSame('70.00', $this->quote('1', 'Cebu', asOf: '2026-06-01', sellerProvince: 'Bohol')->deliveryFee);
        $this->assertSame('95.00', $this->quote('1', 'Cebu', asOf: '2026-06-01', sellerProvince: 'Batangas')->deliveryFee);
        $this->assertSame('95.00', $this->quote('1', 'Cebu', asOf: '2026-06-01')->deliveryFee);
    }

    public function test_province_names_match_loosely_and_metro_manila_cities_resolve_to_ncr(): void
    {
        $regions = app(RegionResolver::class);

        $this->assertSame('VISAYAS', $regions->resolve('  negros   OCCIDENTAL '));
        $this->assertSame('MINDANAO', $regions->resolve('Davao De Oro'));          // PSGC capitalisation
        $this->assertSame('MINDANAO', $regions->resolve('Maguindanao'));           // PSGC's undivided province
        $this->assertSame('NCR', $regions->resolve('Quezon City'));
        $this->assertSame('LUZON', $regions->resolve('Quezon'));
        $this->assertSame('NCR', $regions->resolve('Second District'));            // PSGC district as province
        $this->assertSame('NCR', $regions->resolve('City of Las Piñas'));
        $this->assertSame('NCR', $regions->resolve(null, 'Makati'));               // no province: use the city
        $this->assertNull($regions->resolve('Atlantis', 'San Juan'));              // a bad province is not guessed from the city
        $this->assertSame('ISLAND', $regions->resolve('tawi-tawi'));
    }

    public function test_missing_zero_or_malformed_weights_are_invalid(): void
    {
        $calculator = app(ShippingCalculator::class);
        foreach ([
            [['quantity' => 1]],
            [['weight_kg' => 0, 'quantity' => 1]],
            [['weight_kg' => '-1', 'quantity' => 1]],
            [['weight_kg' => 'abc', 'quantity' => 1]],
            [['weight_kg' => '0.001', 'quantity' => 1]],   // rounds to 0.00 kg
            [['weight_kg' => 1, 'quantity' => 0]],
            [['weight_kg' => 1, 'quantity' => 1.5]],
            [],
        ] as $items) {
            $this->assertSame(QuoteFailure::InvalidWeight, $calculator->quote($items, 'Cebu')->failure, json_encode($items));
        }
    }

    public function test_the_quote_serialises_money_as_two_decimal_strings(): void
    {
        $this->assertSame([
            'ok' => true, 'origin_region' => null, 'origin_resolved' => false, 'destination_region' => 'MINDANAO',
            'price_source' => 'GENERIC_FALLBACK', 'chargeable_weight_kg' => '25.00',
            'weight_tier_kg' => '25.00', 'parcel_class' => 'BULKY', 'delivery_fee' => '810.00',
            'rider_pay' => '50.00', 'rates_version' => $this->quote('25', 'Davao del Sur')->ratesVersion,
        ], $this->quote('25', 'Davao del Sur')->toArray());

        $this->assertSame(['ok' => false, 'reason' => 'UNKNOWN_PROVINCE', 'message' => QuoteFailure::UnknownProvince->message()],
            $this->quote('1', 'Atlantis')->toArray());
    }

    public function test_a_parcel_class_cannot_pay_a_rider_below_the_minimum(): void
    {
        $class = ParcelClass::find('HEAVY');
        $class->rider_pay = '15.00';
        $class->save();                                             // exactly the minimum is allowed

        $this->expectException(OperationRefused::class);
        $class->rider_pay = '14.99';
        $class->save();
    }

    public function test_a_new_class_below_the_minimum_is_refused(): void
    {
        $this->expectException(OperationRefused::class);

        ParcelClass::create(['code' => 'TINY', 'min_kg_exclusive' => 0, 'max_kg_inclusive' => 0.5, 'rider_pay' => 10]);
    }
}
