<?php

namespace App\Http\Controllers;

use App\Http\Requests\Admin\AdminOrderStatusRequest;
use App\Http\Requests\Admin\ResolveOrderExceptionRequest;
use App\Models\AuditLog;
use App\Models\Complaint;
use App\Models\LogisticsBranch;
use App\Models\Order;
use App\Models\User;
use App\Services\Admin\AdminDashboardService;
use App\Services\Finance\FinancialSummary;
use App\Services\Orders\OrderAdministrationService;
use App\Services\Orders\OrderLifecycleService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/** Marketplace-wide order control surface (orders.view; overrides need their own permissions). */
class AdminOrderController extends Controller
{
    public const STAGES = [
        'fulfillment' => ['label' => 'Seller fulfillment', 'statuses' => ['placed', 'confirmed', 'preparing', 'ready_for_pickup']],
        'logistics'   => ['label' => 'In logistics', 'statuses' => ['picked_up', 'at_sorting_center', 'sorted', 'assigned_to_rider', 'out_for_delivery']],
        'delivered'   => ['label' => 'Delivered', 'statuses' => ['delivered', 'completed']],
        'exceptions'  => ['label' => 'Exceptions', 'statuses' => ['delivery_failed', 'returned', 'cancelled']],
    ];

    public const DATE_FILTERS = ['today' => 'Placed today', '7d' => 'Last 7 days', '30d' => 'Last 30 days', '90d' => 'Last 90 days'];

    public function index(Request $request, AdminDashboardService $dashboard)
    {
        $stage = array_key_exists($request->query('stage'), self::STAGES) ? $request->query('stage') : 'all';
        $search = trim((string) $request->query('search', ''));
        $statuses = array_values(array_intersect(explode(',', (string) $request->query('status', '')), Order::STATUS_LIFECYCLE));
        $date = array_key_exists($request->query('date'), self::DATE_FILTERS) ? $request->query('date') : ($request->query('placed') === 'today' ? 'today' : 'any');
        $ids = fn (string $key) => is_numeric($request->query($key)) ? (int) $request->query($key) : null;
        $filters = ['seller' => $ids('seller'), 'buyer' => $ids('buyer'), 'courier' => $ids('courier'), 'branch' => $ids('branch')];
        $stuck = $request->boolean('stuck');
        $issues = $request->boolean('issues');

        $query = Order::with(['buyer', 'seller', 'courier'])->latest('updated_at');
        if ($stage !== 'all') {
            $query->whereIn('status', self::STAGES[$stage]['statuses']);
        }
        if ($statuses) {
            $query->whereIn('status', $statuses);
        }
        foreach (['seller' => 'seller_id', 'buyer' => 'buyer_id', 'courier' => 'courier_id'] as $key => $column) {
            if ($filters[$key]) {
                $query->where($column, $filters[$key]);
            }
        }
        if ($filters['branch']) {
            $query->where(fn ($q) => $q->where('origin_branch_id', $filters['branch'])->orWhere('destination_branch_id', $filters['branch']));
        }
        match ($date) {
            'today' => $query->whereDate('created_at', today()),
            '7d' => $query->where('created_at', '>=', now()->subDays(7)),
            '30d' => $query->where('created_at', '>=', now()->subDays(30)),
            '90d' => $query->where('created_at', '>=', now()->subDays(90)),
            default => null,
        };
        if ($stuck) {
            $query->whereIn('id', $dashboard->stuckOrdersQuery()->select('id'));
        }
        if ($issues) {
            // Same definition as the dashboard's Operational Exceptions panel.
            $query->where(fn ($q) => $q
                ->whereIn('id', $dashboard->stuckOrdersQuery()->select('id'))
                ->orWhere('status', 'delivery_failed')
                ->orWhere(fn ($sorted) => $sorted->where('status', 'sorted')->whereNull('courier_id')));
        }
        if ($search !== '') {
            $query->where(fn ($q) => $q->where('order_number', 'like', "%{$search}%")
                ->orWhere('waybill_number', 'like', "%{$search}%")
                ->orWhere('product_name', 'like', "%{$search}%")
                ->when(ctype_digit($search), fn ($byId) => $byId->orWhere('id', (int) $search)));
        }

        $activeFilter = match (true) {
            $issues => 'Operational exceptions: stuck, failed delivery or unassigned',
            $stuck => 'Stuck: in progress with no update for ' . AdminDashboardService::STUCK_AFTER_HOURS . 'h+',
            (bool) $statuses => 'Status: ' . implode(', ', array_map(fn ($s) => str_replace('_', ' ', $s), $statuses)),
            default => null,
        };

        $statusCounts = Order::selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        return view('admin.orders.index', [
            'orders' => $query->paginate(20)->withQueryString(),
            'stage' => $stage,
            'search' => $search,
            'date' => $date,
            'filters' => $filters,
            'statuses' => $statuses,
            'stages' => self::STAGES,
            'stageCounts' => collect(self::STAGES)->map(fn ($definition) => $statusCounts->only($definition['statuses'])->sum()),
            'totalOrders' => $statusCounts->sum(),
            'activeFilter' => $activeFilter,
            'options' => [
                'sellers' => User::where('role', 'seller')->whereHas('ordersAsSeller')->orderBy('business_name')->get(['id', 'first_name', 'last_name', 'business_name']),
                'buyers' => User::where('role', 'buyer')->whereHas('ordersAsBuyer')->orderBy('last_name')->get(['id', 'first_name', 'last_name', 'middle_initial']),
                'couriers' => User::where('role', 'courier')->whereHas('ordersAsCourier')->orderBy('last_name')->get(['id', 'first_name', 'last_name', 'middle_initial']),
                'branches' => LogisticsBranch::orderBy('name')->get(['id', 'name']),
            ],
        ]);
    }

    public function show(Order $order, FinancialSummary $finance)
    {
        Gate::authorize('view', $order);
        $order->load(['buyer', 'seller', 'courier', 'product', 'originBranch', 'destinationBranch', 'destinationBarangay',
            'returnRequest.events.actor', 'statusHistories.changer']);

        return view('admin.orders.show', [
            'order' => $order,
            'money' => $finance->forOrder($order),
            'overrideTargets' => auth()->user()->can(\App\Auth\Permission::ORDERS_OVERRIDE_STATUS) ? OrderAdministrationService::overrideTargets($order) : [],
            'exceptionActions' => auth()->user()->can(\App\Auth\Permission::ORDERS_MANAGE) ? OrderAdministrationService::exceptionActions($order) : [],
            'complaints' => Complaint::where(fn ($q) => $q->where('order_id', $order->id)
                ->orWhere(fn ($legacy) => $legacy
                    ->where(fn ($w) => $w->where('filed_by', $order->buyer_id)->where('against_user_id', $order->seller_id))
                    ->orWhere(fn ($w) => $w->where('filed_by', $order->seller_id)->where('against_user_id', $order->buyer_id))))
                ->where('created_at', '>=', $order->created_at->copy()->subDay())
                ->latest()->get(),
            'auditHistory' => AuditLog::with('actor')->where('subject_type', $order->getMorphClass())->where('subject_id', $order->id)->latest('id')->get(),
            'isStuck' => in_array($order->status, AdminDashboardService::IN_PROGRESS_STATUSES, true)
                && $order->updated_at->lt(now()->subHours(AdminDashboardService::STUCK_AFTER_HOURS)),
        ]);
    }

    public function overrideStatus(AdminOrderStatusRequest $request, Order $order, OrderAdministrationService $orders)
    {
        $orders->overrideStatus($order, $request->user(), $request->validated('status'), $request->validated('reason'));

        return back()->with('success', "Order {$order->order_number} moved to " . OrderLifecycleService::label($order->status) . '. Buyer and seller were notified.');
    }

    public function resolveException(ResolveOrderExceptionRequest $request, Order $order, OrderAdministrationService $orders)
    {
        $orders->resolveException($order, $request->user(), $request->validated('action'), $request->validated('reason'));

        return back()->with('success', OrderAdministrationService::EXCEPTION_ACTIONS[$request->validated('action')]['label'] . " applied to {$order->order_number}.");
    }
}
