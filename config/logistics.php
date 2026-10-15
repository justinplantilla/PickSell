<?php

return [
    // Company (superadmin) logistics portal, e.g. logistics.pick-sell.shop
    'superadmin_domain' => env('LOGISTICS_SUPERADMIN_DOMAIN', 'logistics.pick-sell.shop'),

    // Hub portals are single-level subdomains of this domain so one wildcard certificate (*.pick-sell.shop)
    // covers them all: <province_prefix><subdomain>.<hub_domain> and <station_prefix><subdomain>.<hub_domain>,
    // e.g. hub-cebu.pick-sell.shop and station-mandaue.pick-sell.shop
    'hub_domain' => env('LOGISTICS_HUB_DOMAIN', 'pick-sell.shop'),
    'province_prefix' => env('LOGISTICS_PROVINCE_PREFIX', 'hub-'),
    'station_prefix' => env('LOGISTICS_STATION_PREFIX', 'station-'),
];
