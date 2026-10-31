<?php

use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Laravel\Sanctum\Http\Middleware\AuthenticateSession;

/*
| Login tokens for the PickSell buyer mobile app (routes/buyer-api.php). The app sends
| "Authorization: Bearer <token>" from /api/mobile/login. Only bearer tokens are accepted:
| no stateful domains and no session guard, so a browser's website session never signs in
| to the mobile API.
*/
return [
    'stateful' => [],

    'guard' => [],

    // App login lifetime in minutes (30 days).
    'expiration' => env('SANCTUM_EXPIRATION', 60 * 24 * 30),

    'token_prefix' => env('SANCTUM_TOKEN_PREFIX', ''),

    'middleware' => [
        'authenticate_session' => AuthenticateSession::class,
        'encrypt_cookies' => EncryptCookies::class,
        'validate_csrf_token' => ValidateCsrfToken::class,
    ],
];
