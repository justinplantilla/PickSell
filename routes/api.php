<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\BuyerController;
use App\Models\Product;

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
        ->get(['name', 'category']);

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
