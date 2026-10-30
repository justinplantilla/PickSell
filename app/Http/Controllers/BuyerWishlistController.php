<?php

namespace App\Http\Controllers;

use App\Models\CartItem;
use App\Models\Product;
use App\Models\WishlistItem;
use Illuminate\Http\Request;

/** Buyer wishlist. The rows are shared with the buyer mobile app. */
class BuyerWishlistController extends Controller
{
    public function index()
    {
        $buyer = auth()->user();
        $products = Product::query()
            ->purchasable()
            ->whereIn('id', WishlistItem::where('user_id', $buyer->id)->select('product_id'))
            ->with('seller', 'images')
            ->latest()
            ->paginate(24);
        $cartCount = CartItem::whereHas('cart', fn ($q) => $q->where('buyer_id', $buyer->id))->count();

        return view('buyer.wishlist', compact('products', 'cartCount'));
    }

    public function store(Request $request, Product $product)
    {
        abort_unless($product->isPurchasable(), 404);
        WishlistItem::firstOrCreate(['user_id' => auth()->id(), 'product_id' => $product->id]);

        return $request->expectsJson()
            ? response()->json(['wishlisted' => true])
            : back()->with('success', 'Saved to your wishlist.');
    }

    public function destroy(Request $request, Product $product)
    {
        WishlistItem::where('user_id', auth()->id())->where('product_id', $product->id)->delete();

        return $request->expectsJson()
            ? response()->json(['wishlisted' => false])
            : back()->with('success', 'Removed from your wishlist.');
    }
}
