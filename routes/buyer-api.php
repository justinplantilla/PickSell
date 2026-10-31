<?php

use App\Http\Controllers\BuyerApi\AuthController;
use App\Http\Controllers\BuyerApi\CartController;
use App\Http\Controllers\BuyerApi\CatalogController;
use App\Http\Controllers\BuyerApi\ChatController;
use App\Http\Controllers\BuyerApi\CheckoutController;
use App\Http\Controllers\BuyerApi\NotificationController;
use App\Http\Controllers\BuyerApi\OrderController;
use App\Http\Controllers\BuyerApi\ProfileController;
use App\Http\Controllers\BuyerApi\WishlistController;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
| PickSell buyer app API (/api/mobile/...). The app is built with
| API_BASE_URL=https://pick-sell.shop/api/mobile. Signed-in requests send
| "Authorization: Bearer <token>" from /login. Every rule follows the website's buyer pages;
| each throttle has its own prefix so limits don't share a counter.
*/

Route::prefix('mobile')->name('buyer-api.')->group(function () {
    // Sign-up (website flow: email code → details with ID → pending admin approval), login, reset.
    Route::post('/register/email-code', [AuthController::class, 'sendRegistrationCode'])->middleware('throttle:6,1,buyer-api-register-code')->name('register.email-code');
    Route::post('/register/email-code/verify', [AuthController::class, 'verifyRegistrationCode'])->middleware('throttle:20,1,buyer-api-register-verify')->name('register.email-code.verify');
    Route::post('/register/check-contact', [AuthController::class, 'checkContact'])->middleware('throttle:20,1,buyer-api-check-contact')->name('register.check-contact');
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:10,1,buyer-api-register')->name('register');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1,buyer-api-login')->name('login');
    Route::post('/password/forgot', [AuthController::class, 'forgotPassword'])->middleware('throttle:5,1,buyer-api-password-forgot')->name('password.forgot');
    Route::post('/password/reset', [AuthController::class, 'resetPassword'])->middleware('throttle:10,1,buyer-api-password-reset')->name('password.reset');

    // Public catalog (a bearer token is optional: it adds `wishlisted` and records recently viewed).
    Route::middleware('throttle:120,1,buyer-api-catalog')->group(function () {
        Route::get('/home', [CatalogController::class, 'home'])->name('home');
        Route::get('/categories', [CatalogController::class, 'categories'])->name('categories');
        Route::get('/products', [CatalogController::class, 'index'])->name('products.index');
        Route::get('/products/{product}', [CatalogController::class, 'show'])->name('products.show');
        Route::get('/products/{product}/reviews', [CatalogController::class, 'reviews'])->name('products.reviews');
        Route::get('/sellers/{seller}', [CatalogController::class, 'seller'])->name('sellers.show');
        Route::get('/payment-methods', [CheckoutController::class, 'paymentMethods'])->name('payment-methods');
        Route::get('/legal/{document}', [NotificationController::class, 'legal'])->whereIn('document', ['terms', 'privacy'])->name('legal');
    });

    Route::middleware(['auth:sanctum', 'buyer.api', 'throttle:120,1,buyer-api'])->group(function () {
        Route::prefix('buyer')->group(function () {
            Route::get('/profile', [ProfileController::class, 'show'])->name('profile.show');
            Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
            Route::patch('/password', [ProfileController::class, 'password'])->name('password.update');
            Route::post('/logout', [ProfileController::class, 'logout'])->name('logout');

            Route::get('/wishlist', [WishlistController::class, 'index'])->name('wishlist.index');
            Route::post('/wishlist/{product}', [WishlistController::class, 'store'])->name('wishlist.store');
            Route::delete('/wishlist/{product}', [WishlistController::class, 'destroy'])->name('wishlist.destroy');
            Route::get('/recently-viewed', [WishlistController::class, 'recentlyViewed'])->name('recently-viewed.index');
            Route::delete('/recently-viewed', [WishlistController::class, 'clearRecentlyViewed'])->name('recently-viewed.clear');

            Route::get('/cart', [CartController::class, 'index'])->name('cart.index');
            Route::get('/cart/count', [CartController::class, 'summary'])->name('cart.count');
            Route::post('/cart', [CartController::class, 'store'])->name('cart.store');
            Route::patch('/cart/{item}', [CartController::class, 'update'])->name('cart.update');
            Route::delete('/cart/{item}', [CartController::class, 'destroy'])->name('cart.destroy');
            Route::delete('/cart', [CartController::class, 'destroyMany'])->name('cart.destroy-many');

            Route::post('/checkout/quote', [CheckoutController::class, 'quote'])->name('checkout.quote');
            Route::post('/checkout', [CheckoutController::class, 'place'])->middleware('throttle:10,1,buyer-api-checkout')->name('checkout.place');

            Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
            Route::get('/orders/options', [OrderController::class, 'options'])->name('orders.options');
            Route::get('/orders/{order}', [OrderController::class, 'show'])->name('orders.show');
            Route::post('/orders/{order}/cancel', [OrderController::class, 'cancel'])->name('orders.cancel');
            Route::post('/orders/{order}/rate', [OrderController::class, 'rate'])->name('orders.rate');
            Route::post('/orders/{order}/report', [OrderController::class, 'report'])->middleware('throttle:10,1,buyer-api-report')->name('orders.report');
            Route::post('/orders/{order}/return', [OrderController::class, 'requestReturn'])->middleware('throttle:10,1,buyer-api-return')->name('orders.return');
            Route::post('/orders/{order}/reorder', [OrderController::class, 'reorder'])->name('orders.reorder');
            Route::get('/reorder', [OrderController::class, 'reorderCandidates'])->name('reorder');
            Route::get('/support/tickets', [OrderController::class, 'tickets'])->name('support.tickets');

            Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
            Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');
        });

        Route::get('/chat/contacts', [ChatController::class, 'contacts'])->name('chat.contacts');
        Route::get('/chat/messages/{userId}', [ChatController::class, 'messages'])->whereNumber('userId')->name('chat.messages');
        Route::post('/chat/send', [ChatController::class, 'send'])->middleware('throttle:30,1,buyer-api-chat-send')->name('chat.send');
    });

    Route::get('/ping', fn () => response()->json(['ok' => true]))->name('ping');

    Route::middleware('throttle:60,1,buyer-api-suggestions')->get('/search-suggestions', function (Request $request) {
        $query = trim((string) $request->get('q', ''));
        if (mb_strlen($query) < 2) return response()->json(['products' => [], 'categories' => []]);

        $products = Product::without('images')->purchasable()->where('stock', '>', 0)
            ->where(fn ($builder) => $builder->where('name', 'like', "%{$query}%")->orWhere('category', 'like', "%{$query}%"))
            ->latest()->take(6)->get(['name', 'category']);

        $categories = Product::without('images')->purchasable()->where('stock', '>', 0)
            ->where('category', 'like', "%{$query}%")->whereNotNull('category')
            ->distinct()->orderBy('category')->limit(3)->pluck('category');

        return response()->json(['products' => $products, 'categories' => $categories]);
    })->name('search-suggestions');
});
