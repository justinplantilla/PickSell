<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\BuyerController;
use App\Models\Product;
use Illuminate\Support\Facades\Storage;

Route::middleware('auth')->group(function () {
    Route::patch('/buyer/account', [BuyerController::class, 'updateAccount']);
});

Route::get('/products', function (Request $request) {
    $products = Product::with('images', 'seller')
        ->purchasable()
        ->when($request->get('category'), fn ($q, $cat) => $q->where('category', $cat))
        ->when($request->get('search'), fn ($q, $s) => $q->where('name', 'like', "%{$s}%"))
        ->latest()
        ->paginate(20);

    return response()->json([
        'products' => $products->map(fn ($p) => [
            'id'              => $p->id,
            'name'            => $p->name,
            'category'        => $p->category,
            'price'           => (float) $p->effective_price,
            'original_price'  => $p->discount > 0 ? (float) $p->price : null,
            'stock'           => $p->stock,
            'description'     => $p->description,
            'seller_name'     => $p->seller?->business_name
                                    ?: trim(($p->seller?->first_name ?? '').' '.($p->seller?->last_name ?? '')),
            'seller_location' => implode(', ', array_filter([
                $p->seller?->barangay,
                $p->seller?->municipality,
            ])),
            'images'          => $p->images->map(fn ($img) => $img->url)->values(),
            'primary_image'   => $p->images->isNotEmpty()
                                    ? ($p->images->firstWhere('is_primary', true) ?? $p->images->first())?->url
                                    : null,
        ]),
        'next_page_url' => $products->nextPageUrl(),
    ]);
});

// Pickrush rider app.
require __DIR__.'/pickrush-api.php';

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
            $builder->whereLike('name', "%{$query}%")
                ->orWhereLike('category', "%{$query}%");
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
        ->whereLike('category', "%{$query}%")
        ->whereNotNull('category')
        ->distinct()
        ->orderBy('category')
        ->limit(3)
        ->pluck('category');

    return response()->json(['products' => $products, 'categories' => $categories]);
});
