<?php

namespace App\Http\Controllers;

use App\Mail\NewMessageMail;
use App\Notifications\SellerDeliveryReceived;
use App\Models\Message;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SellerController extends Controller
{
    private function seller() { return auth()->user(); }

    // Dashboard
    public function dashboard()
    {
        $seller = $this->seller();
        $stats = [
            'total_orders'     => Order::where('seller_id', $seller->id)->count(),
            'pending_orders'   => Order::where('seller_id', $seller->id)->where('status', 'placed')->count(),
            'completed_orders' => Order::where('seller_id', $seller->id)->where('status', 'completed')->count(),
            'total_sales'      => Order::where('seller_id', $seller->id)->where('status', 'completed')->sum('amount'),
            'total_products'   => Product::where('seller_id', $seller->id)->where('status', 'active')->count(),
            'low_stock'        => Product::where('seller_id', $seller->id)->where('status', 'active')->where('stock', '<=', 5)->count(),
        ];

        // Monthly sales for chart (last 6 months)
        $months = [];
        $sales  = [];
        for ($i = 5; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $months[] = $date->format('M Y');
            $sales[]  = Order::where('seller_id', $seller->id)
                ->where('status', 'completed')
                ->whereYear('created_at', $date->year)
                ->whereMonth('created_at', $date->month)
                ->sum('amount');
        }

        $recentOrders = Order::where('seller_id', $seller->id)->with('buyer')->latest()->take(5)->get();

        return view('seller.dashboard', compact('stats', 'months', 'sales', 'recentOrders'));
    }

    // Inventory
    public function inventory(Request $request)
    {
        $status = $request->get('status', 'active');
        $search = $request->get('search');
        $query  = Product::where('seller_id', $this->seller()->id);
        if ($status !== 'all') $query->where('status', $status);
        if ($search) $query->where('name', 'like', "%$search%");
        $products = $query->latest()->paginate(15);
        return view('seller.inventory', compact('products', 'status', 'search'));
    }

    public function storeProduct(Request $request)
    {
        $data = $request->validate([
            'name'             => 'required|string|max:255',
            'description'      => 'nullable|string',
            'category'         => 'nullable|string|max:100',
            'price'            => 'required|numeric|min:0',
            'discount'         => 'nullable|numeric|min:0|max:100',
            'voucher_code'     => 'nullable|string|max:50',
            'voucher_discount' => 'nullable|numeric|min:0|max:100',
            'stock'            => 'required|integer|min:0',
            'image'            => 'nullable|image|max:2048',
        ]);
        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('products', 'public');
        }

        $data['seller_id'] = $this->seller()->id;
        Product::create($data);

        return back()->with('success', 'Product added successfully.');
    }

    public function updateProduct(Request $request, Product $product)
    {
        abort_if($product->seller_id !== $this->seller()->id, 403);

        $data = $request->validate([
            'name'             => 'required|string|max:255',
            'description'      => 'nullable|string',
            'category'         => 'nullable|string|max:100',
            'price'            => 'required|numeric|min:0',
            'discount'         => 'nullable|numeric|min:0|max:100',
            'voucher_code'     => 'nullable|string|max:50',
            'voucher_discount' => 'nullable|numeric|min:0|max:100',
            'stock'            => 'required|integer|min:0',
            'image'            => 'nullable|image|max:2048',
        ]);
        if ($request->hasFile('image')) {
            if ($product->image) Storage::disk('public')->delete($product->image);
            $data['image'] = $request->file('image')->store('products', 'public');
        }

        $product->update($data);
        return back()->with('success', 'Product updated.');
    }

    public function archiveProduct(Product $product)
    {
        abort_if($product->seller_id !== $this->seller()->id, 403);
        $product->update(['status' => $product->status === 'archived' ? 'active' : 'archived']);
        return back()->with('success', 'Product status updated.');
    }

    // Orders
    public function orders(Request $request)
    {
        $status = $request->get('status', 'all');
        $query  = Order::where('seller_id', $this->seller()->id)->with('buyer');
        if ($status !== 'all') $query->where('status', $status);
        $orders = $query->latest()->paginate(15);
        return view('seller.orders', compact('orders', 'status'));
    }

    public function showOrder(Order $order)
    {
        abort_if($order->seller_id !== $this->seller()->id, 403);
        $order->load('buyer', 'courier');
        return view('seller.order-detail', compact('order'));
    }

    public function packOrder(Order $order)
    {
        abort_if($order->seller_id !== $this->seller()->id, 403);
        abort_unless(in_array($order->status, ['placed', 'confirmed', 'pending'], true), 422, 'This order is not ready to be prepared.');
        $order->update([
            'status' => 'preparing',
            'packed_at' => now(),
            'tracking_status' => 'Seller is preparing the order',
        ]);
        return back()->with('success', 'Order marked as being prepared.');
    }

    public function handoverOrder(Order $order)
    {
        abort_if($order->seller_id !== $this->seller()->id, 403);
        abort_unless(in_array($order->status, ['preparing', 'processing'], true), 422, 'Prepare the order before handing it over.');

        $waybillNumber = $order->waybill_number ?: $this->generateWaybill($order);
        $order->update([
            'status'         => 'ready_for_pickup',
            'waybill_number' => $waybillNumber,
            'handed_over_at' => now(),
            'tracking_status' => 'Pickup requested from seller',
        ]);

        $message = 'Order #' . $order->order_number . ' has been handed over to logistics. Waybill: ' . $waybillNumber . '.';
        $this->notifyUser($order->buyer, 'Order handed over', $message, $order);
        $this->notifyUser($order->logistics, 'Parcel ready for pickup', $message, $order);
        User::where('role', 'admin')->where('status', 'approved')->get()
            ->each(fn (User $admin) => $this->notifyUser($admin, 'Parcel handed over', $message, $order));

        return back()->with('success', 'Order handed over. Waybill ' . $waybillNumber . ' was generated automatically.');
    }

    private function generateWaybill(Order $order): string
    {
        do {
            $waybill = 'WB-' . now()->format('ymd') . '-' . strtoupper(Str::random(8));
        } while (Order::where('waybill_number', $waybill)->exists());

        return $waybill;
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

        $pdf = Pdf::loadView('seller.pdf.waybill', compact('order'))
            ->setPaper([0, 0, 226.77, 560], 'portrait');

        return $pdf->stream('waybill-' . $order->order_number . '.pdf');
    }

    public function confirmDelivery(Order $order)
    {
        abort_if($order->seller_id !== $this->seller()->id, 403);
        abort_unless(in_array($order->status, ['delivered', 'completed'], true), 422, 'Only delivered orders can be confirmed.');

        $order->update([
            'status' => 'completed',
            'tracking_status' => 'Seller confirmed delivery',
            'confirmed_by_seller_at' => now(),
        ]);

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

    // Reports
    public function reports(Request $request)
    {
        $from   = $request->get('from', now()->startOfMonth()->format('Y-m-d'));
        $to     = $request->get('to', now()->format('Y-m-d'));

        return view('seller.reports', $this->reportData($from, $to));
    }

    public function reportPdf(Request $request)
    {
        $from = $request->get('from', now()->startOfMonth()->format('Y-m-d'));
        $to = $request->get('to', now()->format('Y-m-d'));
        $data = $this->reportData($from, $to);

        $pdf = Pdf::loadView('seller.pdf.report', $data)->setPaper('a4', 'portrait');
        return $pdf->download('seller-report-' . $from . '-to-' . $to . '.pdf');
    }

    private function reportData(string $from, string $to): array
    {
        $seller = $this->seller();

        $orders = Order::where('seller_id', $seller->id)
            ->where('status', 'completed')
            ->whereBetween('created_at', [$from, $to . ' 23:59:59'])
            ->get();

        $totalSales  = $orders->sum('amount');
        $totalOrders = $orders->count();
        $totalProfit = $orders->sum(fn($o) => $o->amount - $o->commission);

        // Daily breakdown
        $days   = [];
        $dailySales = [];
        $start  = \Carbon\Carbon::parse($from);
        $end    = \Carbon\Carbon::parse($to);
        while ($start->lte($end)) {
            $day = $start->format('Y-m-d');
            $days[] = $start->format('M d');
            $dailySales[] = $orders->filter(fn($o) => $o->created_at->format('Y-m-d') === $day)->sum('amount');
            $start->addDay();
        }

        // Top products
        $topProducts = $orders->groupBy('product_name')
            ->map(fn($g) => ['name' => $g->first()->product_name, 'sales' => $g->sum('amount'), 'count' => $g->count()])
            ->sortByDesc('sales')->take(5)->values();

        return compact('from', 'to', 'totalSales', 'totalOrders', 'totalProfit', 'days', 'dailySales', 'topProducts');
    }

    // Chat
    public function chat(Request $request)
    {
        $seller = $this->seller();

        // Show only buyers who have messaged the seller or placed an order.
        $chattedBuyerIds = Message::where(function ($q) use ($seller) {
            $q->where('sender_id', $seller->id)->orWhere('receiver_id', $seller->id);
        })->get()->map(fn($m) => $m->sender_id === $seller->id ? $m->receiver_id : $m->sender_id)
          ->unique()->values();

        $orderedBuyerIds = Order::where('seller_id', $seller->id)
            ->pluck('buyer_id');

        $contactIds = $chattedBuyerIds->merge($orderedBuyerIds)->unique()->values();

        $users = User::whereIn('id', $contactIds)
            ->where('role', 'buyer')->where('status', 'approved')->get();

        // Include admin only if seller has already messaged them
        $admin = User::where('role', 'admin')->first();
        if ($admin && $chattedBuyerIds->contains($admin->id)) {
            $users->prepend($admin);
        }

        $activeUserId = $request->get('user');
        $activeUser   = $activeUserId ? User::find($activeUserId) : null;
        $messages     = collect();

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
        $request->validate(['receiver_id' => 'required|exists:users,id', 'body' => 'required|string|max:2000']);
        $msg = Message::create(['sender_id' => auth()->id(), 'receiver_id' => $request->receiver_id, 'body' => $request->body, 'read' => false]);
        $msg->load('sender', 'receiver', 'product');
        \Illuminate\Support\Facades\Mail::to($msg->receiver->email)->send(new NewMessageMail($msg));
        return back();
    }

    // Account
    public function account()
    {
        return view('seller.account');
    }

    public function updateAccount(Request $request)
    {
        $request->validate([
            'first_name'    => 'required|string|max:100',
            'last_name'     => 'required|string|max:100',
            'middle_initial' => 'nullable|string|max:5',
            'sex'           => 'required|in:Male,Female',
            'birthday'      => 'required|date|before:-18 years',
            'email'         => 'required|email|unique:users,email,' . auth()->id(),
            'contact_no'    => 'required|string|max:20',
            'business_name' => 'nullable|string|max:255',
            'line_of_business' => 'nullable|string|max:255',
            'province'      => 'required|string|max:120',
            'municipality'  => 'required|string|max:120',
            'barangay'      => 'required|string|max:120',
            'street'        => 'nullable|string|max:255',
            'house_no'      => 'nullable|string|max:100',
        ]);
        auth()->user()->update($request->only(
            'first_name', 'last_name', 'middle_initial', 'sex', 'birthday', 'email', 'contact_no',
            'business_name', 'line_of_business', 'province', 'municipality', 'barangay', 'street', 'house_no'
        ));
        return back()->with('success', 'Account updated successfully.');
    }

    public function updatePassword(Request $request)
    {
        $request->validate(['current_password' => 'required', 'password' => 'required|min:8|confirmed']);
        if (!Hash::check($request->current_password, auth()->user()->password)) {
            return back()->withErrors(['current_password' => 'Current password is incorrect.']);
        }
        auth()->user()->update(['password' => Hash::make($request->password)]);
        return back()->with('success', 'Password changed successfully.');
    }
}
