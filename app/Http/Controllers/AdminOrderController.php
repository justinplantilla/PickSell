<?php

namespace App\Http\Controllers;

use App\Auth\Permission;
use App\Http\Requests\Admin\AdminOrderStatusRequest;
use App\Http\Requests\Admin\ResolveOrderExceptionRequest;
use App\Models\AuditLog;
use App\Models\Complaint;
use App\Models\Order;
use App\Models\User;
use App\Services\Admin\AdminDashboardService;
use App\Services\Finance\FinancialSummary;
use App\Services\Orders\OrderAdministrationService;
use App\Services\AuditLogger;
use App\Services\Orders\OrderLifecycleService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/** Marketplace-wide order control surface (orders.view; overrides need their own permissions). */
class AdminOrderController extends Controller
{
    public const STAGES = [
        'fulfillment' => ['label' => 'Seller fulfillment', 'statuses' => ['placed', 'confirmed', 'preparing', 'ready_for_pickup']],
        'logistics' => ['label' => 'With Pickrush', 'statuses' => ['picked_up', 'at_sorting_center', 'sorted', 'assigned_to_rider', 'out_for_delivery']],
        'delivered' => ['label' => 'Delivered', 'statuses' => ['delivered', 'completed']],
        'exceptions' => ['label' => 'Exceptions', 'statuses' => ['delivery_failed', 'returned', 'cancelled']],
    ];

    public const DATE_FILTERS = ['today' => 'Placed today', '7d' => 'Last 7 days', '30d' => 'Last 30 days', '90d' => 'Last 90 days'];

    public function index(Request $request, AdminDashboardService $dashboard)
    {
        $filters = $this->filters($request);
        $query = $this->filteredOrders($filters, $dashboard);
        $activeFilter = match (true) {
            $filters['issues'] => 'Operational exceptions: stuck or failed delivery',
            $filters['stuck'] => 'Stuck: in progress with no update for '.AdminDashboardService::STUCK_AFTER_HOURS.'h+',
            (bool) $filters['statuses'] => 'Status: '.implode(', ', array_map(fn ($s) => OrderLifecycleService::label($s), $filters['statuses'])),
            default => null,
        };

        $statusCounts = Order::selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');
        $options = [
            'sellers' => User::where('role', 'seller')->whereHas('ordersAsSeller')->orderBy('business_name')->get(['id', 'first_name', 'last_name', 'business_name']),
            'buyers' => User::where('role', 'buyer')->whereHas('ordersAsBuyer')->orderBy('last_name')->get(['id', 'first_name', 'last_name', 'middle_initial']),
        ];

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
            'chips' => $this->activeChips($request, $filters, $options, $activeFilter),
            'moreFiltersCount' => count(array_filter($filters['parties'])),
            'matchingCount' => (clone $query)->count(),
            'stuckAfterHours' => AdminDashboardService::STUCK_AFTER_HOURS,
            'options' => $options,
        ]);
    }

    /** Side-panel summary of one order, loaded into the list page without leaving it. */
    public function preview(Order $order)
    {
        Gate::authorize('view', $order);
        $order->load(['buyer', 'seller',
            'statusHistories' => fn ($q) => $q->with('changer')->reorder()->latest('id')->limit(5)]);

        return view('admin.orders._preview', [
            'order' => $order,
            'isStuck' => $this->isStuck($order),
        ]);
    }

    /** CSV of the orders matching the current filters, or of the selected `order_ids`. */
    public function export(Request $request, AdminDashboardService $dashboard, AuditLogger $audit): StreamedResponse
    {
        $selected = $this->selectedIds($request);
        $filters = $this->filters($request);
        $query = $selected
            ? Order::with(['buyer', 'seller'])->whereKey($selected)
            : $this->filteredOrders($filters, $dashboard);
        $audit->record('orders.exported', null, [], array_filter([
            'order_ids' => $selected ?: null,
            'filters' => $selected ? null : array_filter([
                'stage' => $filters['stage'] !== 'all' ? $filters['stage'] : null,
                'search' => $filters['search'] ?: null,
                'statuses' => $filters['statuses'] ?: null,
                'date' => $filters['date'] !== 'any' ? $filters['date'] : null,
                'parties' => array_filter($filters['parties']) ?: null,
                'stuck' => $filters['stuck'] ?: null,
                'issues' => $filters['issues'] ?: null,
            ]),
        ]), Permission::REPORTS_EXPORT);

        $cell = fn (?string $value) => $value !== null && preg_match('/^[\s]*[=+\-@]/', $value) ? "'".$value : $value;

        return response()->streamDownload(function () use ($query, $cell) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['order_number', 'status', 'tracking_status', 'product', 'quantity', 'amount', 'buyer', 'seller', 'waybill', 'placed_at', 'updated_at']);
            $query->reorder()->orderBy('id')->chunk(500, function ($orders) use ($out, $cell) {
                foreach ($orders as $order) {
                    fputcsv($out, [
                        $order->order_number,
                        $order->status,
                        $cell($order->tracking_status),
                        $cell($order->product_name),
                        $order->quantity,
                        number_format((float) $order->amount, 2, '.', ''),
                        $cell($order->buyer?->full_name),
                        $cell($order->seller?->business_name ?? $order->seller?->full_name),
                        $cell($order->waybill_number),
                        $order->created_at?->toIso8601String(),
                        $order->updated_at?->toIso8601String(),
                    ]);
                }
            });
            fclose($out);
        }, 'orders-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv']);
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
        $selected = $this->selectedIds($request);
        $query = $selected
            ? Order::whereKey($selected)
            : $this->filteredOrders($this->filters($request), $dashboard);
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
        $order->load(['buyer', 'seller', 'product',
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
            'isStuck' => $this->isStuck($order),
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

    /** Up to 500 explicitly selected order ids from the list checkboxes; empty means "all matching". */
    private function selectedIds(Request $request): array
    {
        $ids = $request->input('order_ids', []);
        if (is_string($ids)) {
            $ids = explode(',', $ids);
        }

        return collect(is_array($ids) ? $ids : [])
            ->filter(fn ($id) => is_scalar($id) && ctype_digit((string) $id) && (int) $id > 0)
            ->map(fn ($id) => (int) $id)
            ->unique()->take(500)->values()->all();
    }

    private function isStuck(Order $order): bool
    {
        return in_array($order->status, AdminDashboardService::IN_PROGRESS_STATUSES, true)
            && $order->updated_at->lt(now()->subHours(AdminDashboardService::STUCK_AFTER_HOURS));
    }

    /**
     * Removable chips for every filter in effect besides the stage tab.
     *
     * @return list<array{label: string, url: string}>
     */
    private function activeChips(Request $request, array $filters, array $options, ?string $activeFilter): array
    {
        $without = fn (string ...$keys) => route('admin.orders', $request->except([...$keys, 'page']));
        $name = fn (string $key, int $id, callable $label) => ($match = $options[$key]->firstWhere('id', $id)) ? $label($match) : "#{$id}";
        $chips = [];

        if ($filters['search'] !== '') {
            $chips[] = ['label' => "Search: \u{201C}{$filters['search']}\u{201D}", 'url' => $without('search')];
        }
        if ($filters['statuses']) {
            $chips[] = ['label' => 'Status: '.implode(', ', array_map(fn ($s) => OrderLifecycleService::label($s), $filters['statuses'])), 'url' => $without('status', 'filter_status')];
        }
        if ($filters['date'] !== 'any') {
            $chips[] = ['label' => self::DATE_FILTERS[$filters['date']], 'url' => $without('date', 'placed')];
        }
        foreach ([
            'seller' => ['Seller', 'sellers', fn ($u) => $u->business_name ?? $u->full_name],
            'buyer' => ['Buyer', 'buyers', fn ($u) => $u->full_name],
        ] as $key => [$label, $optionKey, $labelOf]) {
            if ($filters['parties'][$key]) {
                $chips[] = ['label' => $label.': '.$name($optionKey, $filters['parties'][$key], $labelOf), 'url' => $without($key)];
            }
        }
        if ($filters['issues'] || $filters['stuck']) {
            $chips[] = ['label' => $activeFilter, 'url' => $without('issues', 'stuck')];
        }

        return $chips;
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
            'parties' => ['seller' => $ids('seller'), 'buyer' => $ids('buyer')],
            'stuck' => $request->boolean('stuck'),
            'issues' => $request->boolean('issues'),
        ];
    }

    private function filteredOrders(array $filters, AdminDashboardService $dashboard): Builder
    {
        $query = Order::with(['buyer', 'seller'])->latest('updated_at');
        if ($filters['stage'] !== 'all') {
            $query->whereIn('status', self::STAGES[$filters['stage']]['statuses']);
        }
        if ($filters['statuses']) {
            $query->whereIn('status', $filters['statuses']);
        }
        foreach (['seller' => 'seller_id', 'buyer' => 'buyer_id'] as $key => $column) {
            if ($filters['parties'][$key]) {
                $query->where($column, $filters['parties'][$key]);
            }
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
                ->orWhere('status', 'delivery_failed'));
        }
        if ($filters['search'] !== '') {
            $search = $filters['search'];
            $words = preg_split('/\s+/', $search);
            // Nested so the OR branches stay inside the relation's own constraint.
            $party = fn ($q) => $q->where(fn ($name) => $name->whereLike('first_name', "%{$search}%")
                ->orWhereLike('last_name', "%{$search}%")
                ->orWhereLike('business_name', "%{$search}%")
                ->when(count($words) > 1, fn ($full) => $full->orWhere(fn ($both) => $both
                    ->whereLike('first_name', '%'.$words[0].'%')
                    ->whereLike('last_name', '%'.end($words).'%'))));
            $query->where(fn ($q) => $q->whereLike('order_number', "%{$search}%")
                ->orWhereLike('waybill_number', "%{$search}%")
                ->orWhereLike('product_name', "%{$search}%")
                // A numeric search means an order id or number, never a person's name.
                ->when(! ctype_digit($search), fn ($byName) => $byName->orWhereHas('buyer', $party)->orWhereHas('seller', $party))
                ->when(ctype_digit($search), fn ($byId) => $byId->orWhere('id', (int) $search)));
        }

        return $query;
    }
}
