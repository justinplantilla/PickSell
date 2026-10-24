<?php

namespace App\Pickrush\Support;

/** Straight-line distances between map pins (no paid routing service involved). */
final class Geo
{
    /** Great-circle distance in metres. */
    public static function metres(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $rad = M_PI / 180;
        $a = sin(($lat2 - $lat1) * $rad / 2) ** 2
            + cos($lat1 * $rad) * cos($lat2 * $rad) * sin(($lng2 - $lng1) * $rad / 2) ** 2;

        return 6371000 * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    /** "120 m" or "1.4 km". */
    public static function human(float $metres): string
    {
        return $metres < 1000 ? round($metres).' m' : round($metres / 1000, 1).' km';
    }
}
