<?php

namespace App\Pickrush\Services\Shipping;

use Illuminate\Support\Facades\DB;

/**
 * Which shipping region (NCR, LUZON, VISAYAS, MINDANAO, ISLAND) an address is in, from
 * pr_shipping_region_provinces. Names match ignoring case, extra spaces and ñ/n. Without a
 * province the city is used, so Metro Manila addresses saved by city still resolve.
 */
class RegionResolver
{
    /** @var array<string, string>|null  normalised name => region code */
    private ?array $map = null;

    public function resolve(?string $province, ?string $city = null): ?string
    {
        $map = $this->map ??= DB::table('pr_shipping_region_provinces')->pluck('region_code', 'name')
            ->mapWithKeys(fn ($region, $name) => [self::key($name) => $region])->all();

        // Only a blank province falls back to the city: "San Juan" is also in Batangas and La Union,
        // so a province we do not recognise is an address to fix, not a guess.
        $key = self::key($province) !== '' ? self::key($province) : self::key($city);

        return $map[$key] ?? null;
    }

    public static function key(?string $name): string
    {
        $name = mb_strtolower(trim((string) $name));

        return (string) preg_replace('/\s+/', ' ', str_replace('ñ', 'n', $name));
    }
}
