<?php

use App\Pickrush\Http\Controllers\AuthController;
use App\Pickrush\Http\Controllers\DashboardController;
use App\Pickrush\Http\Controllers\HubController;
use App\Pickrush\Http\Controllers\TrackingController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Pickrush web portal
|--------------------------------------------------------------------------
| Served only on the logistics host (config pickrush.domain). Registered before the
| marketplace routes so "/" and "/login" on that host belong to Pickrush. Every route is
| named pickrush.*; IsolatePickrushHost 404s anything else on the host.
*/

Route::domain(config('pickrush.domain'))->name('pickrush.')->group(function () {
    // Public: anyone with a tracking number.
    Route::get('/track', TrackingController::class)->middleware('throttle:30,1')->name('track');

    Route::middleware('guest:pickrush')->group(function () {
        Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
        Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1')->name('login.attempt');
    });

    Route::middleware(['auth:pickrush', 'pickrush.role'])->group(function () {
        Route::get('/', DashboardController::class)->name('dashboard');
        Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

        Route::middleware('pickrush.role:super_admin,finance,support')->group(function () {
            Route::get('/hubs', [HubController::class, 'index'])->name('hubs.index');
            Route::get('/hubs/{hub}', [HubController::class, 'show'])->name('hubs.show');
        });
    });
});
