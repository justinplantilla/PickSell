<?php

namespace App\Http\Controllers;

use App\Http\Requests\AssignCourierRequest;
use App\Http\Requests\OpenLogisticsExceptionRequest;
use App\Http\Requests\ResolveParcelExceptionRequest;
use App\Http\Requests\ScanParcelRequest;
use App\Models\Barangay;
use App\Models\BranchRider;
use App\Models\LogisticsBranch;
use App\Models\LogisticsException;
use App\Models\Order;
use App\Models\RiderBarangay;
use App\Models\User;
use App\Services\Admin\RegistrationReviewService;
use App\Services\BarangayImporter;
use App\Services\CourierAssignmentService;
use App\Services\LogisticsExceptionService;
use App\Services\LogisticsRoutingService;
use App\Services\LogisticsService;
use App\Services\Orders\DeliveryStatusTransitionPolicy;
use App\Services\Orders\OrderLifecycleService;
use App\Support\RegistrationChecklist;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use RuntimeException;

/**
 * Station Hub Portal
 *
 * Handles last-mile operations for a single station hub (distribution point):
 *   - Approve seller pickup requests
 *   - Scan incoming parcels
 *   - Sort parcels for local delivery or inter-province linehaul
 *   - Assign riders for last-mile delivery
 *   - Manage delivery exceptions
 */
class StationHubController extends Controller
{
    private const MISSING_SCAN_AFTER_HOURS = 48;

    private const PIPELINE_STATUSES = ['ready_for_pickup', 'picked_up', 'at_sorting_center', 'sorted', 'in_transit', 'assigned_to_rider', 'out_for_delivery', 'delivery_failed', 'delivered', 'completed'];

    private const SCANNABLE_TRACKING = ['Pickup approved by logistics', 'Handed over to courier'];

    private const SCANNED_TRACKING = 'Received and scanned at sorting center';

    private function station(): LogisticsBranch
    {
        return request()->attributes->get('station_hub');
    }

    private function ordersForStation()
    {
        $station = $this->station();

        return Order::query()
            ->where('logistics_id', $station->logistics_id)
            ->where(fn ($q) => $q
                ->where('origin_branch_id', $station->id)
                ->orWhere('destination_branch_id', $station->id));
    }

    private function ensureStationOrder(Order $order, bool $destinationOnly = false): void
    {
        $station = $this->station();
        $matches = (int) $order->destination_branch_id === (int) $station->id
            || (! $destinationOnly && (int) $order->origin_branch_id === (int) $station->id);

        abort_unless(
            $matches && (int) $order->logistics_id === (int) $station->logistics_id,
            403,
            'This parcel does not belong to this station hub.',
        );
    }

    public function dashboard()
    {
        $station = $this->station();
        $base    = $this->ordersForStation();

        $stats = [
            'pending_pickup'  => (clone $base)->where('status', 'ready_for_pickup')->count(),
            'to_scan'         => $this->awaitingScanQuery(clone $base)->count(),
            'to_sort'         => $this->awaitingSortQuery(clone $base)->count(),
            'to_assign'       => (clone $base)->where('status', 'sorted')->where('destination_branch_id', $station->id)->count(),
            'on_road'         => (clone $base)->whereIn('status', ['assigned_to_rider', 'out_for_delivery'])->count(),
            'failed'          => (clone $base)->where('status', 'delivery_failed')->count(),
            'rider_unavailable' => $this->riderUnavailableQuery(clone $base, $station)->count(),
        ];
        $activeRiders = BranchRider::where('branch_id', $station->id)->where('status', 'active')->count();

        $orders = $this->ordersForStation()
            ->with(['buyer', 'courier', 'originBranch', 'destinationBranch', 'originHub', 'destinationHub'])
            ->whereIn('status', ['ready_for_pickup', 'picked_up', 'at_sorting_center', 'sorted', 'assigned_to_rider', 'out_for_delivery', 'delivery_failed'])
            ->latest('updated_at')->take(10)->get();

        return view('logistics.station-hub.dashboard', compact('station', 'stats', 'orders', 'activeRiders'));
    }

    /** Old pre-hub branches (no province hub). Riders stuck there may be taken over by a real station. */
    private function legacyBranches(): \Closure
    {
        return fn ($branch) => $branch->whereNull('parent_id')->where('hub_type', 'station_hub');
    }

    public function riders()
    {
        $station = $this->station();
        $assignments = BranchRider::query()
            ->where('branch_id', $station->id)
            ->with(['rider', 'barangays.barangay'])
            ->orderBy('id')
            ->get();
        $availableRiders = User::query()
            ->where('role', 'courier')
            ->where('status', 'approved')
            ->whereDoesntHave('branchAssignments', fn ($query) => $query->where('status', 'active')
                ->whereDoesntHave('branch', $this->legacyBranches()))
            ->with(['branchAssignments' => fn ($query) => $query->where('status', 'active')->with('branch')])
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get()
            ->reject(fn (User $rider) => $rider->branchAssignments->contains('branch_id', $station->id))
            ->values();
        $barangays = Barangay::query()->where('municipality_id', $station->municipality_id)->orderBy('name')->get();
        $pendingApplications = $this->pendingApplications()->count();

        return view('logistics.station-hub.riders', compact('station', 'assignments', 'availableRiders', 'barangays', 'pendingApplications'));
    }

    public function assignRider(Request $request)
    {
        $station = $this->station();
        $data = $request->validate([
            'rider_id' => ['required', 'integer', 'exists:users,id'],
            'barangay_id' => [
                'required',
                'integer',
                Rule::exists('barangays', 'id')->where('municipality_id', $station->municipality_id),
            ],
        ]);
        $rider = User::query()->whereKey($data['rider_id'])
            ->where('role', 'courier')->where('status', 'approved')->firstOrFail();
        abort_if(
            $rider->branchAssignments()->where('status', 'active')->where('branch_id', '!=', $station->id)
                ->whereDoesntHave('branch', $this->legacyBranches())->exists(),
            409,
            'This rider is already assigned to another active station.',
        );

        $this->attachRider($rider, (int) $data['barangay_id']);

        return back()->with('success', "Rider {$rider->full_name} assigned to {$station->name}.");
    }

    /** Make the rider active at this station (releasing any old pre-hub branch) and cover the barangay. */
    private function attachRider(User $rider, ?int $barangayId): void
    {
        $station = $this->station();
        DB::transaction(function () use ($rider, $barangayId, $station): void {
            $rider->branchAssignments()->where('status', 'active')->where('branch_id', '!=', $station->id)
                ->whereHas('branch', $this->legacyBranches())
                ->update(['status' => 'inactive']);
            $assignment = BranchRider::updateOrCreate(
                ['branch_id' => $station->id, 'user_id' => $rider->id],
                ['status' => 'active'],
            );
            if ($barangayId) {
                RiderBarangay::firstOrCreate(
                    ['branch_rider_id' => $assignment->id, 'barangay_id' => $barangayId],
                    ['is_primary' => ! $assignment->barangays()->exists()],
                );
            }
        });
    }

    public function importBarangays(BarangayImporter $importer)
    {
        $station = $this->station();
        try {
            $added = $importer->import($station->municipality);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['barangays' => $exception->getMessage()]);
        }

        return back()->with('success', $added > 0
            ? "Added {$added} barangays for {$station->municipality->name}."
            : "The barangay list for {$station->municipality->name} is already complete.");
    }

    /** Pending courier applications from this station's town. */
    private function pendingApplications()
    {
        return $this->station()->pendingCourierApplications();
    }

    private function ensureLocalApplicant(User $user): void
    {
        abort_unless($this->pendingApplications()->contains('id', $user->id), 404, 'This application is not pending for this station.');
    }

    public function applications()
    {
        $station = $this->station();
        $applications = $this->pendingApplications();
        $barangays = Barangay::query()->where('municipality_id', $station->municipality_id)->orderBy('name')->get();

        return view('logistics.station-hub.applications', compact('station', 'applications', 'barangays'));
    }

    public function approveApplication(Request $request, User $user, RegistrationReviewService $reviews)
    {
        $this->ensureLocalApplicant($user);
        $station = $this->station();
        $checklist = RegistrationChecklist::for($user);
        abort_if(
            collect($checklist)->contains('available', false),
            422,
            'This application is missing a required document, so it can only be disapproved.',
        );
        $data = $request->validate([
            'checklist' => ['required', 'array'],
            ...collect($checklist)->mapWithKeys(fn ($item, $key) => ["checklist.{$key}" => ['accepted']])->all(),
            'barangay_id' => ['nullable', 'integer', Rule::exists('barangays', 'id')->where('municipality_id', $station->municipality_id)],
        ], [
            'checklist.required' => 'Complete the verification checklist before approving.',
            'checklist.*.accepted' => 'Confirm every checklist item before approving.',
        ]);

        $reviews->approve($user, $request->user(), array_map(fn () => true, $data['checklist']), "Approved by {$station->name}.");
        $barangayId = isset($data['barangay_id']) ? (int) $data['barangay_id'] : null;
        $this->attachRider($user->fresh(), $barangayId);

        return redirect()->route('station-hub.applications')->with('success', "{$user->full_name} is approved and now a rider at {$station->name}."
            .($barangayId ? '' : ' Assign their barangays on the Riders page.')
            .($reviews->notified ? '' : ' The approval email could not be sent.'));
    }

    public function disapproveApplication(Request $request, User $user, RegistrationReviewService $reviews)
    {
        $this->ensureLocalApplicant($user);
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:1000']]);
        $reviews->disapprove($user, $request->user(), $data['reason']);

        return redirect()->route('station-hub.applications')->with('success', "Application of {$user->full_name} disapproved."
            .($reviews->notified ? ' They have been emailed the reason.' : ' The email could not be sent.'));
    }

    public function updateRiderStatus(Request $request, User $user)
    {
        abort_unless(
            $user->role === 'courier' && $user->branchAssignments()->where('branch_id', $this->station()->id)->exists(),
            404,
        );
        $data = $request->validate(['status' => 'required|in:approved,suspended']);
        $user->update(['status' => $data['status']]);

        return back()->with('success', "Rider {$user->full_name} is now {$data['status']}.");
    }

    public function parcels(Request $request, LogisticsRoutingService $routing)
    {
        $station = $this->station();
        $status  = (string) $request->get('status', 'all');
        $base    = $this->ordersForStation();

        $statusAliases = [
            'scanned' => 'at_sorting_center',
            'awaiting_rider' => 'sorted',
            'failed_delivery' => 'delivery_failed',
            'wrong_destination' => 'destination_exception',
        ];
        $statusFilters = [...self::PIPELINE_STATUSES, ...array_keys($statusAliases), 'awaiting_scan', 'awaiting_sort', 'verification_required', 'destination_exception', 'missing_scan', 'rider_unavailable'];
        $status = in_array($status, $statusFilters, true) ? $status : 'all';
        $filter = $statusAliases[$status] ?? $status;

        $queueCounts = [
            'ready_for_pickup' => (clone $base)->where('status', 'ready_for_pickup')->count(),
            'awaiting_scan' => $this->awaitingScanQuery(clone $base)->count(),
            'verification_required' => $this->verificationRequiredQuery(clone $base)->count(),
            'awaiting_sort' => $this->awaitingSortQuery(clone $base)->count(),
            'sorted' => (clone $base)->where('status', 'sorted')->count(),
            'linehaul' => (clone $base)->where('status', 'in_transit')->count(),
            'destination_exception' => $this->destinationExceptionQuery(clone $base)->count(),
            'assigned' => (clone $base)->where('status', 'assigned_to_rider')->count(),
            'out_for_delivery' => (clone $base)->where('status', 'out_for_delivery')->count(),
            'failed_delivery' => (clone $base)->where('status', 'delivery_failed')->count(),
            'missing_scan' => $this->missingScanQuery(clone $base)->count(),
            'rider_unavailable' => $this->riderUnavailableQuery(clone $base, $station)->count(),
        ];

        $query = (clone $base)->with([
            'buyer',
            'seller',
            'courier',
            'originBranch',
            'originHub',
            'destinationHub',
            'destinationBranch.municipality',
            'destinationBarangay',
            'parcelScans',
            'delivery.logs',
            'logisticsExceptions',
        ])->whereIn('status', self::PIPELINE_STATUSES);

        match ($filter) {
            'awaiting_scan' => $this->awaitingScanQuery($query),
            'awaiting_sort' => $this->awaitingSortQuery($query),
            'verification_required' => $this->verificationRequiredQuery($query),
            'destination_exception' => $this->destinationExceptionQuery($query),
            'missing_scan' => $this->missingScanQuery($query),
            'rider_unavailable' => $this->riderUnavailableQuery($query, $station),
            'all' => $query,
            default => $query->where('status', $filter),
        };

        $orders = $query->latest()->paginate(15);

        $eligibleCouriers = $orders->getCollection()
            ->filter(fn (Order $o) => $o->status === 'sorted')
            ->mapWithKeys(fn (Order $o) => [$o->id => $routing->eligibleCouriers($o)]);

        return view('logistics.station-hub.parcels', compact('station', 'orders', 'eligibleCouriers', 'status', 'queueCounts'));
    }

    private function awaitingScanQuery($query)
    {
        return $query->where('status', 'picked_up')->whereIn('tracking_status', self::SCANNABLE_TRACKING);
    }

    private function awaitingSortQuery($query)
    {
        return $query->where('status', 'at_sorting_center')->where('tracking_status', self::SCANNED_TRACKING);
    }

    private function missingScanQuery($query)
    {
        return $query->where('status', 'picked_up')
            ->where('updated_at', '<', now()->subHours(self::MISSING_SCAN_AFTER_HOURS));
    }

    private function verificationRequiredQuery($query)
    {
        return $query->where(function ($query) {
            $query->where(function ($query) {
                $query->where('status', 'picked_up')
                    ->where(fn ($query) => $query->whereNull('tracking_status')
                        ->orWhereNotIn('tracking_status', self::SCANNABLE_TRACKING));
            })->orWhere(function ($query) {
                $query->where('status', 'at_sorting_center')
                    ->where(fn ($query) => $query->whereNull('tracking_status')
                        ->orWhere('tracking_status', '!=', self::SCANNED_TRACKING));
            });
        });
    }

    private function destinationExceptionQuery($query)
    {
        return $query->whereIn('status', ['ready_for_pickup', 'picked_up', 'at_sorting_center', 'sorted'])
            ->where(function ($query) {
                $query->whereNull('destination_branch_id')
                    ->orWhereNull('destination_barangay_id')
                    ->orWhereDoesntHave('buyer')
                    ->orWhereHas('buyer', fn ($buyer) => $buyer
                        ->whereNull('barangay')
                        ->orWhereNull('municipality')
                        ->orWhereNull('province'))
                    ->orWhereDoesntHave('destinationBranch.municipality', fn ($municipality) => $municipality
                        ->whereHas('barangays', fn ($barangays) => $barangays
                            ->whereColumn('barangays.id', 'orders.destination_barangay_id')));
            });
    }

    /** Sorted parcels for this station's delivery area that no approved, active rider covers. */
    private function riderUnavailableQuery($query, LogisticsBranch $station)
    {
        return $query->where('status', 'sorted')
            ->where('destination_branch_id', $station->id)
            ->where(fn ($route) => $route
                ->whereNull('origin_hub_id')
                ->orWhereNull('destination_hub_id')
                ->orWhereColumn('origin_hub_id', 'destination_hub_id')
                ->orWhereNotNull('destination_hub_received_at'))
            ->whereDoesntHave('destinationBranch.riderAssignments', function ($assignment) {
                $assignment->where('status', 'active')
                    ->whereHas('rider', fn ($rider) => $rider->where('role', 'courier')->where('status', 'approved'))
                    ->whereHas('barangays', fn ($barangays) => $barangays
                        ->whereColumn('rider_barangays.barangay_id', 'orders.destination_barangay_id'));
            });
    }

    public function approvePickup(Request $request, Order $order, LogisticsService $logistics)
    {
        $this->ensureStationOrder($order);
        $logistics->approvePickup($order, $request->user(), 'logistics');

        return back()->with('success', "Pickup for {$order->order_number} approved.");
    }

    public function scanParcel(ScanParcelRequest $request, Order $order, LogisticsService $logistics)
    {
        $this->ensureStationOrder($order);
        $logistics->scanParcel($order, $request->user(), 'logistics', null, $request->validated());

        return back()->with('success', "Parcel {$order->order_number} scanned.");
    }

    public function sortParcel(Request $request, Order $order, LogisticsService $logistics)
    {
        $this->ensureStationOrder($order);
        $area = $logistics->sortParcel($order, $request->user(), 'logistics');

        return back()->with('success', "Parcel {$order->order_number} sorted for {$area}.");
    }

    public function assignCourier(AssignCourierRequest $request, Order $order, CourierAssignmentService $assignments)
    {
        $this->ensureStationOrder($order, destinationOnly: true);
        $courierId = $request->validated('courier_id');
        $courierId = $courierId === null ? null : (int) $courierId;
        $courier   = $assignments->assign($order, $request->user(), $courierId, 'logistics');

        return back()->with('success', "Parcel {$order->order_number} assigned to {$courier->full_name}.");
    }

    public function markReturned(
        Request $request,
        Order $order,
        OrderLifecycleService $lifecycle,
        DeliveryStatusTransitionPolicy $transitionPolicy,
    ) {
        $this->ensureStationOrder($order);
        DB::transaction(function () use ($request, $order, $lifecycle, $transitionPolicy): void {
            $locked = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            abort_unless(
                $locked->status === 'delivery_failed' && $locked->delivery?->status === 'return_to_sender',
                409,
                'This parcel is not awaiting a return to sender.',
            );
            abort_unless(
                $transitionPolicy->canTransition($locked->delivery->status, 'returned', 'logistics', (int) $locked->delivery->delivery_attempts),
                409,
                'This delivery cannot be confirmed as returned.',
            );
            $lifecycle->transition($locked, 'returned', $request->user()->id, 'logistics', 'Physical return to sender confirmed by logistics.', [
                'tracking_status' => 'Returned to sender',
            ]);
        });

        return back()->with('success', "Parcel {$order->order_number} marked as returned to sender.");
    }

    public function openException(OpenLogisticsExceptionRequest $request, Order $order, LogisticsExceptionService $exceptions)
    {
        $this->ensureStationOrder($order);
        $data      = $request->validated();
        $exception = $exceptions->open($order, $request->user(), $data['type'], $data['description']);

        return back()->with('success', "Logistics exception #{$exception->id} opened.");
    }

    public function resolveException(ResolveParcelExceptionRequest $request, LogisticsException $exception, LogisticsExceptionService $exceptions)
    {
        $this->ensureStationOrder($exception->order);
        $exceptions->resolve($exception, $request->user(), $request->validated('resolution'));

        return back()->with('success', "Logistics exception #{$exception->id} resolved.");
    }
}
