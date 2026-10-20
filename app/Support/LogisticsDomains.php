<?php

namespace App\Support;

/**
 * Host name of the logistics portal (e.g. logistics.pick-sell.shop). Company, province hub and station hub
 * accounts all use this one host; the signed-in account decides which portal pages they see.
 */
final class LogisticsDomains
{
    public static function isLogisticsHost(string $host): bool
    {
        return strtolower($host) === self::superadminHost();
    }

    public static function superadminHost(): string
    {
        return strtolower((string) config('logistics.superadmin_domain'));
    }

    /** Absolute URL of a logistics portal path, on the logistics host unless already serving from it (or locally). */
    public static function url(string $path): string
    {
        $request = request();
        if (self::isLogisticsHost($request->getHost()) || app()->environment(['local', 'testing'])) {
            return url($path);
        }

        return 'https://'.self::superadminHost().'/'.ltrim($path, '/');
    }
}
