<?php

namespace App\Pickrush\Services;

use App\Pickrush\Models\Parcel;

/**
 * Tracking numbers: the carrier's two-letter prefix + yymmdd + 7 random digits + a check letter,
 * e.g. PR2610200483921K for Pickrush.
 */
class TrackingNumber
{
    private const LETTERS = 'ABCDEFGHJKLMNPQRSTUVWXYZ';

    public function generate(?string $prefix = null): string
    {
        $prefix = strtoupper($prefix ?: config('pickrush.tracking_prefix'));
        do {
            $body = $prefix.now()->format('ymd').str_pad((string) random_int(0, 9_999_999), 7, '0', STR_PAD_LEFT);
            $number = $body.self::checkLetter($body);
        } while (Parcel::withoutGlobalScopes()->where('tracking_no', $number)->exists());

        return $number;
    }

    /** Catches mistyped numbers before a lookup. */
    public static function isValid(string $number): bool
    {
        return (bool) preg_match('/^[A-Z]{2}\d{13}[A-Z]$/', $number)
            && self::checkLetter(substr($number, 0, -1)) === substr($number, -1);
    }

    private static function checkLetter(string $body): string
    {
        $sum = 0;
        foreach (str_split(preg_replace('/\D/', '', $body)) as $i => $digit) {
            $sum += (int) $digit * ($i % 2 ? 3 : 1);
        }

        return self::LETTERS[$sum % strlen(self::LETTERS)];
    }
}
