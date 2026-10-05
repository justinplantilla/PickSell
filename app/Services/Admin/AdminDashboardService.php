<?php

namespace App\Services\Admin;

use App\Auth\Permission;
use App\Models\BranchRider;
use App\Models\Complaint;
use App\Models\Order;
use App\Models\ReturnRequest;
use App\Models\User;
use App\Services\Finance\FinancialSummary;
use Illuminate\Support\Carbon;

/**
 * Live aggregation for the Admin command center. No snapshot tables: every figure is read from
 * the current database state on request. Only sections the viewer is permitted to see are computed.
 */
class AdminDashboardService
{
    /** An in-progress order untouched for longer than this is "stuck". */
    public const STUCK_AFTER_HOURS = 48;

    public const IN_PROGRESS_STATUSES = [
        'placed', 'confirmed', 'preparing', 'ready_for_pickup',
        'picked_up', 'at_sorting_center', 'sorted', 'assigned_to_rider', 'out_for_delivery',
    ];

    /** Lifecycle strip: stage => statuses (order follows the marketplace lifecycle). */
    public const LIFECYCLE = [
        'placed' => ['label' => 'Placed', 'statuses' => ['placed', 'confirmed']],
        'preparing' => ['label' => 'Preparing', 'statuses' => ['preparing', 'ready_for_pickup']],
        'sorting' => ['label' => 'Sorting', 'statuses' => ['picked_up', 'at_sorting_center', 'sorted']],
        'delivery' => ['label' => 'Delivery', 'statuses' => ['assigned_to_rider', 'out_for_delivery']],
        'awaiting_confirmation' => ['label' => 'Awaiting buyer', 'statuses' => ['delivered']],
    ];

    /** Accounts Admin can approve (couriers are reviewed by Logistics). */
    public const ADMIN_REVIEWED_ROLES = ['buyer', 'seller', 'logistics'];

    public function __construct(private FinancialSummary $finance) {}

    public function build(User $viewer): array
    {
        $can = fn (string $permission) => $viewer->hasPermission($permission);
        $statusCounts = $can(Permission::ORDERS_VIEW) || $can(Permission::LOGISTICS_VIEW)
            ? Order::selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status')
            : collect();

        $exceptions = $this->exceptions($viewer, $statusCounts);

        return [
            'kpis' => $this->kpis($viewer, $statusCounts, $exceptions),
            'lifecycle' => $can(Permission::ORDERS_VIEW) ? $this->lifecycle($statusCounts) : null,
            'pendingActions' => $this->pendingActions($viewer),
            'exceptions' => $exceptions,
            'finance' => $can(Permission::REPORTS_VIEW) || $can(Permission::COMMISSION_VIEW) ? $this->finance() : null,
            'users' => $can(Permission::USERS_VIEW) ? $this->users() : null,
            'pendingApplications' => $can(Permission::REGISTRATIONS_VIEW)
                ? User::whereIn('role', self::ADMIN_REVIEWED_ROLES)->where('status', 'pending')->oldest()->take(5)->get()
                : collect(),
        ];
    }

    public function stuckOrdersQuery()
    {
        return Order::whereIn('status', self::IN_PROGRESS_STATUSES)
            ->where('updated_at', '<', now()->subHours(self::STUCK_AFTER_HOURS));
    }

    /** Pending approvals count, also used for the sidebar badge. */
    public function pendingApprovals(): int
    {
        return User::whereIn('role', self::ADMIN_REVIEWED_ROLES)->where('status', 'pending')->count();
    }

    private function kpis(User $viewer, $statusCounts, array $exceptions): array
    {
        $kpis = [];

        if ($viewer->hasPermission(Permission::ORDERS_VIEW)) {
            $today = Order::whereDate('created_at', today())->count();
            $kpis[] = [
                'key' => 'orders_today', 'label' => 'Orders today', 'value' => $today,
                'detail' => number_format($statusCounts->only(self::IN_PROGRESS_STATUSES)->sum()) . ' in progress overall',
                'url' => route('admin.orders', ['placed' => 'today']),
            ];
        }

        if ($viewer->hasPermission(Permission::REGISTRATIONS_VIEW)) {
            $kpis[] = [
                'key' => 'pending_approvals', 'label' => 'Pending approvals', 'value' => $this->pendingApprovals(),
                'detail' => 'Buyer, seller and logistics applications',
                'url' => route('admin.registrations', ['status' => 'pending']),
            ];
        }

        if ($viewer->hasPermission(Permission::LOGISTICS_VIEW)) {
            $activeRiders = BranchRider::where('status', 'active')
                ->whereHas('rider', fn ($q) => $q->where('role', 'courier')->where('status', 'approved'))
                ->distinct()->count('user_id');
            $onDelivery = Order::whereIn('status', ['assigned_to_rider', 'out_for_delivery'])->whereNotNull('courier_id')->distinct()->count('courier_id');
            $kpis[] = [
                'key' => 'active_riders', 'label' => 'Active riders', 'value' => $activeRiders,
                'detail' => number_format($onDelivery) . ' carrying parcels now',
                'url' => route('admin.logistics.riders'),
            ];
        }

        if ($viewer->hasPermission(Permission::ORDERS_VIEW) || $viewer->hasPermission(Permission::LOGISTICS_VIEW)) {
            $issues = collect($exceptions)->sum('count');
            $kpis[] = [
                'key' => 'issues', 'label' => 'Issues', 'value' => $issues,
                'detail' => $issues ? 'Stuck, failed or unassigned orders' : 'No operational exceptions',
                // Orders view lists every exception type together; logistics-only viewers get the sorting queue.
                'url' => $viewer->hasPermission(Permission::ORDERS_VIEW)
                    ? route('admin.orders', ['issues' => 1])
                    : route('admin.logistics.sorting', ['status' => 'sorted']),
                'alert' => $issues > 0,
            ];
        }

        return $kpis;
    }

    private function lifecycle($statusCounts): array
    {
        return collect(self::LIFECYCLE)->map(fn ($stage) => [
            'label' => $stage['label'],
            'count' => (int) $statusCounts->only($stage['statuses'])->sum(),
            'url' => route('admin.orders', ['status' => implode(',', $stage['statuses'])]),
        ])->values()->all();
    }

    private function pendingActions(User $viewer): array
    {
        $actions = [];
        if ($viewer->hasPermission(Permission::REGISTRATIONS_VIEW)) {
            $actions[] = ['label' => 'Registrations awaiting review', 'count' => $this->pendingApprovals(),
                'url' => route('admin.registrations', ['status' => 'pending']), 'cta' => 'Review applications'];
        }
        if ($viewer->hasPermission(Permission::COMPLAINTS_VIEW)) {
            $actions[] = ['label' => 'Open complaints', 'count' => Complaint::whereIn('status', ['open', 'under_review'])->count(),
                'url' => route('admin.complaints', ['status' => 'open']), 'cta' => 'Review complaints'];
        }
        if ($viewer->hasPermission(Permission::RETURNS_VIEW)) {
            $actions[] = ['label' => 'Return disputes to decide', 'count' => ReturnRequest::where('dispute_status', 'open')->count(),
                'url' => route('admin.disputes', ['status' => 'open']), 'cta' => 'Resolve disputes'];
        }

        return $actions;
    }

    private function exceptions(User $viewer, $statusCounts): array
    {
        $exceptions = [];
        if ($viewer->hasPermission(Permission::ORDERS_VIEW)) {
            $exceptions[] = ['key' => 'stuck', 'label' => 'Stuck orders', 'count' => $this->stuckOrdersQuery()->count(),
                'detail' => 'In progress with no update for ' . self::STUCK_AFTER_HOURS . 'h+',
                'url' => route('admin.orders', ['stuck' => 1])];
            $exceptions[] = ['key' => 'failed', 'label' => 'Failed deliveries', 'count' => (int) ($statusCounts['delivery_failed'] ?? 0),
                'detail' => 'Delivery attempts that did not reach the buyer',
                'url' => route('admin.orders', ['status' => 'delivery_failed'])];
        }
        if ($viewer->hasPermission(Permission::LOGISTICS_VIEW)) {
            $exceptions[] = ['key' => 'unassigned', 'label' => 'Unassigned parcels', 'count' => Order::where('status', 'sorted')->whereNull('courier_id')->count(),
                'detail' => 'Sorted and waiting for a rider',
                'url' => route('admin.logistics.sorting', ['status' => 'sorted'])];
        }

        return $exceptions;
    }

    private function finance(): array
    {
        return [
            'today' => $this->finance->forPeriod(today()->startOfDay(), now()),
            'month' => $this->finance->forPeriod(now()->startOfMonth(), now()),
        ];
    }

    private function users(): array
    {
        $byRole = User::where('status', 'approved')->whereIn('role', ['buyer', 'seller', 'logistics', 'courier'])
            ->selectRaw('role, count(*) as total')->groupBy('role')->pluck('total', 'role');

        $start = now()->startOfMonth()->subMonths(5);
        $registrations = User::whereIn('role', ['buyer', 'seller', 'logistics', 'courier'])
            ->where('created_at', '>=', $start)->get(['created_at'])
            ->countBy(fn (User $user) => $user->created_at->format('Y-m'));
        $months = collect(range(0, 5))->map(fn ($i) => $start->copy()->addMonths($i));

        return [
            'distribution' => [
                'Buyers' => (int) ($byRole['buyer'] ?? 0),
                'Sellers' => (int) ($byRole['seller'] ?? 0),
                'Logistics' => (int) ($byRole['logistics'] ?? 0),
                'Riders' => (int) ($byRole['courier'] ?? 0),
            ],
            'suspended' => User::where('status', 'suspended')->count(),
            'trend' => [
                'labels' => $months->map(fn (Carbon $month) => $month->format('M Y'))->all(),
                'values' => $months->map(fn (Carbon $month) => (int) ($registrations[$month->format('Y-m')] ?? 0))->all(),
            ],
        ];
    }
}
