<?php

namespace App\Http\Controllers;

use App\Auth\Permission;
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
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/** Marketplace-wide order control surface (orders.view; overrides need their own permissions). */
class AdminOrderController extends Controller
{
    public const STAGES = [
        'fulfillment' => ['label' => 'Seller fulfillment', 'statuses' => ['placed', 'confirmed', 'preparing', 'ready_for_pickup']],
        'logistics' => ['label' => 'In logistics', 'statuses' => ['picked_up', 'at_sorting_center', 'sorted', 'assigned_to_rider', 'out_for_delivery']],
        'delivered' => ['label' => 'Delivered', 'statuses' => ['delivered', 'completed']],
        'exceptions' => ['label' => 'Exceptions', 'statuses' => ['delivery_failed', 'returned', 'cancelled']],
    ];

    public const DATE_FILTERS = ['today' => 'Placed today', '7d' => 'Last 7 days', '30d' => 'Last 30 days', '90d' => 'Last 90 days'];

    public function index(Request $request, AdminDashboardService $dashboard)
    {
        $filters = $this->filters($request);
        $query = $this->filteredOrders($filters, $dashboard);
        $activeFilter = match (true) {
            $filters['issues'] => 'Operational exceptions: stuck, failed delivery or unassigned',
            $filters['stuck'] => 'Stuck: in progress with no update for '.AdminDashboardService::STUCK_AFTER_HOURS.'h+',
            (bool) $filters['statuses'] => 'Status: '.implode(', ', array_map(fn ($s) => str_replace('_', ' ', $s), $filters['statuses'])),
            default => null,
        };

        $statusCounts = Order::selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        return view('admin.orders.index', [
            'orders' => $query->paginate(20)->withQueryString(),
            'stage' => $filters['stage'],
            'search' => $filters['search'],
            'date' => $filters['date'],
            'filters' => $filters['parties'],
            'statuses' => $filters['statuses'],
            'stages' => self::STAGES,
            'stageCounts' => collect(self::STAGES)->map(fn ($definition) => $statusCounts->only($definition['statuses'])->sum()),
            'totalOrders' => $statusCounts->sum(),
            'activeFilter' => $activeFilter,
            'matchingCount' => (clone $query)->count(),
            'options' => [
                'sellers' => User::where('role', 'seller')->whereHas('ordersAsSeller')->orderBy('business_name')->get(['id', 'first_name', 'last_name', 'business_name']),
                'buyers' => User::where('role', 'buyer')->whereHas('ordersAsBuyer')->orderBy('last_name')->get(['id', 'first_name', 'last_name', 'middle_initial']),
                'couriers' => User::where('role', 'courier')->whereHas('ordersAsCourier')->orderBy('last_name')->get(['id', 'first_name', 'last_name', 'middle_initial']),
                'branches' => LogisticsBranch::orderBy('name')->get(['id', 'name']),
            ],
        ]);
    }

    public function bulkOverrideStatus(
        Request $request,
        AdminDashboardService $dashboard,
        OrderAdministrationService $orders,
    ) {
        $validated = $request->validate([
            'to_status' => ['required', Rule::in(Order::STATUS_LIFECYCLE)],
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
            'confirm' => ['accepted'],
            'matching_count' => ['required', 'integer', 'min:1'],
        ]);
        $query = $this->filteredOrders($this->filters($request), $dashboard);
        $currentCount = (clone $query)->count();
        if ($currentCount !== (int) $validated['matching_count']) {
            return back()->with('warning', 'The matching order count changed. Review the refreshed results before applying this bulk action.');
        }

        $processed = 0;
        $skipped = [];
        $query->reorder()->orderBy('id')->chunkById(100, function ($matches) use ($validated, $orders, &$processed, &$skipped): void {
            foreach ($matches as $order) {
                $decision = Gate::inspect('overrideStatus', [$order, $validated['to_status']]);
                if (! $decision->allowed()) {
                    $skipped[] = $order->order_number.': '.$decision->message();

                    continue;
                }

                try {
                    $orders->overrideStatus($order, request()->user(), $validated['to_status'], $validated['reason']);
                    $processed++;
                } catch (HttpExceptionInterface $exception) {
                    if (! in_array($exception->getStatusCode(), [403, 404, 409], true)) {
                        throw $exception;
                    }
                    $skipped[] = $order->order_number.': '.$exception->getMessage();
                }
            }
        });

        return back()->with('success', "{$processed} order(s) updated.")
            ->with('bulkSkipped', array_slice($skipped, 0, 20))
            ->with('bulkSkippedCount', count($skipped));
    }

    public function show(Order $order, FinancialSummary $finance)
    {
        Gate::authorize('view', $order);
        $order->load(['buyer', 'seller', 'courier', 'product', 'originBranch', 'destinationBranch', 'destinationBarangay',
            'returnRequest.events.actor', 'statusHistories.changer']);

        return view('admin.orders.show', [
            'order' => $order,
            'money' => $finance->forOrder($order),
            'overrideTargets' => auth()->user()->can(Permission::ORDERS_OVERRIDE_STATUS) ? OrderAdministrationService::overrideTargets($order) : [],
            'exceptionActions' => auth()->user()->can(Permission::ORDERS_MANAGE) ? OrderAdministrationService::exceptionActions($order) : [],
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

        return back()->with('success', "Order {$order->order_number} moved to ".OrderLifecycleService::label($order->status).'. Buyer and seller were notified.');
    }

    public function resolveException(ResolveOrderExceptionRequest $request, Order $order, OrderAdministrationService $orders)
    {
        $orders->resolveException($order, $request->user(), $request->validated('action'), $request->validated('reason'));

        return back()->with('success', OrderAdministrationService::EXCEPTION_ACTIONS[$request->validated('action')]['label']." applied to {$order->order_number}.");
    }

    private function filters(Request $request): array
    {
        $ids = fn (string $key) => is_numeric($request->input($key)) ? (int) $request->input($key) : null;
        $statusFilter = (string) $request->input('filter_status', $request->input('status', ''));

        return [
            'stage' => array_key_exists($request->input('stage'), self::STAGES) ? $request->input('stage') : 'all',
            'search' => trim((string) $request->input('search', '')),
            'statuses' => array_values(array_intersect(explode(',', $statusFilter), Order::STATUS_LIFECYCLE)),
            'date' => array_key_exists($request->input('date'), self::DATE_FILTERS) ? $request->input('date') : ($request->input('placed') === 'today' ? 'today' : 'any'),
            'parties' => ['seller' => $ids('seller'), 'buyer' => $ids('buyer'), 'courier' => $ids('courier'), 'branch' => $ids('branch')],
            'stuck' => $request->boolean('stuck'),
            'issues' => $request->boolean('issues'),
        ];
    }

    private function filteredOrders(array $filters, AdminDashboardService $dashboard): Builder
    {
        $query = Order::with(['buyer', 'seller', 'courier'])->latest('updated_at');
        if ($filters['stage'] !== 'all') {
            $query->whereIn('status', self::STAGES[$filters['stage']]['statuses']);
        }
        if ($filters['statuses']) {
            $query->whereIn('status', $filters['statuses']);
        }
        foreach (['seller' => 'seller_id', 'buyer' => 'buyer_id', 'courier' => 'courier_id'] as $key => $column) {
            if ($filters['parties'][$key]) {
                $query->where($column, $filters['parties'][$key]);
            }
        }
        if ($filters['parties']['branch']) {
            $query->where(fn ($q) => $q->where('origin_branch_id', $filters['parties']['branch'])
                ->orWhere('destination_branch_id', $filters['parties']['branch']));
        }
        match ($filters['date']) {
            'today' => $query->whereDate('created_at', today()),
            '7d' => $query->where('created_at', '>=', now()->subDays(7)),
            '30d' => $query->where('created_at', '>=', now()->subDays(30)),
            '90d' => $query->where('created_at', '>=', now()->subDays(90)),
            default => null,
        };
        if ($filters['stuck']) {
            $query->whereIn('id', $dashboard->stuckOrdersQuery()->select('id'));
        }
        if ($filters['issues']) {
            $query->where(fn ($q) => $q
                ->whereIn('id', $dashboard->stuckOrdersQuery()->select('id'))
                ->orWhere('status', 'delivery_failed')
                ->orWhere(fn ($sorted) => $sorted->where('status', 'sorted')->whereNull('courier_id')));
        }
        if ($filters['search'] !== '') {
            $search = $filters['search'];
            $query->where(fn ($q) => $q->where('order_number', 'like', "%{$search}%")
                ->orWhere('waybill_number', 'like', "%{$search}%")
                ->orWhere('product_name', 'like', "%{$search}%")
                ->when(ctype_digit($search), fn ($byId) => $byId->orWhere('id', (int) $search)));
        }

        return $query;
    }
}
