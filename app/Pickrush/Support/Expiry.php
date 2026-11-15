<?php

namespace App\Pickrush\Support;

use Carbon\CarbonInterface;

/** Where a fleet paper stands: a driver's license or a truck's OR, by its expiry date. */
final class Expiry
{
    public static function warningDays(): int
    {
        return (int) config('pickrush.fleet.expiry_warning_days', 30);
    }

    /** missing | expired | expiring (within the warning days) | valid */
    public static function state(?CarbonInterface $expiry): string
    {
        return match (true) {
            $expiry === null => 'missing',
            $expiry->lt(today()) => 'expired',
            $expiry->lte(today()->addDays(self::warningDays())) => 'expiring',
            default => 'valid',
        };
    }

    /** @return array{0: string, 1: string} badge class and text */
    public static function badge(?CarbonInterface $expiry): array
    {
        return match (self::state($expiry)) {
            'missing' => ['pr-badge-bad', 'No papers'],
            'expired' => ['pr-badge-bad', 'Expired'],
            'expiring' => ['pr-badge-warn', ($days = (int) today()->diffInDays($expiry)) === 0 ? 'Expires today' : "Expires in {$days} ".($days === 1 ? 'day' : 'days')],
            default => ['pr-badge-ok', 'Valid'],
        };
    }
}
