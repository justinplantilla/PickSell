<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\BuyerController;
use App\Http\Controllers\Api\V1\RiderController;
use App\Http\Middleware\RiderApiMiddleware;
use App\Models\Product;
use Illuminate\Support\Facades\Storage;

Route::prefix('v1')->group(function () {
    Route::middleware('throttle:api')->group(function () {
        Route::post('/auth/login', [RiderController::class, 'login'])
            ->middleware('throttle:10,1')
            ->name('api.v1.auth.login');

        Route::middleware(['auth:sanctum', RiderApiMiddleware::class])->group(function () {
            Route::middleware('abilities:rider:read')->group(function () {
                Route::get('/rider/assignments', [RiderController::class, 'assignments'])
                    ->name('api.v1.rider.assignments');
                Route::get('/rider/deliveries', [RiderController::class, 'deliveries'])
                    ->name('api.v1.rider.deliveries');
                Route::get('/rider/deliveries/{delivery}', [RiderController::class, 'showDeliveryById'])
                    ->name('api.v1.rider.deliveries.show');
                Route::get('/rider/deliveries/{delivery}/proof/{log}', [RiderController::class, 'showProofImage'])
                    ->name('api.v1.rider.deliveries.proof');
                Route::get('/rider/me', [RiderController::class, 'me'])
                    ->name('api.v1.rider.me');
            });

            Route::middleware('abilities:rider:write')->group(function () {
                Route::post('/auth/logout', [RiderController::class, 'logout'])
                    ->name('api.v1.auth.logout');
                Route::post('/rider/assignments/{assignment}/respond', [RiderController::class, 'respondToOffer'])
                    ->name('api.v1.rider.assignments.respond');
                Route::post('/rider/deliveries/{delivery}/status', [RiderController::class, 'updateDeliveryStatus'])
                    ->name('api.v1.rider.deliveries.status');
            });
        });

        // Temporary route aliases preserve compatibility with the first Rider API iteration.
        Route::post('/rider/login', [RiderController::class, 'login'])
            ->middleware('throttle:10,1')
            ->name('api.v1.rider.login');
        Route::middleware(['auth:sanctum', RiderApiMiddleware::class])->group(function () {
            Route::post('/rider/logout', [RiderController::class, 'logout'])
                ->middleware('abilities:rider:write')
                ->name('api.v1.rider.logout');
        });
    });
});

Route::middleware('auth')->group(function () {
    Route::patch('/buyer/account', [BuyerController::class, 'updateAccount']);
});

Route::get('/ping', function (Request $request) {
    return response()->json(['ok' => true]);
});

Route::get('/search-suggestions', function (Request $request) {
    $query = trim((string) $request->get('q', ''));
    if (mb_strlen($query) < 2) return response()->json(['products' => [], 'categories' => []]);

    $products = Product::query()
        ->where('status', 'active')
        ->where('stock', '>', 0)
        ->where(function ($builder) use ($query) {
            $builder->where('name', 'like', "%{$query}%")
                ->orWhere('category', 'like', "%{$query}%");
        })
        ->latest()
        ->take(6)
        ->get()
        ->map(fn (Product $product) => [
            'name' => $product->name,
            'category' => $product->category,
            'image' => $product->primary_image ? Storage::url($product->primary_image) : null,
            'price' => $product->effective_price,
        ]);

    $categories = Product::query()
        ->where('status', 'active')
        ->where('stock', '>', 0)
        ->where('category', 'like', "%{$query}%")
        ->whereNotNull('category')
        ->distinct()
        ->orderBy('category')
        ->limit(3)
        ->pluck('category');

    return response()->json(['products' => $products, 'categories' => $categories]);
});
