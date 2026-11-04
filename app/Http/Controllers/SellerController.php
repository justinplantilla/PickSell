<?php

namespace App\Http\Controllers;

use App\Mail\NewMessageMail;
use App\Models\Message;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\ReturnRequest;
use App\Models\User;
use App\Notifications\SellerDeliveryReceived;
use App\Pickrush\Bridge\PicksellBridge;
use App\Services\CommissionService;
use App\Services\Finance\FinancialLedgerService;
use App\Services\Orders\OrderLifecycleService;
use App\Services\Orders\OrderCancellationService;
use App\Services\ProductGallery;
use App\Services\Reports\FinancialReportService;
use App\Services\SellerVacations;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

class SellerController extends Controller
{
    private const PENDING_ORDER_STATUSES = [
        'pending',
        'placed',
        'confirmed',
        'preparing',
        'processing',
        'ready_for_pickup',
        'picked_up',
        'at_sorting_center',
        'sorted',
        'in_transit',
        'assigned_to_rider',
        'out_for_delivery',
        'shipped',
    ];

    private const ORDER_STATUS_GROUPS = [
        'processing' => [
            'label' => 'Processing',
            'tone' => 'processing',
            'description' => 'Placed through ready for pickup',
            'statuses' => ['pending', 'placed', 'confirmed', 'preparing', 'processing', 'ready_for_pickup'],
        ],
        'shipped' => [
            'label' => 'Shipped',
            'tone' => 'shipped',
            'description' => 'Picked up, transferred between province hubs, and out for delivery',
            'statuses' => ['picked_up', 'at_sorting_center', 'sorted', 'in_transit', 'assigned_to_rider', 'out_for_delivery', 'shipped'],
        ],
        'delivered_or_completed' => [
            'label' => 'Delivered',
            'tone' => 'delivered',
            'description' => 'Delivered or completed',
            'statuses' => ['delivered', 'completed'],
        ],
        'cancelled' => [
            'label' => 'Cancelled / returned',
            'tone' => 'cancelled',
            'description' => 'Cancelled, delivery failed, or returned',
            'statuses' => ['cancelled', 'delivery_failed', 'returned'],
        ],
    ];

    /** Status filter on the orders page. "Sorted" is a sorting-center step to the seller, so it files under it. */
    public const ORDER_FILTERS = [
        'placed' => ['label' => 'Placed', 'statuses' => ['pending', 'placed', 'confirmed']],
        'preparing' => ['label' => 'Preparing', 'statuses' => ['preparing', 'processing']],
        'ready_for_pickup' => ['label' => 'Ready for pickup', 'statuses' => ['ready_for_pickup']],
        'picked_up' => ['label' => 'Picked up', 'statuses' => ['picked_up']],
        'at_sorting_center' => ['label' => 'At sorting center', 'statuses' => ['at_sorting_center', 'sorted', 'in_transit', 'shipped']],
        'out_for_delivery' => ['label' => 'Out for delivery', 'statuses' => ['assigned_to_rider', 'out_for_delivery']],
        'delivered' => ['label' => 'Delivered', 'statuses' => ['delivered']],
        'completed' => ['label' => 'Completed', 'statuses' => ['completed']],
        'cancelled' => ['label' => 'Cancelled / returned', 'statuses' => ['cancelled', 'delivery_failed', 'returned']],
    ];

    private function seller()
    {
        return auth()->user();
    }

    // Dashboard
    public function dashboard(Request $request)
    {
        $seller = $this->seller();
        $storeName = $seller->business_name ?: trim(($seller->first_name ?? '').' '.($seller->last_name ?? '')) ?: 'Your Store';
        $range = in_array($request->query('range', '30D'), ['7D', '30D', '6M'], true) ? $request->query('range', '30D') : '30D';
        $metric = in_array($request->query('metric', 'sales'), ['sales', 'orders'], true) ? $request->query('metric', 'sales') : 'sales';

        $totalOrders = Order::where('seller_id', $seller->id)->count();
        $pendingOrders = Order::where('seller_id', $seller->id)
            ->whereIn('status', self::PENDING_ORDER_STATUSES)
            ->count();
        $completedOrders = Order::where('seller_id', $seller->id)->where('status', 'completed')->count();
        $totalSales = Order::where('seller_id', $seller->id)->where('status', 'completed')->sum('amount');
        $activeProducts = Product::where('seller_id', $seller->id)->where('status', 'active')->count();
        $lowStockCount = Product::where('seller_id', $seller->id)->lowStock()->count();

        $actionRequiredOrders = Order::where('seller_id', $seller->id)
            ->whereIn('status', ['placed', 'confirmed', 'preparing', 'ready_for_pickup'])
            ->count();
        $actionRequiredReturns = Schema::hasTable('return_requests')
            ? ReturnRequest::where('seller_id', $seller->id)->where('status', 'requested')->count()
            : 0;
        $lowStockProducts = Product::where('seller_id', $seller->id)
            ->lowStock()
            ->orderBy('stock', 'asc')
            ->limit(5)
            ->get();

        $storeStatus = match ($seller->status) {
            'approved' => $seller->isOnVacation() ? ['label' => 'On Vacation', 'tone' => 'attention'] : ($actionRequiredOrders > 0 || $actionRequiredReturns > 0 || $lowStockCount > 0
                ? ['label' => 'Needs Attention', 'tone' => 'attention']
                : ['label' => 'Active', 'tone' => 'active']),
            'suspended' => ['label' => 'Suspended', 'tone' => 'suspended'],
            default => null,
        };

        $currentPeriodOrders = $this->orderCountInRange($seller->id, $range);
        $previousPeriodOrders = $this->orderCountInRange($seller->id, $range, true);
        $currentPeriodSales = $this->salesInRange($seller->id, $range);
        $previousPeriodSales = $this->salesInRange($seller->id, $range, true);

        $salesDelta = $this->calculateDelta($currentPeriodSales, $previousPeriodSales);
        $ordersDelta = $this->calculateDelta($currentPeriodOrders, $previousPeriodOrders);

        $chartData = $this->chartData($seller->id, $range, $metric);
        $orderStatusCounts = Order::where('seller_id', $seller->id)
            ->selectRaw('status, COUNT(*) as status_count')
            ->groupBy('status')
            ->pluck('status_count', 'status')
            ->all();
        $totalStatusOrders = array_sum($orderStatusCounts);
        $orderBreakdown = [];
        foreach (self::ORDER_STATUS_GROUPS as $filter => $group) {
            $count = array_sum(array_map(
                fn (string $status): int => (int) ($orderStatusCounts[$status] ?? 0),
                $group['statuses'],
            ));
            $orderBreakdown[] = [
                'filter' => $filter,
                'label' => $group['label'],
                'tone' => $group['tone'],
                'description' => $group['description'],
                'count' => $count,
                'percentage' => $totalStatusOrders > 0 ? round(($count / $totalStatusOrders) * 100, 1) : 0,
            ];
        }

        $stats = [
            'total_orders' => $totalOrders,
            'pending_orders' => $pendingOrders,
            'completed_orders' => $completedOrders,
            'total_sales' => (float) $totalSales,
            'total_products' => $activeProducts,
            'low_stock' => $lowStockCount,
        ];

        $recentOrders = Order::where('seller_id', $seller->id)->with('buyer')->latest()->take(5)->get();
        $currentDate = now();
        $greeting = $currentDate->hour < 12 ? 'Good morning' : ($currentDate->hour < 18 ? 'Good afternoon' : 'Good evening');

        return view('seller.dashboard', compact(
            'seller',
            'storeName',
            'greeting',
            'currentDate',
            'storeStatus',
            'stats',
            'range',
            'metric',
            'chartData',
            'recentOrders',
            'lowStockProducts',
            'orderBreakdown',
            'salesDelta',
            'ordersDelta',
            'actionRequiredOrders',
            'actionRequiredReturns',
            'lowStockCount',
            'totalSales',
            'totalOrders',
            'completedOrders',
            'activeProducts',
            'pendingOrders',
        ));
    }

    private function orderCountInRange(int $sellerId, string $range, bool $previous = false): int
    {
        [$start, $end] = $this->dateWindow($range, $previous);

        return Order::where('seller_id', $sellerId)
            ->whereBetween('created_at', [$start, $end])
            ->count();
    }

    private function salesInRange(int $sellerId, string $range, bool $previous = false): float
    {
        [$start, $end] = $this->dateWindow($range, $previous);

        return (float) Order::where('seller_id', $sellerId)
            ->where('status', 'completed')
            ->whereBetween('created_at', [$start, $end])
            ->sum('amount');
    }

    private function dateWindow(string $range, bool $previous = false): array
    {
        if ($range === '7D') {
            $days = 7;
        } elseif ($range === '6M') {
            $days = 180;
        } else {
            $days = 30;
        }

        if ($previous) {
            $end = now()->subDays($days)->startOfDay();
            $start = now()->subDays($days * 2)->startOfDay();

            return [$start, $end];
        }

        return [now()->subDays($days - 1)->startOfDay(), now()->endOfDay()];
    }

    private function calculateDelta(float $current, float $previous): float
    {
        if ($previous <= 0) {
            return $current > 0 ? 100.0 : 0.0;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }

    private function chartData(int $sellerId, string $range, string $metric): array
    {
        if ($range === '7D') {
            $labels = [];
            $values = [];
            for ($i = 6; $i >= 0; $i--) {
                $date = now()->subDays($i);
                $labels[] = $date->format('D');
                $query = Order::where('seller_id', $sellerId)
                    ->whereDate('created_at', $date->toDateString());
                if ($metric === 'sales') {
                    $query->where('status', 'completed');
                    $values[] = (float) $query->sum('amount');
                } else {
                    $values[] = $query->count();
                }
            }

            return ['labels' => $labels, 'values' => $values];
        }

        if ($range === '6M') {
            $labels = [];
            $values = [];
            for ($i = 5; $i >= 0; $i--) {
                $date = now()->subMonths($i);
                $labels[] = $date->format('M');
                $query = Order::where('seller_id', $sellerId)
                    ->whereYear('created_at', $date->year)
                    ->whereMonth('created_at', $date->month);
                if ($metric === 'sales') {
                    $query->where('status', 'completed');
                    $values[] = (float) $query->sum('amount');
                } else {
                    $values[] = $query->count();
                }
            }

            return ['labels' => $labels, 'values' => $values];
        }

        $labels = [];
        $values = [];
        for ($i = 29; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $labels[] = $date->format('d');
            $query = Order::where('seller_id', $sellerId)
                ->whereDate('created_at', $date->toDateString());
            if ($metric === 'sales') {
                $query->where('status', 'completed');
                $values[] = (float) $query->sum('amount');
            } else {
                $values[] = $query->count();
            }
        }

        return ['labels' => $labels, 'values' => $values];
    }

    // Inventory
    public function inventory(Request $request)
    {
        $status = $request->get('status', 'active');
        $stockFilter = $request->query('filter') === 'low-stock' || $request->query('stock') === 'low' ? 'low' : 'all';
        $search = $request->get('search');
        $sort = $request->query('sort', 'newest');
        $sortOptions = [
            'newest' => ['created_at', 'desc'],
            'oldest' => ['created_at', 'asc'],
            'name_asc' => ['name', 'asc'],
            'name_desc' => ['name', 'desc'],
            'price_asc' => ['price', 'asc'],
            'price_desc' => ['price', 'desc'],
            'stock_asc' => ['stock', 'asc'],
            'stock_desc' => ['stock', 'desc'],
        ];
        if (! array_key_exists($sort, $sortOptions)) {
            $sort = 'newest';
        }
        $query = Product::where('seller_id', $this->seller()->id)->with('latestStatusModeration');
        if ($stockFilter === 'low') {
            $status = 'active';
            $query->lowStock();
        } elseif ($status !== 'all') {
            $query->where('status', $status);
        }
        if ($search) {
            $query->whereLike('name', "%$search%");
        }
        [$sortColumn, $sortDirection] = $sortOptions[$sort];
        $products = $query->orderBy($sortColumn, $sortDirection)
            ->orderBy('id', $sortDirection)
            ->paginate(15);

        // Without a province that matches a region, the seller's parcels get the generic (Metro Manila-origin) price.
        $shippingRegionMissing = ! PicksellBridge::hasShippingRegion($this->seller());

        return view('seller.inventory', compact('products', 'status', 'stockFilter', 'search', 'sort', 'shippingRegionMissing'));
    }

    private const PRODUCT_RULES = [
        'name' => 'required|string|max:255',
        'description' => 'nullable|string',
        'category' => 'nullable|string|max:100',
        'price' => 'required|numeric|min:0',
        'discount' => 'nullable|numeric|min:0|max:100',
        'voucher_code' => 'nullable|string|max:50',
        'voucher_discount' => 'nullable|numeric|min:0|max:100',
        'stock' => 'required|integer|min:0',
        // Shipping is priced on the packed weight of one unit.
        'weight_kg' => 'required|numeric|min:0.01|max:1000',
        'gallery' => 'nullable|array',
        'gallery.*' => 'string|max:64',
        'gallery_cover' => 'nullable|string|max:64',
        'gallery_managed' => 'nullable|boolean',
        'gallery_alt' => 'nullable|array',
        'gallery_alt.*' => 'nullable|string|max:255',
    ];

    public function storeProduct(Request $request, ProductGallery $gallery)
    {
        $data = $request->validate(self::PRODUCT_RULES);
        $data['seller_id'] = $this->seller()->id;

        $managed = $request->boolean('gallery_managed');
        DB::transaction(function () use ($data, $gallery, $managed) {
            $product = Product::create(collect($data)->except(['gallery', 'gallery_cover', 'gallery_managed', 'gallery_alt'])->all());
            // Only the JS gallery control sets gallery_managed, so a no-JS submit never wipes images.
            if ($managed) {
                $gallery->sync($product, $data['gallery'] ?? [], $data['gallery_cover'] ?? null, $data['gallery_alt'] ?? []);
            }
        });

        return back()->with('success', 'Product added successfully.');
    }

    public function updateProduct(Request $request, Product $product, ProductGallery $gallery)
    {
        abort_if($product->seller_id !== $this->seller()->id, 403);

        $data = $request->validate(self::PRODUCT_RULES);

        $managed = $request->boolean('gallery_managed');
        DB::transaction(function () use ($product, $data, $gallery, $managed) {
            $product->update(collect($data)->except(['gallery', 'gallery_cover', 'gallery_managed', 'gallery_alt'])->all());
            // Only the JS gallery control sets gallery_managed, so a no-JS submit never wipes images.
            if ($managed) {
                $gallery->sync($product, $data['gallery'] ?? [], $data['gallery_cover'] ?? null, $data['gallery_alt'] ?? []);
            }
        });

        return back()->with('success', 'Product updated.');
    }

    public function archiveProduct(Product $product)
    {
        abort_if($product->seller_id !== $this->seller()->id, 403);
        if ($product->isUnderAdminHold()) {
            return back()->withErrors(['product' => 'This product was archived by PickSell Admin and can only be restored by an admin. Contact support if you believe this is a mistake.']);
        }
        $product->update(['status' => $product->status === 'archived' ? 'active' : 'archived']);

        return back()->with('success', 'Product status updated.');
    }

    // Orders
    public function orders(Request $request)
    {
        $status = $request->get('status', 'all');
        $query = Order::where('seller_id', $this->seller()->id)->with('buyer');
        // pending, action_required and the dashboard's status groups still come in from dashboard links.
        if (array_key_exists($status, self::ORDER_FILTERS)) {
            $query->whereIn('status', self::ORDER_FILTERS[$status]['statuses']);
        } elseif ($status === 'pending') {
            $query->whereIn('status', self::PENDING_ORDER_STATUSES);
        } elseif ($status === 'action_required') {
            $query->whereIn('status', ['placed', 'confirmed', 'preparing', 'ready_for_pickup']);
        } elseif (array_key_exists($status, self::ORDER_STATUS_GROUPS)) {
            $query->whereIn('status', self::ORDER_STATUS_GROUPS[$status]['statuses']);
        } elseif ($status !== 'all') {
            $query->where('status', $status);
        }
        $orders = $query->latest()->paginate(15);

        $filters = array_map(fn (array $filter) => $filter['label'], self::ORDER_FILTERS);
        $legacyFilters = ['pending' => 'Pending', 'action_required' => 'Needs processing', 'processing' => 'Processing', 'shipped' => 'Shipped', 'delivered_or_completed' => 'Delivered or completed'];
        if (isset($legacyFilters[$status])) {
            $filters = [$status => $legacyFilters[$status]] + $filters;
        }

        return view('seller.orders', compact('orders', 'status', 'filters'));
    }

    public function showOrder(Order $order)
    {
        abort_if($order->seller_id !== $this->seller()->id, 403);
        $order->load('buyer');
        $commissionService = app(CommissionService::class);
        $commissionRate = $commissionService->rateForOrder($order);
        $commission = $commissionService->commissionForOrder($order);
        $netEarnings = $commissionService->sellerNet($order->amount, $commission);

        return view('seller.order-detail', compact('order', 'commissionRate', 'commission', 'netEarnings'));
    }

    public function packOrder(Order $order, OrderLifecycleService $lifecycle)
    {
        $count = DB::transaction(function () use ($order, $lifecycle): int {
            $locked = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            abort_if($locked->seller_id !== $this->seller()->id, 403);
            abort_unless(in_array($locked->status, ['placed', 'confirmed', 'pending'], true), 422, 'This order is not ready to be prepared.');
            // Items sharing a parcel are packed together.
            $parcelOrders = $this->parcelOrders($locked)->filter(fn (Order $o) => in_array($o->status, ['placed', 'confirmed', 'pending'], true));
            foreach ($parcelOrders as $item) {
                $lifecycle->transition($item, 'preparing', $this->seller()->id, 'seller', null, [
                    'packed_at' => now(),
                    'tracking_status' => 'Seller is preparing the order',
                ]);
            }
            $order->setRawAttributes($locked->getAttributes(), true);

            return $parcelOrders->count();
        });

        return back()->with('success', $count > 1 ? "{$count} orders in this parcel marked as being prepared." : 'Order marked as being prepared.');
    }

    /**
     * The order and the seller's other items from the same checkout that travel in its parcel,
     * locked; cancelled items are no longer in it.
     *
     * @return \Illuminate\Support\Collection<int, Order>
     */
    private function parcelOrders(Order $locked)
    {
        if (! $locked->order_shipment_id) {
            return collect([$locked]);
        }

        return Order::query()->where('order_shipment_id', $locked->order_shipment_id)->where('status', '!=', 'cancelled')
            ->orderBy('id')->lockForUpdate()->get()
            ->map(fn (Order $o) => $o->id === $locked->id ? $locked : $o);
    }

    public function cancelOrder(Request $request, Order $order, OrderCancellationService $cancellations)
    {
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:1000']]);
        $cancellations->cancel($order, $this->seller(), 'seller', $data['reason'] ?? null);

        return back()->with('success', 'Order cancelled before pickup.');
    }

    /** Step 1: print the label. Pickrush is not alerted yet, so the seller can finish packing. */
    public function generateWaybill(Order $order, PicksellBridge $pickrush)
    {
        $parcel = DB::transaction(function () use ($order, $pickrush) {
            $locked = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            abort_if($locked->seller_id !== $this->seller()->id, 403);
            abort_unless(in_array($locked->status, ['preparing', 'processing'], true), 422, 'Prepare the order before generating its waybill.');
            $parcelOrders = $this->parcelOrders($locked);
            abort_if($parcelOrders->contains(fn (Order $o) => ! in_array($o->status, ['preparing', 'processing'], true)), 422,
                'Prepare every item that ships in this parcel ('.$parcelOrders->pluck('order_number')->join(', ').') before generating its waybill.');

            // Pickrush's tracking number is the waybill, shared by every item in the parcel.
            $parcel = $pickrush->book($locked);
            $parcelOrders->each(fn (Order $o) => $o->update(['waybill_number' => $parcel->tracking_no]));

            return $parcel;
        });

        return back()->with('success', 'Waybill '.$parcel->tracking_no.' generated. Print it and pack the order, then press Hand Over when it is ready for the rider.'
            .($parcel->needs_address_fix ? ' Pickrush could not match the pickup or delivery address to a station yet; its team will confirm the address before pickup.' : ''));
    }

    /** Step 2: the parcel is packed and labelled; Pickrush schedules the pickup. */
    public function handoverOrder(Order $order, OrderLifecycleService $lifecycle, PicksellBridge $pickrush)
    {
        $parcel = DB::transaction(function () use ($order, $lifecycle, $pickrush) {
            $locked = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            abort_if($locked->seller_id !== $this->seller()->id, 403);
            abort_unless(in_array($locked->status, ['preparing', 'processing'], true), 422, 'Prepare the order before handing it over.');
            abort_unless(PicksellBridge::hasParcel($locked), 422, 'Generate and print the waybill before handing the order over.');

            $parcel = $pickrush->requestPickup($locked);
            foreach ($this->parcelOrders($locked)->filter(fn (Order $o) => in_array($o->status, ['preparing', 'processing'], true)) as $item) {
                $lifecycle->transition($item, 'ready_for_pickup', $this->seller()->id, 'seller', null, [
                    'waybill_number' => $parcel->tracking_no,
                    'handed_over_at' => now(),
                    'tracking_status' => 'Pickup requested from seller',
                ]);
            }
            $order->setRawAttributes($locked->getAttributes(), true);

            return $parcel;
        });
        $waybillNumber = $parcel->tracking_no;

        $message = 'Order #'.$order->order_number.' has been handed over to Pickrush. Waybill: '.$waybillNumber.'.';
        $this->notifyUser($order->buyer, 'Order handed over', $message, $order);
        User::where('role', 'admin')->where('status', 'approved')->get()
            ->each(fn (User $admin) => $this->notifyUser($admin, 'Parcel handed over', $message, $order));

        return back()->with('success', 'Order handed over to Pickrush. A rider will pick up waybill '.$waybillNumber.'.');
    }

    private function notifyUser(?User $user, string $title, string $message, Order $order): void
    {
        $user?->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => 'App\\Notifications\\OrderWorkflowUpdate',
            'data' => json_encode([
                'type' => 'order',
                'icon' => 'package',
                'title' => $title,
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'waybill_number' => $order->waybill_number,
                'status' => $order->status,
                'message' => $message,
            ]),
        ]);
    }

    public function showWaybill(Order $order)
    {
        abort_if($order->seller_id !== $this->seller()->id, 403);

        // Handed over to Pickrush: the waybill is Pickrush's 4×6 label with barcode and QR code.
        if ($parcel = PicksellBridge::parcelFor($order)) {
            return app(\App\Pickrush\Services\Labels::class)->parcel($parcel);
        }

        $pdf = Pdf::loadView('seller.pdf.waybill', compact('order'))
            ->setPaper([0, 0, 226.77, 560], 'portrait');

        return $pdf->stream('waybill-'.$order->order_number.'.pdf');
    }

    public function confirmDelivery(Order $order, FinancialLedgerService $ledger, OrderLifecycleService $lifecycle)
    {
        DB::transaction(function () use ($order, $ledger, $lifecycle): void {
            $locked = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            abort_if($locked->seller_id !== $this->seller()->id, 403);
            abort_unless(in_array($locked->status, ['delivered', 'completed'], true), 422, 'Only delivered orders can be confirmed.');

            if ($locked->status === 'delivered') {
                $lifecycle->transition($locked, 'completed', $this->seller()->id, 'seller', null, [
                    'tracking_status' => 'Seller confirmed delivery',
                    'confirmed_by_seller_at' => now(),
                ]);
            } else {
                $locked->update([
                    'tracking_status' => 'Seller confirmed delivery',
                    'confirmed_by_seller_at' => now(),
                ]);
            }
            $ledger->postCompletedOrder($locked, $this->seller());
            // A rating the buyer left while the order was delivered becomes the product review now, as in the buyer app.
            if ($locked->rating && $locked->product_id) {
                ProductReview::firstOrCreate(
                    ['product_id' => $locked->product_id, 'buyer_id' => $locked->buyer_id, 'order_id' => $locked->id],
                    ['rating' => $locked->rating, 'body' => $locked->feedback],
                );
            }
            $order->setRawAttributes($locked->getAttributes(), true);
        });

        $this->seller()->notify(new SellerDeliveryReceived($order));

        return back()->with('success', 'Delivery confirmed.');
    }

    public function notifications()
    {
        $notifications = auth()->user()->notifications()->latest()->take(20)->get();
        $unreadCount = auth()->user()->unreadNotifications()->count();

        return response()->json($notifications)->header('X-Unread-Count', $unreadCount);
    }

    public function markNotificationsRead()
    {
        auth()->user()->unreadNotifications->markAsRead();

        return response()->json(['success' => true]);
    }

    // Earnings visibility
    public function earnings(Request $request)
    {
        return view('seller.earnings', $this->earningsData($request));
    }

    public function earningsCsv(Request $request)
    {
        $data = $this->earningsData($request);

        return response()->streamDownload(function () use ($data): void {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                throw new \RuntimeException('Unable to open the CSV output stream.');
            }

            try {
                if (fputcsv($handle, ['Order Number', 'Date', 'Gross Sales (PHP)', 'Commission Rate (%)', 'Commission (PHP)', 'Net Earnings (PHP)', 'Status']) === false) {
                    throw new \RuntimeException('Unable to write the CSV header.');
                }

                foreach ($data['orders'] as $order) {
                    $written = fputcsv($handle, [
                        $order['order_number'],
                        $order['created_at']->format('Y-m-d'),
                        number_format($order['amount'], 2, '.', ''),
                        number_format($order['commission_rate'], 2, '.', ''),
                        number_format($order['commission'], 2, '.', ''),
                        number_format($order['net_earnings'], 2, '.', ''),
                        $order['status'],
                    ]);
                    if ($written === false) {
                        throw new \RuntimeException('Unable to write a seller earnings CSV row.');
                    }
                }
            } finally {
                fclose($handle);
            }
        }, 'seller-earnings-'.$data['from'].'-to-'.$data['to'].'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function earningsPdf(Request $request)
    {
        $data = $this->earningsData($request);
        $pdf = Pdf::loadView('seller.pdf.earnings', $data)->setPaper('a4', 'portrait');

        return $pdf->download('seller-earnings-'.$data['from'].'-to-'.$data['to'].'.pdf');
    }

    private function earningsData(Request $request): array
    {
        $validated = $request->validate([
            'preset' => ['nullable', 'in:today,last_7_days,last_30_days,this_month,last_month,custom'],
            'from' => ['required_if:preset,custom', 'required_with:to', 'nullable', 'date_format:Y-m-d'],
            'to' => ['required_if:preset,custom', 'required_with:from', 'nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);

        $preset = $validated['preset'] ?? (isset($validated['from']) ? 'custom' : 'this_month');
        $today = now()->startOfDay();

        if ($preset === 'custom') {
            $start = Carbon::createFromFormat('Y-m-d', $validated['from'])->startOfDay();
            $end = Carbon::createFromFormat('Y-m-d', $validated['to'])->endOfDay();
        } else {
            [$start, $end] = match ($preset) {
                'today' => [$today->copy(), $today->copy()->endOfDay()],
                'last_7_days' => [$today->copy()->subDays(6), $today->copy()->endOfDay()],
                'last_30_days' => [$today->copy()->subDays(29), $today->copy()->endOfDay()],
                'last_month' => [$today->copy()->subMonthNoOverflow()->startOfMonth(), $today->copy()->subMonthNoOverflow()->endOfMonth()],
                default => [$today->copy()->startOfMonth(), $today->copy()->endOfDay()],
            };
        }

        $from = $start->toDateString();
        $to = $end->toDateString();
        $finance = app(FinancialReportService::class)->sellerPeriod($start, $end, $this->seller()->id);

        return [
            'from' => $from,
            'to' => $to,
            'preset' => $preset,
            'orders' => $finance['orders'],
            'totalOrders' => $finance['completed_orders'],
            'totalSales' => $finance['gross_sales'],
            'totalCommission' => $finance['commission'],
            'totalNetEarnings' => $finance['seller_net'],
            'averageCommissionRate' => $finance['average_commission_rate'],
        ];
    }

    // Reports
    public function reports(Request $request)
    {
        [$from, $to] = $this->reportDateRange($request);

        return view('seller.reports', $this->reportData($from, $to));
    }

    public function reportPdf(Request $request)
    {
        [$from, $to] = $this->reportDateRange($request);
        $data = $this->reportData($from, $to);

        $pdf = Pdf::loadView('seller.pdf.report', $data)->setPaper('a4', 'portrait');

        return $pdf->download('seller-report-'.$from.'-to-'.$to.'.pdf');
    }

    public function reportCsv(Request $request)
    {
        [$from, $to] = $this->reportDateRange($request);
        $data = $this->reportData($from, $to);

        return response()->streamDownload(function () use ($data): void {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                throw new \RuntimeException('Unable to open the CSV output stream.');
            }

            try {
                if (fputcsv($handle, ['Order ID', 'Order Number', 'Transaction Date', 'Sale Amount (Gross PHP)', 'Commission Rate (%)', 'Commission Deducted (PHP)', 'Net Earnings (PHP)', 'Order Status']) === false) {
                    throw new \RuntimeException('Unable to write the seller report CSV header.');
                }

                foreach ($data['financialOrders'] as $order) {
                    $written = fputcsv($handle, [
                        $order['id'],
                        $order['order_number'],
                        $order['created_at']->format('Y-m-d H:i:s'),
                        number_format($order['amount'], 2, '.', ''),
                        number_format($order['commission_rate'], 2, '.', ''),
                        number_format($order['commission'], 2, '.', ''),
                        number_format($order['net_earnings'], 2, '.', ''),
                        $order['status'],
                    ]);
                    if ($written === false) {
                        throw new \RuntimeException('Unable to write a seller report CSV row.');
                    }
                }
            } finally {
                fclose($handle);
            }
        }, 'seller-report-'.$from.'-to-'.$to.'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function reportDateRange(Request $request): array
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $from = $validated['from'] ?? now()->startOfMonth()->format('Y-m-d');
        $to = $validated['to'] ?? now()->format('Y-m-d');

        validator(compact('from', 'to'), [
            'to' => ['after_or_equal:from'],
        ])->validate();

        return [$from, $to];
    }

    private function reportData(string $from, string $to): array
    {
        $seller = $this->seller();
        $finance = app(FinancialReportService::class)->sellerPeriod(
            Carbon::parse($from)->startOfDay(),
            Carbon::parse($to)->endOfDay(),
            $seller->id,
        );
        $totalSales = $finance['gross_sales'];
        $totalOrders = $finance['completed_orders'];
        $totalCommission = $finance['commission'];
        $totalNetEarnings = $finance['seller_net'];
        $totalProfit = $totalNetEarnings;
        $financialOrders = $finance['financial_orders'];
        $days = $finance['days'];
        $dailySales = $finance['daily_sales'];
        $topProducts = $finance['top_products'];
        $commissionRate = $finance['average_commission_rate'];

        return compact(
            'from',
            'to',
            'totalSales',
            'totalOrders',
            'totalCommission',
            'totalNetEarnings',
            'totalProfit',
            'financialOrders',
            'days',
            'dailySales',
            'topProducts',
            'commissionRate',
        );
    }

    // Chat
    public function chat(Request $request)
    {
        $seller = $this->seller();

        // Show buyers connected to the seller, plus support so sellers can start a conversation.
        $chattedBuyerIds = Message::where(function ($q) use ($seller) {
            $q->where('sender_id', $seller->id)->orWhere('receiver_id', $seller->id);
        })->get()->map(fn ($m) => $m->sender_id === $seller->id ? $m->receiver_id : $m->sender_id)
            ->unique()->values();

        $orderedBuyerIds = Order::where('seller_id', $seller->id)
            ->pluck('buyer_id');

        $contactIds = $chattedBuyerIds->merge($orderedBuyerIds)->unique()->values();

        $users = User::whereIn('id', $contactIds)
            ->where('role', 'buyer')->where('status', 'approved')->get();

        $supportUsers = User::where('role', 'admin')->where('status', 'approved')->get();
        $users = $users->concat($supportUsers)->unique('id')->values();

        $activeUserId = $request->get('user');
        $activeUser = is_string($activeUserId) && ctype_digit($activeUserId)
            ? $users->firstWhere('id', (int) $activeUserId)
            : null;
        $messages = collect();

        if ($activeUser) {
            Message::where('sender_id', $activeUserId)->where('receiver_id', $seller->id)->update(['read' => true]);
            $messages = Message::where(function ($q) use ($seller, $activeUserId) {
                $q->where('sender_id', $seller->id)->where('receiver_id', $activeUserId);
            })->orWhere(function ($q) use ($seller, $activeUserId) {
                $q->where('sender_id', $activeUserId)->where('receiver_id', $seller->id);
            })->with('product')->orderBy('created_at')->get();
        }

        // Product context: from last message with product in this conversation
        $product = $messages->isNotEmpty() ? $messages->whereNotNull('product_id')->last()?->product : null;

        $users = $users->map(function ($u) use ($seller) {
            $u->unread = Message::where('sender_id', $u->id)->where('receiver_id', $seller->id)->where('read', false)->count();

            return $u;
        });

        return view('seller.chat', compact('users', 'activeUser', 'messages', 'product'));
    }

    public function sendMessage(Request $request)
    {
        $data = $request->validate([
            'receiver_id' => ['required', 'integer', 'exists:users,id'],
            'body' => ['required', 'string', 'max:2000'],
        ]);
        $seller = $this->seller();
        $receiver = User::query()->whereKey($data['receiver_id'])
            ->where('status', 'approved')
            ->where(function ($query) use ($seller): void {
                $query->where('role', 'admin')
                    ->orWhere(function ($buyer) use ($seller): void {
                        $buyer->where('role', 'buyer')->where(function ($contact) use ($seller): void {
                            $contact->whereHas('ordersAsBuyer', fn ($orders) => $orders->where('seller_id', $seller->id))
                                ->orWhereHas('sentMessages', fn ($messages) => $messages->where('receiver_id', $seller->id))
                                ->orWhereHas('receivedMessages', fn ($messages) => $messages->where('sender_id', $seller->id));
                        });
                    });
            })->firstOrFail();
        abort_if($receiver->is($seller), 422, 'You cannot message your own account.');

        $msg = Message::create([
            'sender_id' => $seller->id,
            'receiver_id' => $receiver->id,
            'body' => trim($data['body']),
            'read' => false,
        ]);
        $msg->load('sender', 'receiver', 'product');
        try {
            Mail::to($msg->receiver->email)->send(new NewMessageMail($msg));
        } catch (Throwable $exception) {
            Log::warning('Chat message email notification failed.', [
                'message_id' => $msg->id,
                'error' => $exception->getMessage(),
            ]);
        }

        return redirect()->route('seller.chat', ['user' => $receiver->id])->with('success', 'Message sent.');
    }

    // Account
    public function account(SellerVacations $vacations)
    {
        $seller = $this->seller();
        $current = $vacations->current($seller);

        return view('seller.account', [
            'vacationDaysLeft' => $vacations->daysLeft($seller, now()),
            'latestReturn' => $vacations->latestReturn($seller, $current?->starts_at ?? now(), $current),
        ]);
    }

    public function updateAccount(Request $request)
    {
        $request->validate([
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'middle_initial' => 'nullable|string|max:5',
            'sex' => 'required|in:Male,Female',
            'birthday' => 'required|date|before:-18 years',
            'email' => 'required|email|unique:users,email,'.auth()->id(),
            'contact_no' => 'required|string|max:20',
            'business_name' => 'nullable|string|max:255',
            'line_of_business' => 'nullable|string|max:255',
            'province' => 'required|string|max:120',
            'municipality' => 'required|string|max:120',
            'barangay' => 'required|string|max:120',
            'street' => 'nullable|string|max:255',
            'house_no' => 'nullable|string|max:100',
        ]);
        auth()->user()->update($request->only(
            'first_name', 'last_name', 'middle_initial', 'sex', 'birthday', 'email', 'contact_no',
            'business_name', 'line_of_business', 'province', 'municipality', 'barangay', 'street', 'house_no'
        ));

        return back()->with('success', 'Account updated successfully.');
    }

    /** The shop pin Pickrush riders check "Arrived" against (and navigate to). */
    public function updatePickupLocation(Request $request)
    {
        $data = $request->validate([
            'lat' => 'required|numeric|between:4,22',      // the Philippines
            'lng' => 'required|numeric|between:116,127',
        ], ['lat.required' => 'Drop a pin on the map first.', 'lat.between' => 'Pin a spot inside the Philippines.', 'lng.between' => 'Pin a spot inside the Philippines.']);
        $seller = auth()->user();
        $seller->update(['pickup_lat' => $data['lat'], 'pickup_lng' => $data['lng']]);
        \App\Pickrush\Bridge\PicksellBridge::sellerPinned($seller);

        return back()->with('success', 'Shop pin saved. Pickrush riders will come to this spot.');
    }

    /**
     * Vacation mode: products stay visible but cannot be ordered until the return date. Sellers get
     * User::MAX_VACATION_DAYS_PER_MONTH vacation days per calendar month (see SellerVacations).
     */
    public function updateVacation(Request $request, SellerVacations $vacations)
    {
        $seller = $this->seller();
        $current = $vacations->current($seller);

        if (! $request->boolean('vacation_mode')) {
            $request->validate(['vacation_mode' => 'required|boolean']);
            $vacations->end($seller);

            return back()->with('success', 'Welcome back! Your products are open for orders again.');
        }

        // Changing the date mid-vacation is measured from its original start, without counting it twice.
        $latest = $vacations->latestReturn($seller, $current?->starts_at ?? now(), $current);
        if (! $latest) {
            return back()->withErrors(['vacation_mode' => 'You have used all '.User::MAX_VACATION_DAYS_PER_MONTH.' vacation days for this month.']);
        }
        $data = $request->validate([
            'vacation_mode' => 'required|boolean',
            'vacation_until' => ['required', 'date', 'after:today', 'before_or_equal:'.$latest->toDateString()],
            'vacation_message' => 'nullable|string|max:255',
        ], [
            'vacation_until.required' => 'Pick the day you will be back.',
            'vacation_until.after' => 'Your return date must be after today.',
            'vacation_until.before_or_equal' => 'You get '.User::MAX_VACATION_DAYS_PER_MONTH.' vacation days a month. Pick a return date on or before '.$latest->format('M j, Y').'.',
        ]);
        $message = trim($data['vacation_message'] ?? '') ?: null;
        $endsAt = Carbon::parse($data['vacation_until'])->startOfDay();

        if ($current) {
            $vacations->change($seller, $current, $endsAt, $message);

            return back()->with('success', "Vacation updated: you're away until {$endsAt->format('M j, Y')}.");
        }

        $unshipped = Order::where('seller_id', $seller->id)->whereIn('status', User::SELLER_UNSHIPPED_STATUSES)->count();
        if ($unshipped > 0) {
            return back()->withErrors(['vacation_mode' => "Hand over or cancel your {$unshipped} unshipped ".Str::plural('order', $unshipped).' before going on vacation.']);
        }

        $vacations->start($seller, $endsAt, $message);

        return back()->with('success', "Vacation mode is on until {$endsAt->format('M j, Y')}. Buyers can still see your products but can't order until then.");
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
