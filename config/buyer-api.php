<?php

/*
| Buyer mobile app API (routes/buyer-api.php). Business rules come from the website's own code;
| these lists only shape what the app shows.
*/
return [
    // The website offers Cash on Delivery only.
    'payment_methods' => [
        ['code' => 'cod', 'name' => 'Cash on Delivery', 'description' => 'Pay in cash when you receive your order', 'available' => true],
    ],

    // Category chips on the app's shop home: this fixed list plus any category in use.
    'home_categories' => ['Beauty', 'Books', 'Clothing', 'Electronics', 'Fashion', 'Food & Grocery', 'Home & Living', 'Pet Supplies', 'Sports', 'Toys'],

    // Reasons offered when a buyer cancels; saved as the cancellation reason in the order history.
    'cancel_reasons' => [
        'Need to change delivery address', 'Need to change the variation or quantity',
        'Found a better price elsewhere', 'Ordered by mistake', 'Other',
    ],

    // "Report a problem" categories; each report becomes a complaint on the admin Complaints page.
    'report_categories' => ['Damaged Item', 'Missing Item', 'Wrong Item', 'Quality Issue', 'Delivery Delay', 'Other'],
];
