<?php

namespace App\Http\Controllers;

use App\Mail\NewMessageMail;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Message;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\ProductReviewPhoto;
use App\Models\ProductReviewVote;
use App\Models\Voucher;
use App\Support\ReviewReports;
use App\Services\Vouchers\VoucherPricing;
use App\Models\User;
use App\Models\WishlistItem;
use App\Pickrush\Bridge\PicksellBridge;
use App\Services\CartQuote;
use App\Services\OrderPlacement;
use App\Services\Orders\OrderRating;
use App\Support\DeliveryAddress;
use App\Services\RecentlyViewed;
use App\Services\LogisticsRoutingService;
use App\Services\MessagingService;
use App\Services\Orders\OrderCancellationService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class BuyerController extends Controller
{
    private function buyer()
    {
        return auth()->user();
    }

    private function createBuyerNotification(User $user, string $message, ?Order $order = null): void
    {
        $user->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => 'App\\Notifications\\BuyerOrderUpdate',
            'data' => json_encode([
                'type' => $order ? 'order' : 'message',
                'order_id' => $order?->id,
                'order_number' => $order?->order_number,
                'message' => $message,
            ]),
        ]);
    }

    private function getCart()
    {
        return Cart::firstOrCreate(['buyer_id' => $this->buyer()->id]);
    }

    // Home / Shop
    public function home(Request $request)
    {
        $search = $request->get('search');
        $category = $request->get('category');
        $deals = $request->boolean('deals') || $request->routeIs('buyer.deals');

        $query = Product::showcased()->where('stock', '>', 0)->with('seller');

        if ($search) {
            $query->whereLike('name', "%$search%");
        }
        if ($category) {
            $query->where('category', $category);
        }
        if ($deals) {
            $query->where('discount', '>', 0);
        }

        $products = $query->latest()->paginate(12);

        // 14 deal products shown on home (no pagination) — only when not filtering
        $dealProducts = null;
        if (!$search && !$category && !$deals) {
            $dealProducts = Product::showcased()
                ->where('stock', '>', 0)
                ->where('discount', '>', 0)
                ->with('seller')
                ->latest()
                ->take(14)
                ->get();
        }

        $recommendedProducts = Product::showcased()
            ->where('stock', '>', 0)
            ->with('seller')
            ->latest()
            ->take(8)
            ->get();
        $topCategoryProducts = Product::showcased()
            ->where('stock', '>', 0)
            ->with('seller')
            ->withSum([
                'orders as units_sold' => fn ($orders) => $orders->whereIn('status', ['delivered', 'completed']),
            ], 'quantity')
            ->orderByDesc('units_sold')
            ->latest()
            ->get()
            ->unique(fn (Product $product) => $product->category ?? $product->id)
            ->take(4)
            ->values();
        $categoryNames = ['Beauty', 'Books', 'Clothing', 'Electronics', 'Fashion', 'Food & Grocery', 'Home & Living', 'Pet Supplies', 'Sports', 'Toys'];
        $categories = collect($categoryNames)
            ->merge(Product::showcased()->whereNotNull('category')->distinct()->pluck('category'))
            ->filter()
            ->unique()
            ->sort()
            ->values();
        $cartCount = CartItem::whereHas('cart', fn ($q) => $q->where('buyer_id', $this->buyer()->id))->count();
        $recentlyViewed = (!$search && !$category && !$deals) ? app(RecentlyViewed::class)->products($this->buyer(), 10) : collect();

        return view('buyer.home', compact('products', 'dealProducts', 'recommendedProducts', 'topCategoryProducts', 'categories', 'search', 'category', 'deals', 'cartCount', 'recentlyViewed'));
    }

    public function recommended(Request $request)
    {
        $page = max(1, (int) $request->get('page', 1));
        $products = Product::showcased()
            ->where('stock', '>', 0)
            ->with('seller')
            ->latest()
            ->paginate(24, ['*'], 'page', $page);

        if ($request->wantsJson()) {
            return response()->json([
                'html' => view('buyer.partials.product-cards', ['products' => $products->items()])->render(),
                'hasMore' => $products->hasMorePages(),
                'nextPage' => $products->currentPage() + 1,
            ]);
        }

        $cartCount = CartItem::whereHas('cart', fn ($q) => $q->where('buyer_id', $this->buyer()->id))->count();
        return view('buyer.recommended', compact('products', 'cartCount'));
    }

    /** What buyers pay for one unit, in SQL, so price filters and sorts follow the discount. */
    private const EFFECTIVE_PRICE_SQL = 'price * (1 - discount / 100.0)';

    public const BROWSE_SORTS = [
        'latest' => 'Latest', 'popular' => 'Best selling', 'rating' => 'Top rated',
        'price_asc' => 'Price: Low to High', 'price_desc' => 'Price: High to Low',
    ];

    public function browse(Request $request)
    {
        $search = trim((string) $request->get('search', ''));
        $category = $request->get('category');
        $sort = array_key_exists($request->get('sort'), self::BROWSE_SORTS) ? $request->get('sort') : 'latest';
        $dealsOnly = $request->boolean('deals');
        $minPrice = is_numeric($request->get('min_price')) ? max(0, (float) $request->get('min_price')) : null;
        $maxPrice = is_numeric($request->get('max_price')) ? max(0, (float) $request->get('max_price')) : null;
        $minRating = in_array((int) $request->get('rating'), [3, 4], true) ? (int) $request->get('rating') : null;
        // "near" means stores in the buyer's own province: the cheapest, fastest shipping.
        $location = trim((string) $request->get('location', ''));
        $province = $location === 'near' ? $this->buyer()->province : ($location ?: null);

        $query = Product::showcased()->where('stock', '>', 0)->with('seller')->withAvg('reviews', 'rating')->withCount('reviews');

        if ($search) {
            $query->where(function ($b) use ($search) {
                $b->whereLike('name', "%{$search}%")
                  ->orWhereLike('category', "%{$search}%")
                  ->orWhereHas('seller', fn ($s) => $s->whereLike('business_name', "%{$search}%"));
            });
        }
        if ($category) {
            $query->where('category', $category);
        }
        if ($dealsOnly) {
            $query->where('discount', '>', 0);
        }
        if ($minPrice !== null) {
            $query->whereRaw(self::EFFECTIVE_PRICE_SQL.' >= CAST(? AS DECIMAL(12,2))', [$minPrice]);
        }
        if ($maxPrice !== null) {
            $query->whereRaw(self::EFFECTIVE_PRICE_SQL.' <= CAST(? AS DECIMAL(12,2))', [$maxPrice]);
        }
        if ($minRating) {
            $query->whereRaw('(select avg(rating) from product_reviews where product_reviews.product_id = products.id and product_reviews.hidden_at is null) >= ?', [$minRating]);
        }
        if ($province) {
            $query->whereHas('seller', fn ($s) => $s->where('province', $province));
        }

        match ($sort) {
            'popular' => $query->withSum(['orders as units_sold' => fn ($q) => $q->whereIn('status', ['delivered', 'completed'])], 'quantity')->orderByDesc('units_sold'),
            'rating' => $query->orderByDesc('reviews_avg_rating')->orderByDesc('reviews_count'),
            'price_asc' => $query->orderByRaw(self::EFFECTIVE_PRICE_SQL.' asc'),
            'price_desc' => $query->orderByRaw(self::EFFECTIVE_PRICE_SQL.' desc'),
            default => $query->latest(),
        };

        $products = $query->paginate(30)->withQueryString();
        $provinces = User::where('role', 'seller')->where('status', 'approved')->whereHas('products', fn ($p) => $p->showcased())
            ->whereNotNull('province')->distinct()->orderBy('province')->pluck('province');
        $filters = compact('minPrice', 'maxPrice', 'minRating', 'location');
        $categoryNames = ['Beauty', 'Books', 'Clothing', 'Electronics', 'Fashion', 'Food & Grocery', 'Home & Living', 'Pet Supplies', 'Sports', 'Toys'];
        $categories = collect($categoryNames)
            ->merge(Product::showcased()->whereNotNull('category')->distinct()->pluck('category'))
            ->filter()->unique()->sort()->values();
        $cartCount = CartItem::whereHas('cart', fn ($q) => $q->where('buyer_id', $this->buyer()->id))->count();

        return view('buyer.browse', compact('products', 'categories', 'search', 'category', 'sort', 'dealsOnly', 'cartCount', 'provinces', 'filters'));
    }

    public function categories(Request $request)
    {
        $category = $request->query('category');
        $categories = Product::showcased()
            ->where('stock', '>', 0)
            ->whereNotNull('category')
            ->distinct()
            ->orderBy('category')
            ->pluck('category');
        $categoryCounts = Product::showcased()
            ->where('stock', '>', 0)
            ->selectRaw('category, COUNT(*) as product_count')
            ->whereNotNull('category')
            ->groupBy('category')
            ->pluck('product_count', 'category');
        $products = Product::showcased()
            ->where('stock', '>', 0)
            ->with('seller')
            ->when($category, fn ($query) => $query->where('category', $category))
            ->latest()
            ->paginate(12)
            ->withQueryString();
        $cartCount = CartItem::whereHas('cart', fn ($q) => $q->where('buyer_id', $this->buyer()->id))->count();

        return view('buyer.categories', compact('categories', 'categoryCounts', 'products', 'category', 'cartCount'));
    }

    // Search Results: the browse page has the filters and sorts, so searches land there.
    public function search(Request $request)
    {
        return redirect()->route('buyer.browse', $request->query());
    }

    // Product Detail
    public function productDetail(Product $product, RecentlyViewed $recentlyViewed)
    {
        abort_if($product->status !== 'active', 404);
        $product->load(['seller', 'variations', 'reviews' => fn ($q) => $q->with('buyer', 'photos')->withCount('votes')->latest()]);
        $helpfulIds = ProductReviewVote::where('user_id', $this->buyer()->id)->whereIn('product_review_id', $product->reviews->pluck('id'))->pluck('product_review_id')->all();
        $cartCount = CartItem::whereHas('cart', fn ($q) => $q->where('buyer_id', $this->buyer()->id))->count();
        $reviewCount = $product->reviews->count();
        $reviewAverage = $reviewCount ? round($product->reviews->avg('rating'), 1) : 0;
        $wishlisted = WishlistItem::where('user_id', $this->buyer()->id)->where('product_id', $product->id)->exists();
        $recentlyViewed->record($this->buyer(), $product);

        return view('buyer.product-detail', compact('product', 'cartCount', 'reviewCount', 'reviewAverage', 'wishlisted', 'helpfulIds'));
    }

    public function sellerStorefront(User $seller)
    {
        abort_unless($seller->role === 'seller' && $seller->status === 'approved', 404);

        $products = $seller->products()
            ->where('status', 'active')
            ->where('stock', '>', 0)
            ->latest()
            ->paginate(12);
        $cartCount = CartItem::whereHas('cart', fn ($q) => $q->where('buyer_id', $this->buyer()->id))->count();

        return view('buyer.seller-storefront', compact('seller', 'products', 'cartCount'));
    }

    /** The stores this buyer follows, most recently followed first. */
    public function following()
    {
        $stores = $this->buyer()->followedSellers()
            ->withCount(['products as active_products_count' => fn ($q) => $q->where('status', 'active')])
            ->orderByPivot('created_at', 'desc')->paginate(20);
        $cartCount = CartItem::whereHas('cart', fn ($q) => $q->where('buyer_id', $this->buyer()->id))->count();

        return view('buyer.following', compact('stores', 'cartCount'));
    }

    /** Follow a store, or unfollow it if already following. */
    public function toggleFollow(Request $request, User $seller)
    {
        abort_unless($seller->role === 'seller' && $seller->status === 'approved', 404);
        $result = $this->buyer()->followedSellers()->toggle($seller->id);
        $following = ! empty($result['attached']);

        return $request->expectsJson()
            ? response()->json(['following' => $following, 'followers' => $seller->followers()->count()])
            : back()->with('success', $following ? 'You are now following this store.' : 'You unfollowed this store.');
    }

    // Add to Cart
    public function addToCart(Request $request, Product $product)
    {
        $data = $request->validate([
            'quantity' => 'required|integer|min:1',
            // Only this product's own options; one is required when it is sold in options.
            'variation_id' => [
                Rule::requiredIf(fn () => $product->variations()->exists()),
                'nullable',
                Rule::exists('product_variations', 'id')->where('product_id', $product->id),
            ],
        ], ['variation_id.required' => 'Choose an option first.', 'variation_id.exists' => 'That option isn’t available for this product.']);
        abort_unless($product->isPurchasable(), 404);

        $cart = $this->getCart();

        $item = CartItem::firstOrCreate(
            ['cart_id' => $cart->id, 'product_id' => $product->id, 'variation_id' => $data['variation_id'] ?? null],
            ['quantity' => 0]
        );
        $item->increment('quantity', $data['quantity']);

        return $request->expectsJson()
            ? response()->json([
                'success' => true,
                'message' => 'Item added to cart.',
                'item_id' => $item->id,
            ])
            : redirect()->route('buyer.cart')->with('success', 'Item added to cart.');
    }

    // Cart
    public function cart()
    {
        $cart = $this->getCart();
        // The whole cart on one page, so a store's items are never split across pages.
        $items = CartItem::where('cart_id', $cart->id)->with('product.seller', 'product.variations', 'variation')->latest()->get();
        $cartCount = CartItem::where('cart_id', $cart->id)->count();
        $recommendations = Product::purchasable()->where('stock', '>', 0)->with('seller')->latest()->take(4)->get();
        $recommendedProducts = Product::purchasable()->where('stock', '>', 0)->with('seller')->latest()->take(8)->get();

        return view('buyer.cart', compact('items', 'cartCount', 'recommendations', 'recommendedProducts'));
    }

    public function checkout(Request $request, OrderPlacement $placement)
    {
        $itemIds = $request->input('item_ids', []);
        if (empty($itemIds)) {
            return redirect()->route('buyer.cart');
        }

        $cart = $this->getCart();
        $items = CartItem::whereIn('id', $itemIds)
            ->where('cart_id', $cart->id)
            ->with('product.seller', 'product.variations', 'variation')
            ->get();

        if ($items->isEmpty()) {
            return redirect()->route('buyer.cart');
        }

        $unavailable = $items->reject(fn ($item) => $item->product?->isPurchasable());
        if ($unavailable->isNotEmpty()) {
            return redirect()->route('buyer.cart')->withErrors(['item_ids' => OrderPlacement::unavailableMessage($unavailable)]);
        }
        $unchosen = OrderPlacement::missingOption($items);
        if ($unchosen->isNotEmpty()) {
            return redirect()->route('buyer.cart')->withErrors(['item_ids' => OrderPlacement::missingOptionMessage($unchosen)]);
        }

        $total = $items->sum(fn ($i) => $i->subtotal);
        $cartCount = CartItem::where('cart_id', $cart->id)->count();

        // Where it ships: the default (profile) address unless the buyer picked one from their address book.
        $addresses = $this->buyer()->addresses;
        $addressId = $addresses->contains('id', (int) $request->query('address_id')) ? (int) $request->query('address_id') : null;
        $deliverTo = DeliveryAddress::forBuyer($this->buyer(), $addressId);

        // One parcel and one delivery fee per seller, priced the way the order will be.
        $shipping = $placement->shippingQuotes($this->buyer(), $items, $deliverTo)->map(fn ($quote, $sellerId) => [
            'seller' => $items->firstWhere('product.seller_id', $sellerId)->product->seller,
        ] + CartQuote::shipping($quote, $items->filter(fn ($item) => $item->product->seller_id == $sellerId)));
        $shippingTotal = $shipping->where('ok', true)->sum(fn ($parcel) => (float) $parcel['delivery_fee']);
        $canPlace = $deliverTo->isComplete() && $shipping->every(fn ($parcel) => $parcel['ok']);
        $vouchers = $this->checkoutVouchers($request, $items, $shippingTotal);

        return view('buyer.checkout', compact('items', 'itemIds', 'total', 'cartCount', 'shipping', 'shippingTotal', 'canPlace', 'addresses', 'addressId', 'deliverTo', 'vouchers'));
    }

    /**
     * The store and PickSell vouchers on offer at checkout, which ones are picked (store_voucher[seller],
     * platform_voucher, or a typed code) and what they save. Placing the order checks them again.
     */
    private function checkoutVouchers(Request $request, $items, float $shippingTotal): array
    {
        $pricing = app(VoucherPricing::class);
        $buyer = $this->buyer();
        $subtotals = $items->groupBy(fn ($item) => $item->product->seller_id)->map(fn ($lines) => $lines->sum(fn ($item) => $item->subtotal));
        $picked = ['stores' => array_map('intval', (array) $request->query('store_voucher', [])), 'platform' => (int) $request->query('platform_voucher') ?: null];
        $error = null;

        // A typed code picks the store or PickSell voucher it belongs to (codes are not case-sensitive).
        $code = strtoupper(trim((string) $request->query('code', '')));
        if ($code !== '') {
            $typed = Voucher::live()->whereRaw('UPPER(code) = ?', [$code])
                ->where(fn ($q) => $q->whereNull('seller_id')->orWhereIn('seller_id', $subtotals->keys()))->first();
            if (! $typed) {
                $error = "{$code} is not a voucher for these items.";
            } elseif ($typed->isPlatform()) {
                $picked['platform'] = $typed->id;
            } else {
                $picked['stores'][$typed->seller_id] = $typed->id;
            }
        }

        $stores = $subtotals->map(fn ($subtotal, $sellerId) => $pricing->options($pricing->storeVouchers($sellerId), $buyer, $subtotal));
        $storeSavings = [];
        foreach ($stores as $sellerId => $options) {
            $choice = $options->first(fn ($o) => $o['voucher']->id === ($picked['stores'][$sellerId] ?? null));
            if ($choice && $choice['problem']) {
                $error ??= "{$choice['voucher']->code}: {$choice['problem']}";
            }
            $storeSavings[$sellerId] = $choice && ! $choice['problem'] ? $choice['discount'] : 0;
            if (! $storeSavings[$sellerId]) {
                unset($picked['stores'][$sellerId]);
            }
        }

        $platformOptions = $pricing->options($pricing->platformVouchers(), $buyer, $subtotals->sum() - array_sum($storeSavings), $shippingTotal);
        $platformChoice = $platformOptions->first(fn ($o) => $o['voucher']->id === $picked['platform']);
        if ($platformChoice && $platformChoice['problem']) {
            $error ??= "{$platformChoice['voucher']->code}: {$platformChoice['problem']}";
        }
        $platformSaving = $platformChoice && ! $platformChoice['problem'] ? $platformChoice['discount'] : 0;
        if (! $platformSaving) {
            $picked['platform'] = null;
        }

        return [
            'stores' => $stores, 'platform' => $platformOptions, 'picked' => $picked, 'error' => $error,
            'storeSavings' => $storeSavings, 'platformSaving' => $platformSaving,
            'total' => round(array_sum($storeSavings) + $platformSaving, 2),
        ];
    }

    public function updateCart(Request $request, CartItem $item)
    {
        abort_if($item->cart->buyer_id !== $this->buyer()->id, 403);
        $request->validate(['quantity' => 'required|integer|min:1']);
        $item->update(['quantity' => $request->quantity]);

        return $request->expectsJson()
            ? response()->json(['success' => true])
            : back();
    }

    public function removeFromCart(CartItem $item)
    {
        abort_if($item->cart->buyer_id !== $this->buyer()->id, 403);
        $item->delete();

        return request()->expectsJson()
            ? response()->json(['success' => true])
            : back()->with('success', 'Item removed from cart.');
    }

    // Place Order: the same OrderPlacement the buyer app's checkout uses (routes/buyer-api.php).
    public function placeOrder(Request $request, OrderPlacement $placement)
    {
        $data = $request->validate([
            'item_ids' => 'required|array',
            'item_ids.*' => 'exists:cart_items,id',
            'voucher_code' => 'nullable|string',
            'address_id' => 'nullable|integer',
            'notes' => 'nullable|array',
            'notes.*' => 'nullable|string|max:300',
            'store_vouchers' => 'nullable|array',
            'store_vouchers.*' => 'integer',
            'platform_voucher' => 'nullable|integer',
        ], ['notes.*.max' => 'Keep each note to the seller under 300 characters.']);

        if ($placement->items($this->buyer(), $data['item_ids'])->isEmpty()) {
            return back()->withErrors(['item_ids' => 'No items selected.']);
        }

        try {
            $placement->place($this->buyer(), $data['item_ids'], $data['voucher_code'] ?? null,
                DeliveryAddress::forBuyer($this->buyer(), $data['address_id'] ?? null), $data['notes'] ?? [],
                ['stores' => $data['store_vouchers'] ?? [], 'platform' => $data['platform_voucher'] ?? null]);
        } catch (ValidationException $exception) {
            return redirect()->route('buyer.cart')->withErrors($exception->errors());
        }

        return redirect()->route('buyer.orders')->with('success', 'Order placed successfully!');
    }

    // Orders
    public function orders(Request $request)
    {
        $status = $request->get('status', 'all');
        $query = Order::where('buyer_id', $this->buyer()->id)->with('product', 'seller', 'returnRequest', 'shipment.orders');
        $statusGroups = [
            'pending' => ['pending', 'placed', 'confirmed'],
            'processing' => ['processing', 'preparing', 'ready_for_pickup'],
            'shipped' => ['shipped', 'picked_up', 'at_sorting_center', 'sorted', 'assigned_to_rider', 'out_for_delivery', 'delivery_failed'],
            'completed' => ['delivered', 'completed'],
            'returned' => ['returned'],
            'cancelled' => ['cancelled'],
        ];
        if (isset($statusGroups[$status])) {
            $query->whereIn('status', $statusGroups[$status]);
        } elseif ($status !== 'all') {
            $status = 'all';
        }
        $orders = $query->latest()->paginate(10);
        $tracking = $orders->getCollection()->mapWithKeys(fn ($order) => [$order->id => PicksellBridge::tracking($order)]);
        $cartCount = CartItem::whereHas('cart', fn ($q) => $q->where('buyer_id', $this->buyer()->id))->count();

        return view('buyer.orders', compact('orders', 'status', 'cartCount', 'tracking'));
    }

    public function trackOrder(Order $order)
    {
        $order = Order::query()
            ->whereKey($order->id)
            ->where('buyer_id', $this->buyer()->id)
            ->with('returnRequest')
            ->firstOrFail();
        $tracking = PicksellBridge::tracking($order);

        return view('buyer.order-tracking', compact('order', 'tracking'));
    }

    public function cancelOrder(Request $request, Order $order, OrderCancellationService $cancellations)
    {
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:1000']]);
        $cancellations->cancel($order, $this->buyer(), 'buyer', $data['reason'] ?? null);

        return back()->with('success', 'Order cancelled before pickup.');
    }

    /**
     * Buyers rate once the parcel is delivered, as in the buyer app (the seller still completes the order), and may
     * leave feedback on a cancelled order from its tracking page.
     */
    public function submitFeedback(Request $request, Order $order, OrderRating $ratings)
    {
        abort_if($order->buyer_id !== $this->buyer()->id, 403);
        abort_unless(in_array($order->status, ['delivered', 'completed', 'cancelled'], true), 422, 'You can rate an order once it has been delivered.');
        abort_if($order->rating !== null, 422, 'You already rated this order.');

        // A cancelled order is one experience rating; a delivered one rates the product, the seller and the delivery.
        $delivered = $order->status !== 'cancelled';
        $stars = 'integer|min:1|max:5';
        $data = $request->validate([
            'rating' => "required|$stars",
            'seller_rating' => $delivered ? "required|$stars" : 'prohibited',
            'delivery_rating' => $delivered ? "required|$stars" : 'prohibited',
            'feedback' => 'nullable|string|max:1000',
            'photos' => $delivered ? 'nullable|array|max:'.ProductReviewPhoto::MAX_PER_REVIEW : 'prohibited',
            'photos.*' => 'image|mimes:jpg,jpeg,png,webp|max:5120',
        ], [
            'photos.max' => 'Add up to '.ProductReviewPhoto::MAX_PER_REVIEW.' photos.',
            'photos.*.image' => 'Photos must be JPG, PNG or WEBP images.',
            'photos.*.mimes' => 'Photos must be JPG, PNG or WEBP images.',
            'photos.*.max' => 'Each photo must be 5 MB or smaller.',
            'rating.required' => 'Rate the product.',
            'seller_rating.required' => 'Rate the seller’s service.',
            'delivery_rating.required' => 'Rate the delivery.',
        ]);

        if ($delivered) {
            $ratings->rate($order, $data['rating'], $data['seller_rating'], $data['delivery_rating'], $data['feedback'] ?? null, $request->file('photos', []));
        } else {
            $order->update(['rating' => $data['rating'], 'feedback' => $data['feedback'] ?? null]);
        }

        return back()->with('success', 'Feedback submitted. Thank you!');
    }

    /** Puts a completed order's product (and the same option) back in the cart, as the app's reorder does. */
    public function buyAgain(Order $order)
    {
        abort_if($order->buyer_id !== $this->buyer()->id, 403);
        abort_unless($order->status === 'completed', 404);

        $product = $order->product()->with('seller', 'variations')->first();
        if (! $product || ! $product->isPurchasable() || $product->stock <= 0) {
            return back()->withErrors(['order' => "{$order->product_name} is no longer available."]);
        }
        $variation = $product->variations->firstWhere('id', $order->variation_id);
        if ($product->variations->isNotEmpty() && ! $variation) {
            return redirect()->route('buyer.product', $product)->withErrors(['variation_id' => 'Choose an option for this product.']);
        }
        $stock = $variation ? min($variation->stock, $product->stock) : $product->stock;
        if ($stock <= 0) {
            return back()->withErrors(['order' => "{$order->product_name} ({$variation->label}) is out of stock."]);
        }

        $item = CartItem::firstOrNew(['cart_id' => $this->getCart()->id, 'product_id' => $product->id, 'variation_id' => $variation?->id]);
        $item->quantity = min((int) $item->quantity + (int) $order->quantity, $stock);
        $item->save();

        return redirect()->route('buyer.cart')->with('success', "{$product->name} is back in your cart.");
    }

    /** Mark a review helpful, or take the mark back. Buyers cannot vote on their own review. */
    public function toggleHelpful(Request $request, ProductReview $review)
    {
        abort_if($review->buyer_id === $this->buyer()->id, 422, 'You cannot vote on your own review.');
        $vote = ProductReviewVote::where(['product_review_id' => $review->id, 'user_id' => $this->buyer()->id])->first();
        $vote ? $vote->delete() : ProductReviewVote::create(['product_review_id' => $review->id, 'user_id' => $this->buyer()->id]);

        return $request->expectsJson()
            ? response()->json(['helpful' => ! $vote, 'count' => $review->votes()->count()])
            : redirect()->to(route('buyer.product', $review->product_id).'#review-'.$review->id);
    }

    /** Report a review to PickSell's moderators, once per buyer. */
    public function reportReview(Request $request, ProductReview $review)
    {
        abort_if($review->buyer_id === $this->buyer()->id, 422, 'You cannot report your own review.');
        ReviewReports::file($request, $review);

        return redirect()->to(route('buyer.product', $review->product_id).'#review-'.$review->id)->with('success', 'Thanks. Our team will look at this review.');
    }

    public function submitProductReview(Request $request, Product $product)
    {
        $data = $request->validate([
            'rating' => 'required|integer|min:1|max:5',
            'body' => 'nullable|string|max:1000',
        ]);
        $order = Order::where('buyer_id', $this->buyer()->id)
            ->where('product_id', $product->id)
            ->where('status', 'completed')
            ->latest()
            ->firstOrFail();
        ProductReview::updateOrCreate(
            ['product_id' => $product->id, 'buyer_id' => $this->buyer()->id, 'order_id' => $order->id],
            ['rating' => $data['rating'], 'body' => $data['body'] ?? null]
        );

        return back()->with('success', 'Your product review has been saved.');
    }

    // Chat
    public function chat(Request $request)
    {
        $buyer = $this->buyer();

        // Show only sellers the buyer has messaged or ordered from.
        $chattedUserIds = Message::where(function ($q) use ($buyer) {
            $q->where('sender_id', $buyer->id)->orWhere('receiver_id', $buyer->id);
        })->get()->map(fn ($m) => $m->sender_id === $buyer->id ? $m->receiver_id : $m->sender_id)
            ->unique()->values()->toBase();

        $orderedSellerIds = Order::where('buyer_id', $buyer->id)
            ->pluck('seller_id');

        $contactIds = $chattedUserIds->merge($orderedSellerIds)->unique()->values();

        $contacts = User::whereIn('id', $contactIds)
            ->where('role', 'seller')->where('status', 'approved')->get();

        // Include admin only if buyer has already messaged them
        $admin = User::where('role', 'admin')->first();
        if ($admin && $chattedUserIds->contains($admin->id)) {
            $contacts->prepend($admin);
        }

        $activeUserId = $request->get('user');
        // If coming from product page with seller_id, auto-open that seller
        if (! $activeUserId && $request->get('seller')) {
            $activeUserId = $request->get('seller');
        }
        $activeUser = $activeUserId
            ? User::whereKey($activeUserId)
                ->where(function ($query) {
                    $query->where('role', 'admin')
                        ->orWhere(fn ($seller) => $seller->where('role', 'seller')->where('status', 'approved'));
                })
                ->first()
            : null;
        $messages = collect();
        $product = null;

        if ($activeUser) {
            Message::where('sender_id', $activeUserId)->where('receiver_id', $buyer->id)->update(['read' => true]);
            $messages = Message::where(function ($q) use ($buyer, $activeUserId) {
                $q->where('sender_id', $buyer->id)->where('receiver_id', $activeUserId);
            })->orWhere(function ($q) use ($buyer, $activeUserId) {
                $q->where('sender_id', $activeUserId)->where('receiver_id', $buyer->id);
            })->with('product')->orderBy('created_at')->get();

            // If seller not yet in contacts, add them
            if ($activeUser->role === 'seller' && ! $contacts->contains('id', $activeUser->id)) {
                $contacts->push($activeUser);
            }
        }

        // Product context: from the URL first, else the latest message if it was about a product. Once the buyer
        // clears "Asking about" and sends a plain message, the context stays cleared.
        if ($request->get('product')) {
            $product = Product::find($request->get('product'));
        } elseif ($messages->isNotEmpty()) {
            $product = $messages->last()->product;
        }

        $contacts = $contacts->map(function ($u) use ($buyer) {
            $u->unread = Message::where('sender_id', $u->id)->where('receiver_id', $buyer->id)->where('read', false)->count();

            return $u;
        });

        $cartCount = CartItem::whereHas('cart', fn ($q) => $q->where('buyer_id', $buyer->id))->count();

        return view('buyer.chat', compact('contacts', 'activeUser', 'messages', 'cartCount', 'product'));
    }

    public function sendMessage(Request $request, MessagingService $messaging)
    {
        $data = $request->validate([
            'receiver_id' => 'required|exists:users,id',
            'body' => 'required|string|max:2000',
            'product_id' => 'nullable|exists:products,id',
        ]);
        $receiver = User::whereKey($data['receiver_id'])
            ->where(function ($query) {
                $query->where('role', 'admin')
                    ->orWhere(fn ($seller) => $seller->where('role', 'seller')->where('status', 'approved'));
            })
            ->firstOrFail();
        abort_if($receiver->id === auth()->id(), 422, 'You cannot message your own account.');
        $msg = Message::create([
            'sender_id' => auth()->id(),
            'receiver_id' => $receiver->id,
            'body' => $data['body'],
            'product_id' => $data['product_id'] ?? null,
            'read' => false,
        ]);
        $this->createBuyerNotification(
            $receiver,
            'You have a new message from '.auth()->user()->full_name.'.',
        );
        $msg->load('sender', 'receiver', 'product');
        try {
            Mail::to($msg->receiver->email)->send(new NewMessageMail($msg));
        } catch (Throwable $exception) {
            Log::warning('Chat message email notification failed.', ['message_id' => $msg->id, 'error' => $exception->getMessage()]);
        }
        $messaging->sellerAutoReply(auth()->user(), $receiver);
        $redirect = '/buyer/chat?user='.$receiver->id;
        if (! empty($data['product_id'])) {
            $redirect .= '&product='.$data['product_id'];
        }

        return redirect($redirect);
    }

    public function notifications()
    {
        $notifications = auth()->user()->notifications()->latest()->take(20)->get()->map(function ($notification) {
            $payload = is_string($notification->data) ? json_decode($notification->data, true) : $notification->data;

            return [
                'id' => $notification->id,
                'type' => $notification->type,
                'created_at' => $notification->created_at,
                'updated_at' => $notification->updated_at,
                'read_at' => $notification->read_at,
                'order_id' => $payload['order_id'] ?? null,
                'order_number' => $payload['order_number'] ?? null,
                'title' => $payload['title'] ?? null,
                'message' => $payload['message'] ?? 'New notification',
                'status' => $payload['status'] ?? null,
                'url' => $payload['url'] ?? null,
            ];
        });

        $unreadCount = auth()->user()->unreadNotifications()->count();

        return response()->json($notifications)->header('X-Unread-Count', $unreadCount);
    }

    public function markNotificationsRead()
    {
        auth()->user()->unreadNotifications->markAsRead();

        return response()->json(['success' => true]);
    }

    // Account
    public function account()
    {
        $cartCount = CartItem::whereHas('cart', fn ($q) => $q->where('buyer_id', $this->buyer()->id))->count();

        return view('buyer.account', compact('cartCount'));
    }

    public function updateAccount(Request $request)
    {
        $request->validate([
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'middle_initial' => 'nullable|string|max:5',
            'sex' => 'required|in:Male,Female',
            'email' => 'required|email|unique:users,email,'.auth()->id(),
            'contact_no' => ['required', 'regex:/^09\d{9}$/'],
            'birthday' => 'required|date|before:-18 years',
            'province' => 'required|string|max:100',
            'municipality' => 'required|string|max:100',
            'barangay' => 'required|string|max:100',
            'street' => 'nullable|string|max:255',
            'house_no' => 'nullable|string|max:100',
        ]);

        auth()->user()->update($request->only([
            'first_name',
            'last_name',
            'middle_initial',
            'sex',
            'email',
            'contact_no',
            'birthday',
            'province',
            'municipality',
            'barangay',
            'street',
            'house_no',
        ]));

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Account updated successfully.',
                'user' => auth()->user()->fresh(),
            ]);
        }

        return back()->with('success', 'Account updated successfully.');
    }

    /** The door Pickrush riders navigate to for this buyer's deliveries. */
    public function updateDeliveryLocation(Request $request)
    {
        $data = $request->validate([
            'lat' => 'required|numeric|between:4,22',      // the Philippines
            'lng' => 'required|numeric|between:116,127',
        ], ['lat.required' => 'Drop a pin on the map first.', 'lat.between' => 'Pin a spot inside the Philippines.', 'lng.between' => 'Pin a spot inside the Philippines.']);
        $buyer = $this->buyer();
        $buyer->update(['delivery_lat' => $data['lat'], 'delivery_lng' => $data['lng']]);
        PicksellBridge::buyerPinned($buyer);

        return back()->with('success', 'Delivery pin saved. Pickrush riders will bring your orders to this spot.');
    }

    public function updatePassword(Request $request)
    {
        $request->validate(['current_password' => 'required', 'password' => 'required|min:8|confirmed']);
        if (! Hash::check($request->current_password, auth()->user()->password)) {
            return back()->withErrors(['current_password' => 'Current password is incorrect.']);
        }
        auth()->user()->update(['password' => Hash::make($request->password)]);

        return back()->with('success', 'Password changed successfully.');
    }
}
