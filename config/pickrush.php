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

    // Where the landing page's "Get the rider app" button points. Unset hides the button.
    'rider_app_url' => env('PICKRUSH_RIDER_APP_URL'),

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

        // Parcels on a hub floor longer than this are aging; with no scan for this long, stuck.
        'aging_hours' => 24,
        'stuck_after_hours' => 48,

        // Hub ranking target: delivery success rate (%) a hub should reach.
        'sla_success_rate' => 95,

        // How often stations remit cash to the company: daily, every_two_days or weekly.
        'remittance_cycle' => 'daily',

        // Seller payout releases this many days after delivery (the dispute window).
        'payout_release_days' => 7,
    ],

    /*
    | Shipping rates. A parcel's fee is the base for its service type plus the per-kg charge
    | above the included weight, plus the COD fee (% of the COD amount). HQ edits these.
    */
    'rates' => [
        'same_city' => ['base' => 60, 'included_kg' => 1, 'per_kg' => 15],
        'same_province' => ['base' => 85, 'included_kg' => 1, 'per_kg' => 20],
        'inter_province' => ['base' => 120, 'included_kg' => 1, 'per_kg' => 30],
        'cod_fee_percent' => 1.5,
    ],

    // What a rider earns per finished task, shown in the rider app (today's earnings, a batch's
    // estimate). PLACEHOLDER amounts: replace with the real rider rates.
    'rider_pay' => [
        'pickup_per_parcel' => 15,
        'delivery_per_parcel' => 35,
    ],

    // "Arrived" near a pinned seller or station (metres). The rider's distance is always recorded;
    // only when enforced is a rider farther than this refused (off for now: set
    // PICKRUSH_ENFORCE_ARRIVAL_RADIUS=true to lock the slider again).
    'arrival_radius_m' => 150,
    'enforce_arrival_radius' => (bool) env('PICKRUSH_ENFORCE_ARRIVAL_RADIUS', false),

    // Why a rider could not collect a parcel at the seller (it goes back to the pickup queue).
    'pickup_failure_reasons' => [
        'not_ready' => 'Parcel not ready yet',
        'seller_unavailable' => 'Seller not around / shop closed',
        'no_waybill' => 'No waybill on the parcel',
        'damaged' => 'Parcel damaged or not sealed',
        'other' => 'Other (note required)',
    ],

    // "Report a problem" in the rider app; the station sees it on its dashboard.
    'rider_problems' => [
        'vehicle' => 'Vehicle problem',
        'accident' => 'Accident',
        'cannot_find' => "Can't find the location",
        'too_many_parcels' => "Parcels don't fit my vehicle",
        'other' => 'Other',
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

    // What a rider uploads in the rider app: the license (their government ID) and the OR/CR.
    'rider_documents' => [
        'drivers_license' => "Driver's license",
        'or_cr' => 'OR/CR (vehicle registration)',
    ],

];
