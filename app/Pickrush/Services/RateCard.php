<?php

namespace App\Pickrush\Services;

use App\Pickrush\Models\Hub;

/** The shipping fee for a parcel from the HQ rate table: service type by route, weight, and COD fee. */
class RateCard
{
    public static function serviceType(?Hub $origin, ?Hub $dest): string
    {
        if (! $origin || ! $dest) {
            return 'inter_province';    // unknown route: charge the widest rate until the address is fixed
        }
        if ($origin->is($dest)) {
            return 'same_city';
        }

        return $origin->parent_hub_id === $dest->parent_hub_id ? 'same_province' : 'inter_province';
    }

    public static function quote(?Hub $origin, ?Hub $dest, ?float $weightKg, float $codAmount): float
    {
        $rate = config('pickrush.rates.'.self::serviceType($origin, $dest));
        $extraKg = max(0, ceil((float) ($weightKg ?? 0) - (float) $rate['included_kg']));
        $fee = (float) $rate['base'] + $extraKg * (float) $rate['per_kg'];

        return round($fee + $codAmount * (float) config('pickrush.rates.cod_fee_percent') / 100, 2);
    }
}
