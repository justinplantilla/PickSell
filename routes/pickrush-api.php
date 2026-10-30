<?php

use App\Pickrush\Http\Controllers\Api\RiderApplicationController;
use App\Pickrush\Http\Controllers\Api\RiderAuthController;
use App\Pickrush\Http\Controllers\Api\RiderBatchController;
use App\Pickrush\Http\Controllers\Api\RiderEarningsController;
use App\Pickrush\Http\Controllers\Api\RiderHomeController;
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
        Route::post('/password/forgot', [RiderAuthController::class, 'forgotPassword'])->name('password.forgot');
        Route::post('/password/reset', [RiderAuthController::class, 'resetPassword'])->name('password.reset');
    });

    Route::get('/stations', [RiderApplicationController::class, 'stations'])->middleware('throttle:pickrush-rider-read')->name('stations');
    Route::post('/apply', [RiderApplicationController::class, 'register'])->middleware('throttle:pickrush-rider-apply')->name('apply');

    Route::middleware('pickrush.rider')->group(function () {
        Route::get('/me', [RiderAuthController::class, 'me'])->name('me');
        Route::post('/logout', [RiderAuthController::class, 'logout'])->name('logout');
        Route::post('/password', [RiderAuthController::class, 'changePassword'])->middleware('throttle:pickrush-rider-auth')->name('password');
        Route::post('/application', [RiderApplicationController::class, 'resubmit'])->middleware('throttle:pickrush-rider-apply')->name('resubmit');
    });

    // Approved riders only: home, notifications, shift, and pickup batches.
    Route::middleware('pickrush.rider:active')->group(function () {
        Route::get('/dashboard', [RiderHomeController::class, 'dashboard'])->name('dashboard');
        Route::get('/pulse', [RiderHomeController::class, 'pulse'])->name('pulse');
        Route::patch('/shift', [RiderHomeController::class, 'shift'])->name('shift');
        Route::get('/options', [RiderHomeController::class, 'options'])->name('options');
        Route::get('/earnings', [RiderEarningsController::class, 'index'])->name('earnings');
        Route::get('/notifications', [RiderHomeController::class, 'notifications'])->name('notifications');
        Route::post('/notifications/read', [RiderHomeController::class, 'readNotifications'])->name('notifications.read');

        Route::get('/batches', [RiderBatchController::class, 'index'])->name('batches.index');
        Route::get('/batches/{batch}', [RiderBatchController::class, 'show'])->whereNumber('batch')->name('batches.show');
        Route::post('/batches/{batch}/accept', [RiderBatchController::class, 'accept'])->whereNumber('batch')->name('batches.accept');
        Route::post('/batches/{batch}/decline', [RiderBatchController::class, 'decline'])->whereNumber('batch')->name('batches.decline');
        Route::post('/batches/{batch}/pickups', [RiderBatchController::class, 'pickUp'])->whereNumber('batch')->name('batches.pickups');
        Route::post('/batches/{batch}/stops/arrive', [RiderBatchController::class, 'arriveAtStop'])->whereNumber('batch')->name('batches.stops.arrive');
        Route::post('/batches/{batch}/hub/arrive', [RiderBatchController::class, 'arriveAtHub'])->whereNumber('batch')->name('batches.hub.arrive');
        Route::post('/batches/{batch}/reports', [RiderBatchController::class, 'report'])->whereNumber('batch')->name('batches.reports');

        // Delivery batches: load at the station, start the route, then deliver or fail each parcel.
        Route::post('/batches/{batch}/load', [RiderBatchController::class, 'load'])->whereNumber('batch')->name('batches.load');
        Route::post('/batches/{batch}/start', [RiderBatchController::class, 'startRoute'])->whereNumber('batch')->name('batches.start');
        Route::post('/batches/{batch}/deliveries/{assignment}/deliver', [RiderBatchController::class, 'deliver'])->whereNumber(['batch', 'assignment'])->name('batches.deliver');
        Route::post('/batches/{batch}/deliveries/{assignment}/fail', [RiderBatchController::class, 'fail'])->whereNumber(['batch', 'assignment'])->name('batches.fail');
    });
});
