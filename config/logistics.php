<?php

return [
    // The single logistics portal, e.g. logistics.pick-sell.shop. Company, province hub and station hub
    // accounts all sign in here; each account is routed to its own portal pages after login.
    'superadmin_domain' => env('LOGISTICS_SUPERADMIN_DOMAIN', 'logistics.pick-sell.shop'),
];
