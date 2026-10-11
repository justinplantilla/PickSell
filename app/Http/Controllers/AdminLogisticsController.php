<?php

namespace App\Http\Controllers;

use App\Models\BranchRider;
use App\Models\LogisticsBranch;
use App\Models\LogisticsException;
use App\Models\Order;
use Illuminate\Http\Request;

/**
 * Read-only admin oversight of logistics operations.
 */
class AdminLogisticsController extends Controller
{
    public const PIPELINE = ['ready_for_pickup', 'picked_up', 'at_sorting_center', 'sorted', 'assigned_to_rider', 'out_for_delivery', 'delivery_failed'];

    public const SORTING_STATUSES = ['ready_for_pickup', 'picked_up', 'at_sorting_center', 'sorted'];

    public const RIDER_STATUSES = ['assigned_to_rider', 'out_for_delivery', 'delivery_failed'];

    /** A parcel sitting in one sorting stage longer than this is flagged as stale. */
    public const STALE_AFTER_HOURS = 48;

    public function index()
    {
        $pipeline = Order::whereIn('status', self::PIPELINE)
            ->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        $branches = LogisticsBranch::with(['municipality', 'logistics'])
            ->withCount(['riderAssignments as active_riders_count' => fn ($q) => $q->where('status', 'active')])
            ->orderBy('name')
            ->get();
        $inbound = Order::whereIn('status', self::PIPELINE)->whereNotNull('destination_branch_id')
            ->selectRaw('destination_branch_id, count(*) as total')->groupBy('destination_branch_id')->pluck('total', 'destination_branch_id');
        $outbound = Order::whereIn('status', self::PIPELINE)->whereNotNull('origin_branch_id')
            ->selectRaw('origin_branch_id, count(*) as total')->groupBy('origin_branch_id')->pluck('total', 'origin_branch_id');

        return view('admin.logistics.overview', [
            'pipeline' => collect(self::PIPELINE)->mapWithKeys(fn ($status) => [$status => (int) ($pipeline[$status] ?? 0)]),
            'branches' => $branches,
            'inbound' => $inbound,
            'outbound' => $outbound,
            'staleCount' => $this->staleSortingQuery()->count(),
            'awaitingRider' => Order::where('status', 'sorted')->whereNull('courier_id')->count(),
            'openExceptions' => LogisticsException::query()
                ->where('status', 'open')
                ->with(['order.returnRequest', 'order.delivery', 'order.logistics', 'order.destinationBranch'])
                ->latest()
                ->take(10)
                ->get(),
            'openExceptionCount' => LogisticsException::where('status', 'open')->count(),
        ]);
    }

    public function sorting(Request $request)
    {
        $status = in_array($request->query('status'), self::SORTING_STATUSES, true) ? $request->query('status') : 'all';
        $staleOnly = $request->boolean('stale');

        $query = $staleOnly ? $this->staleSortingQuery() : Order::whereIn('status', self::SORTING_STATUSES);
        if ($status !== 'all') {
            $query->where('status', $status);
        }
        $parcels = $query->with(['seller', 'originBranch', 'destinationBranch'])->oldest('updated_at')->paginate(20)->withQueryString();

        return view('admin.logistics.sorting', [
            'parcels' => $parcels,
            'status' => $status,
            'staleOnly' => $staleOnly,
            'statuses' => self::SORTING_STATUSES,
            'staleAfterHours' => self::STALE_AFTER_HOURS,
        ]);
    }

    public function riders(Request $request)
    {
        $status = in_array($request->query('status'), self::RIDER_STATUSES, true) ? $request->query('status') : 'all';

        $parcels = Order::whereIn('status', $status === 'all' ? self::RIDER_STATUSES : [$status])
            ->with(['courier', 'destinationBranch', 'destinationBarangay'])
            ->latest('updated_at')->paginate(20)->withQueryString();

        $activeLoad = Order::whereIn('status', ['assigned_to_rider', 'out_for_delivery'])->whereNotNull('courier_id')
            ->selectRaw('courier_id, count(*) as total')->groupBy('courier_id')->pluck('total', 'courier_id');
        $roster = BranchRider::with(['rider', 'branch'])->where('status', 'active')->get()
            ->sortByDesc(fn (BranchRider $assignment) => $activeLoad[$assignment->user_id] ?? 0)->values();

        return view('admin.logistics.riders', [
            'parcels' => $parcels,
            'status' => $status,
            'statuses' => self::RIDER_STATUSES,
            'roster' => $roster,
            'activeLoad' => $activeLoad,
            'awaitingRider' => Order::where('status', 'sorted')->whereNull('courier_id')->count(),
        ]);
    }

    private function staleSortingQuery()
    {
        return Order::whereIn('status', self::SORTING_STATUSES)->where('updated_at', '<', now()->subHours(self::STALE_AFTER_HOURS));
    }
}
