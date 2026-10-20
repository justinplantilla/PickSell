<?php

namespace App\Support;

/** Philippine administrative regions a logistics provider may list as its service areas. */
final class PhilippineRegions
{
    public const LABELS = [
        'NCR' => 'National Capital Region (NCR)',
        'CAR' => 'Cordillera Administrative Region (CAR)',
        'Region I' => 'Region I (Ilocos Region)',
        'Region II' => 'Region II (Cagayan Valley)',
        'Region III' => 'Region III (Central Luzon)',
        'Region IV-A' => 'Region IV-A (CALABARZON)',
        'MIMAROPA' => 'MIMAROPA Region',
        'Region V' => 'Region V (Bicol Region)',
        'Region VI' => 'Region VI (Western Visayas)',
        'NIR' => 'Negros Island Region (NIR)',
        'Region VII' => 'Region VII (Central Visayas)',
        'Region VIII' => 'Region VIII (Eastern Visayas)',
        'Region IX' => 'Region IX (Zamboanga Peninsula)',
        'Region X' => 'Region X (Northern Mindanao)',
        'Region XI' => 'Region XI (Davao Region)',
        'Region XII' => 'Region XII (SOCCSKSARGEN)',
        'Region XIII' => 'Region XIII (Caraga)',
        'BARMM' => 'Bangsamoro Autonomous Region in Muslim Mindanao (BARMM)',
    ];

    /** @return list<string> */
    public static function keys(): array
    {
        return array_keys(self::LABELS);
    }

    /** @param  list<string>|null  $keys */
    public static function labels(?array $keys): string
    {
        return collect($keys ?? [])->map(fn (string $key) => self::LABELS[$key] ?? $key)->join('; ');
    }
}
