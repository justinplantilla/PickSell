<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\BuyerController;

Route::middleware('auth')->group(function () {
    Route::patch('/buyer/account', [BuyerController::class, 'updateAccount']);
});

Route::get('/ping', function (Request $request) {
    return response()->json(['ok' => true]);
});
