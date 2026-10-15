<?php

namespace App\Support;

/**
 * Host names of the logistics portals: the company portal plus one single-level subdomain per hub,
 * e.g. hub-cebu.pick-sell.shop (province hub) and station-mandaue.pick-sell.shop (station hub).
 */
final class LogisticsDomains
{
    /** Longest hub subdomain that still fits in one 63-character DNS label once prefixed. */
    public const MAX_SUBDOMAIN_LENGTH = 55;

    public static function isLogisticsHost(string $host): bool
    {
        return strtolower($host) === self::superadminHost() || self::parseHubHost($host) !== null;
    }

    public static function superadminHost(): string
    {
        return strtolower((string) config('logistics.superadmin_domain'));
    }

    public static function provinceHost(string $subdomain): string
    {
        return config('logistics.province_prefix').$subdomain.'.'.config('logistics.hub_domain');
    }

    public static function stationHost(string $subdomain): string
    {
        return config('logistics.station_prefix').$subdomain.'.'.config('logistics.hub_domain');
    }

    /** Route domain pattern, e.g. "hub-{province}.pick-sell.shop". */
    public static function routeDomain(string $type): string
    {
        return $type === 'province_hub'
            ? config('logistics.province_prefix').'{province}.'.config('logistics.hub_domain')
            : config('logistics.station_prefix').'{station}.'.config('logistics.hub_domain');
    }

    /**
     * @return array{0: 'province_hub'|'station_hub', 1: string}|null the hub type and its subdomain
     */
    public static function parseHubHost(string $host): ?array
    {
        $host = strtolower($host);
        $suffix = '.'.strtolower((string) config('logistics.hub_domain'));
        if (! str_ends_with($host, $suffix)) {
            return null;
        }

        $label = substr($host, 0, -strlen($suffix));
        if (str_contains($label, '.')) {
            return null;
        }

        foreach (['province_hub' => 'logistics.province_prefix', 'station_hub' => 'logistics.station_prefix'] as $type => $key) {
            $prefix = strtolower((string) config($key));
            if (str_starts_with($label, $prefix) && strlen($label) > strlen($prefix)) {
                return [$type, substr($label, strlen($prefix))];
            }
        }

        return null;
    }
}
