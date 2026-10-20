<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Pickrush Logistics
    |--------------------------------------------------------------------------
    |
    | Pickrush is the logistics business. It lives in this codebase as an isolated
    | module (app/Pickrush, pr_* tables, its own staff accounts) and is served only
    | on its own host. Locally with Laragon, point e.g. logistics.picksell.test at
    | this project and set PICKRUSH_DOMAIN to match.
    |
    */

    'domain' => env('PICKRUSH_DOMAIN', 'logistics.pick-sell.shop'),

    // Pickrush pages get their own session cookie so a marketplace logout never
    // signs staff out of the logistics portal, and the other way round.
    'session_cookie' => env('PICKRUSH_SESSION_COOKIE', 'pickrush_session'),

    // Tracking numbers look like PR2610200000123X (prefix, yymmdd, sequence, check letter).
    'tracking_prefix' => 'PR',

    'policies' => [
        'max_delivery_attempts' => 3,
    ],

];
