<?php

namespace App\Http\Controllers;

use App\Models\Complaint;
use App\Models\Order;
use App\Models\ReturnRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/** Marketplace analytics (reports.view). Money totals live in Financial Reports. */
class AdminAnalyticsController extends Controller
{
    public function index(Request $request)
    {
        $date = fn ($value, $fallback) => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $value) ? $value : $fallback;
        $from = $date($request->query('from'), now()->subDays(29)->toDateString());
        $to = $date($request->query('to'), now()->toDateString());
        $range = [Carbon::parse($from)->startOfDay(), Carbon::parse($to)->endOfDay()];

        $orders = Order::whereBetween('created_at', $range)->with('product:id,category')->get(['id', 'status', 'amount', 'product_id', 'created_at', 'delivered_at']);
        $byStatus = $orders->countBy('status');

        $delivered = $orders->whereIn('status', ['delivered', 'completed']);
        $failed = $orders->whereIn('status', ['delivery_failed', 'returned']);
        $deliveryAttempts = $delivered->count() + $failed->count();
        $deliveryHours = $delivered->filter(fn ($order) => $order->delivered_at)
            ->map(fn ($order) => $order->created_at->diffInMinutes($order->delivered_at) / 60);

        $returns = ReturnRequest::whereBetween('created_at', $range)->count();
        $completed = $orders->where('status', 'completed');

        $categories = $completed->groupBy(fn ($order) => $order->product?->category ?: 'Uncategorized')
            ->map(fn ($group) => ['orders' => $group->count(), 'sales' => (float) $group->sum('amount')])
            ->sortByDesc('sales')->take(8);

        $lifecycle = collect(Order::STATUS_LIFECYCLE)->mapWithKeys(fn ($status) => [$status => (int) ($byStatus[$status] ?? 0)]);

        return view('admin.analytics.index', [
            'from' => $from,
            'to' => $to,
            'ordersPlaced' => $orders->count(),
            'completedOrders' => $completed->count(),
            'deliverySuccessRate' => $deliveryAttempts ? round($delivered->count() / $deliveryAttempts * 100, 1) : null,
            'avgDeliveryHours' => $deliveryHours->isNotEmpty() ? round($deliveryHours->avg(), 1) : null,
            'returnRate' => $completed->count() ? round($returns / $completed->count() * 100, 1) : null,
            'returnsOpened' => $returns,
            'complaintsOpened' => Complaint::whereBetween('created_at', $range)->count(),
            'newUsers' => User::whereBetween('created_at', $range)->whereIn('role', ['buyer', 'seller', 'courier', 'logistics'])
                ->selectRaw('role, count(*) as total')->groupBy('role')->pluck('total', 'role'),
            'lifecycle' => $lifecycle,
            'lifecycleMax' => max(1, $lifecycle->max()),
            'categories' => $categories,
            'categoryMax' => max(1, (float) $categories->max('sales')),
        ]);
    }
}
