<?php

use App\Pickrush\Http\Controllers\AuthController;
use App\Pickrush\Http\Controllers\DashboardController;
use App\Pickrush\Http\Controllers\HubController;
use App\Pickrush\Http\Controllers\ParcelController;
use App\Pickrush\Http\Controllers\Station;
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

        Route::get('/parcels', [ParcelController::class, 'index'])->name('parcels.index');
        Route::get('/parcels/{parcel}', [ParcelController::class, 'show'])->name('parcels.show');

        Route::middleware('pickrush.role:super_admin,finance,support')->group(function () {
            Route::get('/hubs', [HubController::class, 'index'])->name('hubs.index');
            Route::get('/hubs/{hub}', [HubController::class, 'show'])->name('hubs.show');
        });

        // Station hub portal: first mile, last mile, riders and cash for one municipality.
        Route::prefix('station')->name('station.')->middleware('pickrush.station')->group(function () {
            Route::get('/', Station\DashboardController::class)->name('dashboard');

            Route::get('/receiving', [Station\ReceivingController::class, 'index'])->name('receiving');
            Route::post('/receiving', [Station\ReceivingController::class, 'scan'])->name('receiving.scan');

            Route::get('/trips', [Station\TripController::class, 'index'])->name('trips.index');
            Route::post('/trips', [Station\TripController::class, 'store'])->name('trips.store');
            Route::get('/trips/{manifest}', [Station\TripController::class, 'show'])->name('trips.show');
            Route::post('/trips/{manifest}/parcels', [Station\TripController::class, 'add'])->name('trips.add');
            Route::post('/trips/{manifest}/parcels/all', [Station\TripController::class, 'addAll'])->name('trips.add-all');
            Route::delete('/trips/{manifest}/parcels/{item}', [Station\TripController::class, 'remove'])->name('trips.remove');
            Route::post('/trips/{manifest}/dispatch', [Station\TripController::class, 'dispatch'])->name('trips.dispatch');
            Route::post('/trips/{manifest}/receive', [Station\TripController::class, 'receive'])->name('trips.receive');
            Route::post('/trips/{manifest}/close', [Station\TripController::class, 'close'])->name('trips.close');

            Route::get('/dispatch', [Station\DispatchController::class, 'index'])->name('dispatch');
            Route::post('/dispatch', [Station\DispatchController::class, 'assign'])->name('dispatch.assign');
            Route::post('/assignments/{assignment}/unassign', [Station\DispatchController::class, 'unassign'])->name('dispatch.unassign');

            Route::get('/failed', [Station\FailedController::class, 'index'])->name('failed');
            Route::post('/failed/{parcel}/return', [Station\FailedController::class, 'returnToSender'])->name('failed.return');

            Route::get('/riders', [Station\RiderController::class, 'index'])->name('riders.index');
            Route::get('/riders/{rider}', [Station\RiderController::class, 'show'])->whereNumber('rider')->name('riders.show');
            Route::post('/riders/{rider}/verify', [Station\RiderController::class, 'verify'])->name('riders.verify');
            Route::patch('/riders/{rider}/shift', [Station\RiderController::class, 'shift'])->name('riders.shift');
            Route::get('/riders/{rider}/documents/{document}', [Station\RiderController::class, 'document'])->middleware('signed')->name('riders.document');

            Route::get('/zones', [Station\ZoneController::class, 'index'])->name('zones.index');

            Route::get('/cash', [Station\CashController::class, 'index'])->name('cash');

            // Hub manager only.
            Route::middleware('pickrush.station:manager')->group(function () {
                Route::get('/riders/create', [Station\RiderController::class, 'create'])->name('riders.create');
                Route::post('/riders', [Station\RiderController::class, 'store'])->name('riders.store');
                Route::put('/riders/{rider}', [Station\RiderController::class, 'update'])->name('riders.update');
                Route::post('/riders/{rider}/documents', [Station\RiderController::class, 'uploadDocument'])->name('riders.documents.store');
                Route::post('/riders/{rider}/approve', [Station\RiderController::class, 'approve'])->name('riders.approve');
                Route::post('/riders/{rider}/status', [Station\RiderController::class, 'changeStatus'])->name('riders.status');
                Route::patch('/riders/{rider}/limits', [Station\RiderController::class, 'limits'])->name('riders.limits');

                Route::get('/zones/create', [Station\ZoneController::class, 'create'])->name('zones.create');
                Route::post('/zones', [Station\ZoneController::class, 'store'])->name('zones.store');
                Route::get('/zones/{zone}/edit', [Station\ZoneController::class, 'edit'])->name('zones.edit');
                Route::put('/zones/{zone}', [Station\ZoneController::class, 'update'])->name('zones.update');

                Route::post('/cash/riders/{rider}', [Station\CashController::class, 'record'])->name('cash.record');
                Route::post('/cash/remittances/{remittance}/resolve', [Station\CashController::class, 'resolve'])->name('cash.resolve');
                Route::post('/cash/remit', [Station\CashController::class, 'remit'])->name('cash.remit');
            });
        });
    });
});
