<?php

use App\Pickrush\Http\Controllers\Api\RiderApplicationController;
use App\Pickrush\Http\Controllers\Api\RiderAuthController;
use Illuminate\Support\Facades\Route;

/*
| Pickrush rider app API (/api/rider/...). Riders apply here and the station hub manager approves
| in the portal. Signed-in requests send "Authorization: Bearer <token>" from /login.
*/

Route::prefix('rider')->name('pickrush.api.rider.')->group(function () {
    Route::middleware('throttle:pickrush-rider-auth')->group(function () {
        Route::post('/email-code/send', [RiderAuthController::class, 'sendCode'])->name('email-code.send');
        Route::post('/email-code/verify', [RiderAuthController::class, 'verifyCode'])->name('email-code.verify');
        Route::post('/login', [RiderAuthController::class, 'login'])->name('login');
    });

    Route::get('/stations', [RiderApplicationController::class, 'stations'])->middleware('throttle:pickrush-rider-read')->name('stations');
    Route::post('/apply', [RiderApplicationController::class, 'register'])->middleware('throttle:pickrush-rider-apply')->name('apply');

    Route::middleware('pickrush.rider')->group(function () {
        Route::get('/me', [RiderAuthController::class, 'me'])->name('me');
        Route::post('/logout', [RiderAuthController::class, 'logout'])->name('logout');
        Route::post('/application', [RiderApplicationController::class, 'resubmit'])->middleware('throttle:pickrush-rider-apply')->name('resubmit');
    });
});
