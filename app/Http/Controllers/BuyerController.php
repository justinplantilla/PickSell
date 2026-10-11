<?php

namespace App\Http\Controllers;

use App\Mail\NewMessageMail;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Message;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\User;
use App\Notifications\NewSellerOrder;
use App\Services\CommissionService;
use App\Services\LogisticsRoutingService;
use App\Services\Orders\OrderCancellationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

        $query = Product::where('status', 'active')->where('stock', '>', 0)->with('seller');

        if ($search) {
            $query->where('name', 'like', "%$search%");
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
            $dealProducts = Product::where('status', 'active')
                ->where('stock', '>', 0)
                ->where('discount', '>', 0)
                ->with('seller')
                ->latest()
                ->take(14)
                ->get();
        }

        $recommendedProducts = Product::where('status', 'active')
            ->where('stock', '>', 0)
            ->with('seller')
            ->latest()
            ->take(8)
            ->get();
        $topCategoryProducts = Product::where('status', 'active')
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
            ->merge(Product::where('status', 'active')->whereNotNull('category')->distinct()->pluck('category'))
            ->filter()
            ->unique()
            ->sort()
            ->values();
        $cartCount = CartItem::whereHas('cart', fn ($q) => $q->where('buyer_id', $this->buyer()->id))->count();

        return view('buyer.home', compact('products', 'dealProducts', 'recommendedProducts', 'topCategoryProducts', 'categories', 'search', 'category', 'deals', 'cartCount'));
    }

    public function recommended(Request $request)
    {
        $page = max(1, (int) $request->get('page', 1));
        $products = Product::where('status', 'active')
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

    public function browse(Request $request)
    {
        $search = trim((string) $request->get('search', ''));
        $category = $request->get('category');
        $sort = $request->get('sort', 'latest');
        $dealsOnly = $request->boolean('deals');

        $query = Product::where('status', 'active')->where('stock', '>', 0)->with('seller');

        if ($search) {
            $query->where(function ($b) use ($search) {
                $b->where('name', 'like', "%{$search}%")
                  ->orWhere('category', 'like', "%{$search}%");
            });
        }
        if ($category) {
            $query->where('category', $category);
        }
        if ($dealsOnly) {
            $query->where('discount', '>', 0);
        }

        if ($sort === 'popular') {
            $query = $query->withSum(['orders as units_sold' => fn ($q) => $q->whereIn('status', ['delivered', 'completed'])], 'quantity')->orderByDesc('units_sold');
        } elseif ($sort === 'price_asc') {
            $query->orderBy('price');
        } elseif ($sort === 'price_desc') {
            $query->orderByDesc('price');
        } else {
            $query->latest();
        }

        $products = $query->paginate(30)->withQueryString();
        $categoryNames = ['Beauty', 'Books', 'Clothing', 'Electronics', 'Fashion', 'Food & Grocery', 'Home & Living', 'Pet Supplies', 'Sports', 'Toys'];
        $categories = collect($categoryNames)
            ->merge(Product::where('status', 'active')->whereNotNull('category')->distinct()->pluck('category'))
            ->filter()->unique()->sort()->values();
        $cartCount = CartItem::whereHas('cart', fn ($q) => $q->where('buyer_id', $this->buyer()->id))->count();

        return view('buyer.browse', compact('products', 'categories', 'search', 'category', 'sort', 'dealsOnly', 'cartCount'));
    }

    public function categories(Request $request)
    {
        $category = $request->query('category');
        $categories = Product::where('status', 'active')
            ->where('stock', '>', 0)
            ->whereNotNull('category')
            ->distinct()
            ->orderBy('category')
            ->pluck('category');
        $categoryCounts = Product::where('status', 'active')
            ->where('stock', '>', 0)
            ->selectRaw('category, COUNT(*) as product_count')
            ->whereNotNull('category')
            ->groupBy('category')
            ->pluck('product_count', 'category');
        $products = Product::where('status', 'active')
            ->where('stock', '>', 0)
            ->with('seller')
            ->when($category, fn ($query) => $query->where('category', $category))
            ->latest()
            ->paginate(12)
            ->withQueryString();
        $cartCount = CartItem::whereHas('cart', fn ($q) => $q->where('buyer_id', $this->buyer()->id))->count();

        return view('buyer.categories', compact('categories', 'categoryCounts', 'products', 'category', 'cartCount'));
    }

    // Search Results
    public function search(Request $request)
    {
        $query = trim($request->get('search', ''));
        $category = $request->get('category');

        $products = Product::where('status', 'active')
            ->where('stock', '>', 0)
            ->with('seller')
            ->when($query, fn ($q) => $q->where(function ($b) use ($query) {
                $b->where('name', 'like', "%{$query}%")
                  ->orWhere('category', 'like', "%{$query}%");
            }))
            ->when($category, fn ($q) => $q->where('category', $category))
            ->latest()
            ->paginate(16)
            ->withQueryString();

        $cartCount = CartItem::whereHas('cart', fn ($q) => $q->where('buyer_id', $this->buyer()->id))->count();

        return view('buyer.search', compact('products', 'query', 'category', 'cartCount'));
    }

    // Product Detail
    public function productDetail(Product $product)
    {
        abort_if($product->status !== 'active', 404);
        $product->load('seller', 'variations', 'reviews.buyer');
        $cartCount = CartItem::whereHas('cart', fn ($q) => $q->where('buyer_id', $this->buyer()->id))->count();
        $reviewCount = $product->reviews->count();
        $reviewAverage = $reviewCount ? round($product->reviews->avg('rating'), 1) : 0;

        return view('buyer.product-detail', compact('product', 'cartCount', 'reviewCount', 'reviewAverage'));
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

    // Add to Cart
    public function addToCart(Request $request, Product $product)
    {
        $data = $request->validate([
            'quantity' => 'required|integer|min:1',
            'variation_id' => 'nullable|exists:product_variations,id',
        ]);
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
        $items = CartItem::where('cart_id', $cart->id)->with('product.seller', 'variation')->latest()->paginate(20)->withQueryString();
        $cartCount = CartItem::where('cart_id', $cart->id)->count();
        $recommendations = Product::where('status', 'active')->where('stock', '>', 0)->with('seller')->latest()->take(4)->get();
        $recommendedProducts = Product::where('status', 'active')->where('stock', '>', 0)->with('seller')->latest()->take(8)->get();

        return view('buyer.cart', compact('items', 'cartCount', 'recommendations', 'recommendedProducts'));
    }

    public function checkout(Request $request)
    {
        $itemIds = $request->input('item_ids', []);
        if (empty($itemIds)) {
            return redirect()->route('buyer.cart');
        }

        $cart = $this->getCart();
        $items = CartItem::whereIn('id', $itemIds)
            ->where('cart_id', $cart->id)
            ->with('product.seller', 'variation')
            ->get();

        if ($items->isEmpty()) {
            return redirect()->route('buyer.cart');
        }

        $unavailable = $items->reject(fn ($item) => $item->product?->isPurchasable());
        if ($unavailable->isNotEmpty()) {
            return redirect()->route('buyer.cart')->withErrors(['item_ids' => $this->unavailableMessage($unavailable)]);
        }

        $total = $items->sum(fn ($i) => $i->product->effective_price * $i->quantity);
        $cartCount = CartItem::where('cart_id', $cart->id)->count();
        $logisticsProvider = User::where('role', 'logistics')
            ->where('status', 'approved')
            ->orderBy('business_name')
            ->orderBy('last_name')
            ->first();

        return view('buyer.checkout', compact('items', 'itemIds', 'total', 'cartCount', 'logisticsProvider'));
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

    // Place Order
    public function placeOrder(Request $request)
    {
        $data = $request->validate([
            'item_ids' => 'required|array',
            'item_ids.*' => 'exists:cart_items,id',
            'voucher_code' => 'nullable|string',
        ]);

        $logisticsProvider = User::where('role', 'logistics')
            ->where('status', 'approved')
            ->orderBy('business_name')
            ->orderBy('last_name')
            ->firstOrFail();

        $cart = $this->getCart();
        $items = CartItem::whereIn('id', $data['item_ids'])
            ->where('cart_id', $cart->id)
            ->with('product')
            ->get();

        if ($items->isEmpty()) {
            return back()->withErrors(['item_ids' => 'No items selected.']);
        }

        $routing = app(LogisticsRoutingService::class);

        try {
            $placed = DB::transaction(function () use ($items, $data, $logisticsProvider, $routing) {
                // Lock the products so moderation, other buyers and stock changes cannot interleave.
                $products = Product::whereIn('id', $items->pluck('product_id'))->with('seller')->lockForUpdate()->get()->keyBy('id');
                $unavailable = $items->filter(fn ($item) => ! $products->get($item->product_id)?->isPurchasable());
                if ($unavailable->isNotEmpty()) {
                    throw ValidationException::withMessages(['item_ids' => $this->unavailableMessage($unavailable)]);
                }
                // Several cart lines (e.g. variations) can draw on the same product's stock.
                $shortStock = $items->groupBy('product_id')
                    ->filter(fn ($lines, $productId) => $products[$productId]->stock < $lines->sum('quantity'));
                if ($shortStock->isNotEmpty()) {
                    throw ValidationException::withMessages(['item_ids' => 'Not enough stock for: '.$shortStock->keys()->map(fn ($productId) => $products[$productId]->name)->join(', ').'.']);
                }

                $placed = [];
                foreach ($items as $item) {
                    $item->setRelation('product', $products[$item->product_id]);
                    $placed[] = $this->createOrderFromCartItem($item, $data, $logisticsProvider, $routing);
                }

                return $placed;
            });
        } catch (ValidationException $exception) {
            return redirect()->route('buyer.cart')->withErrors($exception->errors());
        }

        foreach ($placed as $order) {
            $order->seller?->notify(new NewSellerOrder($order));
        }

        return redirect()->route('buyer.orders')->with('success', 'Order placed successfully!');
    }

    /** Builds one order from a cart line inside placeOrder's transaction. */
    private function createOrderFromCartItem(CartItem $item, array $data, User $logisticsProvider, LogisticsRoutingService $routing): Order
    {
        $product = $item->product;
        $price = $product->effective_price;

        // Apply voucher if valid
        if (! empty($data['voucher_code']) && $product->voucher_code === $data['voucher_code']) {
            $price = $price * (1 - $product->voucher_discount / 100);
        }

        $amount = $price * $item->quantity;
        $commissionCalculation = app(CommissionService::class)->calculate((string) round($amount, 2));

        $order = Order::create([
            'order_number' => 'ORD-'.strtoupper(Str::random(8)),
            'product_id' => $product->id,
            'buyer_id' => $this->buyer()->id,
            'seller_id' => $product->seller_id,
            'logistics_id' => $logisticsProvider->id,
            'product_name' => $product->name,
            'quantity' => $item->quantity,
            'amount' => $amount,
            'commission' => $commissionCalculation['commission'],
            'commission_rate' => $commissionCalculation['rate'],
            'status' => 'placed',
            'tracking_status' => 'Order placed and awaiting seller preparation',
        ]);
        $routing->routeOrder($order, $product->seller, $this->buyer());

        // Deduct stock
        $product->decrement('stock', $item->quantity);
        $item->delete();

        return $order;
    }

    private function unavailableMessage($items): string
    {
        return 'No longer available: '.$items->map(fn ($item) => $item->product?->name ?? 'a removed product')->join(', ')
            .'. Remove '.($items->count() === 1 ? 'it' : 'them').' from your cart to continue.';
    }

    // Orders
    public function orders(Request $request)
    {
        $status = $request->get('status', 'all');
        $query = Order::where('buyer_id', $this->buyer()->id)->with('product', 'seller', 'returnRequest', 'delivery');
        $statusGroups = [
            'pending' => ['pending', 'placed', 'confirmed'],
            'processing' => ['processing', 'preparing', 'ready_for_pickup', 'picked_up', 'at_sorting_center', 'sorted'],
            'shipped' => ['shipped', 'assigned_to_rider', 'out_for_delivery', 'delivery_failed'],
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
        $cartCount = CartItem::whereHas('cart', fn ($q) => $q->where('buyer_id', $this->buyer()->id))->count();

        return view('buyer.orders', compact('orders', 'status', 'cartCount'));
    }

    public function trackOrder(Order $order)
    {
        $order = Order::query()
            ->whereKey($order->id)
            ->where('buyer_id', $this->buyer()->id)
            ->with(['delivery.logs', 'returnRequest'])
            ->firstOrFail();

        return view('buyer.order-tracking', compact('order'));
    }

    public function cancelOrder(Request $request, Order $order, OrderCancellationService $cancellations)
    {
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:1000']]);
        $cancellations->cancel($order, $this->buyer(), 'buyer', $data['reason'] ?? null);

        return back()->with('success', 'Order cancelled before pickup.');
    }

    public function submitFeedback(Request $request, Order $order)
    {
        abort_if($order->buyer_id !== $this->buyer()->id, 403);
        $request->validate([
            'rating' => 'required|integer|min:1|max:5',
            'feedback' => 'nullable|string|max:1000',
        ]);
        $order->update(['rating' => $request->rating, 'feedback' => $request->feedback]);

        return back()->with('success', 'Feedback submitted. Thank you!');
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

        // Product context: from URL param first, fallback to last message with product
        if ($request->get('product')) {
            $product = Product::find($request->get('product'));
        } elseif ($messages->isNotEmpty()) {
            $product = $messages->whereNotNull('product_id')->last()?->product;
        }

        $contacts = $contacts->map(function ($u) use ($buyer) {
            $u->unread = Message::where('sender_id', $u->id)->where('receiver_id', $buyer->id)->where('read', false)->count();

            return $u;
        });

        $cartCount = CartItem::whereHas('cart', fn ($q) => $q->where('buyer_id', $buyer->id))->count();

        return view('buyer.chat', compact('contacts', 'activeUser', 'messages', 'cartCount', 'product'));
    }

    public function sendMessage(Request $request)
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
                'message' => $payload['message'] ?? 'New notification',
                'status' => $payload['status'] ?? null,
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
