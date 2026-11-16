<?php

/*
 * The Android apps visitors can download from the landing page (scan the QR or tap the button).
 * Each APK lives at storage/app/private/{file}; drop a new build there to publish it. An app whose
 * file is missing shows as "coming soon" instead of a broken link.
 */
return [
    'picksell' => [
        'name' => 'PickSell',
        'tagline' => 'Marketplace app',
        'description' => 'Shop verified sellers, track every parcel and pay cash on delivery.',
        'file' => env('PICKSELL_APK', 'apps/picksell.apk'),
        'download_as' => 'PickSell.apk',
    ],
    'pickrush' => [
        'name' => 'Pickrush',
        'tagline' => 'Logistics app for riders',
        'description' => 'Apply as a rider, take pickups and deliveries, and get paid weekly.',
        'file' => env('PICKRUSH_APK', 'apps/pickrush.apk'),
        'download_as' => 'Pickrush.apk',
    ],
];
