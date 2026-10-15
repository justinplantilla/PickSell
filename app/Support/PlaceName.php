<?php

namespace App\Support;

/**
 * Compares Philippine place names as people type them: "Sta. Rosa", "City of Santa Rosa"
 * and "Santa Rosa City" all mean the same town.
 */
final class PlaceName
{
    public static function normalize(?string $name): string
    {
        $name = mb_strtolower(trim((string) $name));
        $name = preg_replace('/\(.*?\)/u', ' ', $name);
        $name = preg_replace('/^city of\s+|\s+city$/u', '', $name);
        $name = preg_replace('/\bsta\.?\s+/u', 'santa ', $name);
        $name = preg_replace('/\bsto\.?\s+/u', 'santo ', $name);
        $name = preg_replace('/\bpob\.?\s+|\bpoblacion\b/u', 'poblacion ', $name);
        $name = str_replace(['ñ', 'Ñ'], 'n', $name);

        return trim(preg_replace('/[^a-z0-9]+/u', ' ', $name));
    }

    public static function same(?string $first, ?string $second): bool
    {
        return self::normalize($first) !== '' && self::normalize($first) === self::normalize($second);
    }
}
