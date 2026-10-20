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

        // Rider limits. New riders start on probation with lower caps; the hub raises them later.
        'rider_limits' => [
            'probation' => ['cod_limit' => 3000, 'max_parcels' => 20],
            'standard' => ['cod_limit' => 10000, 'max_parcels' => 60],
        ],

        // Cash counts above this need a second staff member present.
        'two_person_count_above' => 20000,
    ],

    // Closed list so failure reports are comparable across hubs.
    'failure_reasons' => [
        'buyer_unavailable' => 'Buyer unavailable (no answer, nobody home)',
        'wrong_address' => 'Address incomplete or does not exist',
        'buyer_refused' => 'Buyer refused the parcel or the COD amount',
        'cannot_locate' => 'Address exists but could not be found',
        'restricted_area' => 'Restricted area (gated, flooded, road closed)',
        'rescheduled_by_buyer' => 'Buyer asked for another day',
        'other' => 'Other (note required)',
    ],

    'rider_documents' => [
        'gov_id' => 'Government ID',
        'drivers_license' => "Driver's license",
        'or_cr' => 'OR/CR (vehicle registration)',
        'nbi_clearance' => 'NBI clearance',
        'barangay_clearance' => 'Barangay clearance',
        'selfie_with_id' => 'Selfie holding ID',
    ],

];
