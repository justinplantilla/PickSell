<?php

namespace App\Pickrush\Services\Shipping;

use App\Pickrush\Models\Company;
use App\Pickrush\Models\ParcelClass;
use App\Pickrush\Models\ShippingOverweightRate;
use App\Pickrush\Models\ShippingRate;

/**
 * Prices one seller's parcel for the marketplace from a logistics company's dated rate card
 * (Pickrush's when no company is given):
 *  1. chargeable kg = the items' actual weight × quantity, rounded to 2 decimals (box size is
 *     not charged for now);
 *  2. destination region from the buyer's province, origin region from the seller's province;
 *     a row for that exact route beats the generic row (no origin) for the same tier, and the
 *     generic row is used when the route has none or the seller's region is unknown;
 *  3. parcel class by chargeable kg (heavier than shipping.max_priced_weight_kg: REQUIRES_QUOTE);
 *  4. fee = the smallest weight tier >= chargeable kg, or above the heaviest tier
 *     fee at over_kg + ceil(kg - over_kg) × per-kg rate.
 * All arithmetic is bcmath on decimal strings; nothing here is hardcoded.
 */
class ShippingCalculator
{
    private const SCALE = 6;

    public function __construct(private RegionResolver $regions) {}

    /**
     * @param  array<int, array{weight_kg: mixed, quantity: mixed}>  $items
     * @param  string|null  $asOf  Y-m-d; rates in force on this day (default today)
     * @param  int|null  $companyId  the carrier whose rates apply (default: Pickrush)
     */
    public function quote(array $items, ?string $buyerProvince, ?string $sellerProvince = null,
        ?string $asOf = null, ?string $buyerCity = null, ?int $companyId = null): ShippingQuote
    {
        $companyId ??= Company::defaultId();
        $kg = self::chargeableWeight($items);
        if ($kg === null) {
            return ShippingQuote::failed(QuoteFailure::InvalidWeight);
        }

        $destination = $this->regions->resolve($buyerProvince, $buyerCity);
        if ($destination === null) {
            return ShippingQuote::failed(QuoteFailure::UnknownProvince, $kg);
        }

        $maxPriced = self::decimal(config('pickrush.shipping.max_priced_weight_kg'));
        if ($maxPriced !== null && bccomp($kg, $maxPriced, 2) > 0) {
            return ShippingQuote::failed(QuoteFailure::RequiresQuote, $kg, $destination);
        }

        $class = ParcelClass::where('is_active', true)->get()
            ->first(fn (ParcelClass $c) => bccomp($kg, (string) $c->min_kg_exclusive, 2) > 0 && bccomp($kg, (string) $c->max_kg_inclusive, 2) <= 0);
        if (! $class) {
            return ShippingQuote::failed(QuoteFailure::RequiresQuote, $kg, $destination);
        }

        $asOf ??= now()->toDateString();
        $origin = $this->regions->resolve($sellerProvince);
        $tiers = $this->tiers($companyId, $destination, $origin, $asOf);
        if ($tiers === []) {
            return ShippingQuote::failed(QuoteFailure::NoRate, $kg, $destination);
        }

        $tier = collect($tiers)->first(fn (ShippingRate $rate) => bccomp((string) $rate->max_weight_kg, $kg, 2) >= 0);
        if ($tier) {
            $fee = (string) $tier->fee;
            $billedKg = (string) $tier->max_weight_kg;
            $version = "rate:{$tier->id}";
            $fromRoute = $tier->origin_region !== null;
        } else {
            $overweight = ShippingOverweightRate::ofCompany($companyId)->effectiveOn($asOf)->where('destination_region', $destination)
                ->where(fn ($q) => $q->whereNull('origin_region')->when($origin, fn ($q) => $q->orWhere('origin_region', $origin)))
                ->get()
                ->sortBy([
                    fn ($a, $b) => ($a->origin_region === null) <=> ($b->origin_region === null),
                    fn ($a, $b) => $b->effective_from <=> $a->effective_from,
                    fn ($a, $b) => $b->id <=> $a->id,
                ])->first();
            $base = $overweight ? collect($tiers)->first(fn (ShippingRate $rate) => bccomp((string) $rate->max_weight_kg, (string) $overweight->over_kg, 2) === 0) : null;
            if (! $base) {
                return ShippingQuote::failed(QuoteFailure::NoRate, $kg, $destination);
            }
            $extraKg = self::ceil(bcsub($kg, (string) $overweight->over_kg, 2));
            $fee = bcadd((string) $base->fee, bcmul($extraKg, (string) $overweight->per_kg_fee, 2), 2);
            $billedKg = bcadd((string) $overweight->over_kg, $extraKg, 2);
            $version = "rate:{$base->id},overweight:{$overweight->id}";
            $fromRoute = $base->origin_region !== null && $overweight->origin_region !== null;
        }

        return ShippingQuote::priced(
            originRegion: $origin,
            destinationRegion: $destination,
            priceSource: $fromRoute ? ShippingQuote::ORIGIN_DESTINATION : ShippingQuote::GENERIC_FALLBACK,
            chargeableWeightKg: $kg,
            weightTierKg: bcadd($billedKg, '0', 2),
            parcelClass: $class->code,
            deliveryFee: bcadd($fee, '0', 2),
            riderPay: bcadd((string) $class->rider_pay, '0', 2),
            ratesVersion: $version,
        );
    }

    /**
     * The kg one parcel holding all the items is charged on (weight × quantity, summed, rounded
     * to 2 decimals), or null when any weight or quantity is missing, not positive or malformed.
     */
    public static function chargeableWeight(array $items): ?string
    {
        if ($items === []) {
            return null;
        }

        $actual = '0';
        foreach ($items as $item) {
            $weight = self::decimal($item['weight_kg'] ?? null);
            $quantity = self::decimal($item['quantity'] ?? null);
            if ($weight === null || $quantity === null || bccomp($weight, '0', self::SCALE) <= 0
                || bccomp($quantity, '0', self::SCALE) <= 0 || bccomp($quantity, self::ceil($quantity), self::SCALE) !== 0) {
                return null;
            }
            $actual = bcadd($actual, bcmul($weight, $quantity, self::SCALE), self::SCALE);
        }

        $chargeable = self::round2($actual);

        return bccomp($chargeable, '0', 2) > 0 ? $chargeable : null;
    }

    /**
     * Weight tiers in force, lightest first: one row per max_weight_kg, preferring a row for the
     * seller's origin region over a generic one, then the most recently effective.
     *
     * @return array<int, ShippingRate>
     */
    private function tiers(int $companyId, string $destination, ?string $origin, string $asOf): array
    {
        return ShippingRate::ofCompany($companyId)->effectiveOn($asOf)->where('destination_region', $destination)
            ->where(fn ($q) => $q->whereNull('origin_region')->when($origin, fn ($q) => $q->orWhere('origin_region', $origin)))
            ->get()
            ->sortBy([
                fn ($a, $b) => ($a->origin_region === null) <=> ($b->origin_region === null),
                fn ($a, $b) => $b->effective_from <=> $a->effective_from,
                fn ($a, $b) => $b->id <=> $a->id,
            ])
            ->unique(fn (ShippingRate $rate) => (string) $rate->max_weight_kg)
            ->sort(fn ($a, $b) => bccomp((string) $a->max_weight_kg, (string) $b->max_weight_kg, 2))
            ->values()->all();
    }

    /** Round a non-negative decimal to 2 places, half up (bcround needs PHP 8.4; the app allows 8.3). */
    private static function round2(string $value): string
    {
        return bcadd($value, '0.005', 2);
    }

    /** The smallest whole number >= a non-negative decimal (bcceil needs PHP 8.4). */
    private static function ceil(string $value): string
    {
        $whole = bcadd($value, '0', 0);

        return bccomp($value, $whole, self::SCALE) > 0 ? bcadd($whole, '1', 0) : $whole;
    }

    /** A plain non-negative decimal string for bcmath, or null for anything else (floats are formatted first). */
    private static function decimal(mixed $value): ?string
    {
        if (is_int($value)) {
            $value = (string) $value;
        } elseif (is_float($value)) {
            $value = rtrim(rtrim(sprintf('%.6F', $value), '0'), '.');
        }

        return is_string($value) && preg_match('/^\d+(\.\d+)?$/', trim($value)) ? trim($value) : null;
    }
}
