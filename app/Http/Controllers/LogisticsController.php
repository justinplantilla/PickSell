<?php

namespace App\Http\Controllers;

use App\Http\Requests\AssignCourierRequest;
use App\Http\Requests\OpenLogisticsExceptionRequest;
use App\Http\Requests\ResolveParcelExceptionRequest;
use App\Http\Requests\ScanParcelRequest;
use App\Mail\RegistrationApprovedMail;
use App\Mail\RegistrationDisapprovedMail;
use App\Mail\NewMessageMail;
use App\Models\Order;
use App\Models\LogisticsException;
use App\Models\LogisticsBranch;
use App\Models\Municipality;
use App\Models\BranchRider;
use App\Models\RiderBarangay;
use App\Models\Complaint;
use App\Models\Message;
use App\Models\User;
use App\Services\CourierAssignmentService;
use App\Services\LogisticsService;
use App\Services\LogisticsExceptionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class LogisticsController extends Controller
{
    private const MISSING_SCAN_AFTER_HOURS = 48;

    public function dashboard()
    {
        $stats = [
            'pending_couriers' => User::where('role', 'courier')->where('status', 'pending')->count(),
            'approved_couriers' => User::where('role', 'courier')->where('status', 'approved')->count(),
            'to_sort' => Order::whereIn('status', ['ready_for_pickup', 'picked_up', 'at_sorting_center'])->count(),
            'assigned' => Order::whereIn('status', ['assigned_to_rider', 'out_for_delivery'])->count(),
        ];

        $orders = Order::with(['buyer', 'courier'])->where('logistics_id', auth()->id())
            ->whereIn('status', ['ready_for_pickup', 'picked_up', 'at_sorting_center', 'sorted', 'assigned_to_rider', 'out_for_delivery', 'delivered', 'completed'])
            ->latest()->take(10)->get();
        return view('logistics.dashboard', compact('stats', 'orders'));
    }

    public function module(string $module)
    {
        $definitions = [
            'coverage' => ['title' => 'Barangay Coverage', 'description' => 'See which riders cover each barangay.', 'query' => RiderBarangay::with(['barangay.municipality', 'assignment.rider', 'assignment.branch'])],
            'assignments' => ['title' => 'Rider Assignments', 'description' => 'Review branch and barangay assignments for every rider.', 'query' => BranchRider::with(['branch.municipality', 'rider', 'barangays.barangay'])],
            'tracking' => ['title' => 'Delivery Tracking', 'description' => 'Monitor parcels as they move from branch to doorstep.', 'query' => Order::with(['buyer', 'courier', 'destinationBranch', 'destinationBarangay'])->latest()],
            'riders' => ['title' => 'Active Riders', 'description' => 'Review approved and suspended riders and their delivery territories.', 'query' => User::where('role', 'courier')->whereIn('status', ['approved', 'suspended'])->with('branchAssignments.branch')],
            'delivery-reports' => ['title' => 'Delivery Reports', 'description' => 'Delivery volume and status summary.', 'query' => Order::query()],
            'branch-reports' => ['title' => 'Branch Reports', 'description' => 'Branch coverage and assignment summary.', 'query' => LogisticsBranch::with(['municipality', 'riderAssignments'])],
            'complaints' => ['title' => 'Complaints', 'description' => 'Review logistics-related customer complaints.', 'query' => Complaint::with(['filer', 'against'])->latest()],
            'messages' => ['title' => 'Messages', 'description' => 'Recent platform conversations involving logistics.', 'query' => Message::with(['sender', 'receiver'])->latest()],
        ];
        abort_unless(isset($definitions[$module]), 404);

        $definition = $definitions[$module];
        $records = $module === 'delivery-reports'
            ? collect()
            : $definition['query']->paginate(20);
        $summary = $module === 'delivery-reports'
            ? Order::selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status')
            : collect();

        if ($module === 'riders') {
            return view('logistics.riders', compact('records'));
        }

        return view('logistics.module', compact('module', 'definition', 'records', 'summary'));
    }

    public function deliveryReports(Request $request)
    {
        $from = $request->get('from', now()->startOfMonth()->format('Y-m-d'));
        $to = $request->get('to', now()->format('Y-m-d'));
        $status = $request->get('status', 'all');
        $query = Order::whereBetween('created_at', [$from, $to . ' 23:59:59']);
        if ($status !== 'all') {
            $query->where('status', $status);
        }

        $summaryQuery = clone $query;
        $orders = $query->with(['buyer', 'courier'])->latest()->paginate(20)->withQueryString();
        $summary = $summaryQuery->reorder()->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');

        return view('logistics.delivery-reports', compact('orders', 'summary', 'from', 'to', 'status'));
    }

    public function exportDeliveryReports(Request $request)
    {
        $from = $request->get('from', now()->startOfMonth()->format('Y-m-d'));
        $to = $request->get('to', now()->format('Y-m-d'));
        $status = $request->get('status', 'all');
        $query = Order::whereBetween('created_at', [$from, $to . ' 23:59:59']);
        if ($status !== 'all') {
            $query->where('status', $status);
        }

        $orders = $query->with(['buyer', 'courier'])->latest()->get();
        return response()->streamDownload(function () use ($orders) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Order', 'Status', 'Tracking', 'Buyer', 'Destination', 'Rider', 'Amount', 'Created At']);
            foreach ($orders as $order) {
                fputcsv($handle, [
                    $order->order_number,
                    $order->status,
                    $order->tracking_status,
                    $order->buyer?->full_name,
                    collect([$order->buyer?->barangay, $order->buyer?->municipality, $order->buyer?->province])->filter()->join(', '),
                    $order->courier?->full_name,
                    $order->amount,
                    $order->created_at?->toDateTimeString(),
                ]);
            }
            fclose($handle);
        }, 'picksell-delivery-reports.csv', ['Content-Type' => 'text/csv']);
    }

    public function account()
    {
        return view('logistics.account-edit', ['user' => auth()->user()]);
    }

    public function updateAccount(Request $request)
    {
        $data = $request->validate([
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'middle_initial' => 'nullable|string|max:5',
            'sex' => 'required|in:Male,Female',
            'email' => 'required|email|unique:users,email,' . auth()->id(),
            'contact_no' => ['required', 'regex:/^09\d{9}$/'],
            'birthday' => 'required|date',
            'province' => 'required|string|max:120',
            'municipality' => 'required|string|max:120',
            'barangay' => 'required|string|max:120',
            'street' => 'nullable|string|max:255',
            'house_no' => 'nullable|string|max:100',
        ]);
        auth()->user()->update($data);
        return back()->with('success', 'Account details updated.');
    }

    public function updatePassword(Request $request)
    {
        $data = $request->validate([
            'current_password' => 'required',
            'password' => 'required|min:8|confirmed',
        ]);
        abort_unless(Hash::check($data['current_password'], auth()->user()->password), 422, 'Current password is incorrect.');
        auth()->user()->update(['password' => Hash::make($data['password'])]);
        return back()->with('success', 'Password updated.');
    }

    public function messages(Request $request)
    {
        $logistics = auth()->user();
        $contactIds = Message::where(fn ($query) => $query->where('sender_id', $logistics->id)->orWhere('receiver_id', $logistics->id))
            ->get()->map(fn ($message) => $message->sender_id === $logistics->id ? $message->receiver_id : $message->sender_id)->unique();
        $contacts = User::whereIn('id', $contactIds)->where('status', 'approved')->orderBy('first_name')->get();
        $activeUser = $request->filled('user') ? User::find($request->integer('user')) : $contacts->first();
        $messages = collect();
        if ($activeUser) {
            Message::where('sender_id', $activeUser->id)->where('receiver_id', $logistics->id)->update(['read' => true]);
            $messages = Message::where(fn ($query) => $query->where('sender_id', $logistics->id)->where('receiver_id', $activeUser->id))
                ->orWhere(fn ($query) => $query->where('sender_id', $activeUser->id)->where('receiver_id', $logistics->id))
                ->orderBy('created_at')->get();
        }
        return view('logistics.chat', compact('contacts', 'activeUser', 'messages'));
    }

    public function sendMessage(Request $request)
    {
        $data = $request->validate(['receiver_id' => 'required|exists:users,id', 'body' => 'required|string|max:2000']);
        $message = Message::create(['sender_id' => auth()->id(), 'receiver_id' => $data['receiver_id'], 'body' => $data['body'], 'read' => false]);
        try {
            Mail::to($message->receiver->email)->send(new NewMessageMail($message));
        } catch (\Throwable $exception) {
            Log::warning('Logistics message email could not be sent.', ['error' => $exception->getMessage()]);
        }
        return back()->with('success', 'Message sent.');
    }

    public function branches(Request $request)
    {
        $search = trim((string) $request->get('search', ''));
        $query = LogisticsBranch::with(['municipality', 'logistics', 'riderAssignments.rider', 'riderAssignments.barangays.barangay']);
        if ($search !== '') {
            $query->where(function ($builder) use ($search) {
                $builder->where('name', 'like', "%{$search}%")
                    ->orWhereHas('municipality', fn ($municipality) => $municipality
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('province', 'like', "%{$search}%"))
                    ->orWhereHas('logistics', fn ($manager) => $manager
                        ->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%"));
            });
        }
        $branches = $query->orderBy('name')->get();
        $logisticsManagers = User::where('role', 'logistics')->where('status', 'approved')->orderBy('last_name')->get();

        return view('logistics.branches', compact('branches', 'logisticsManagers', 'search'));
    }

    public function storeBranch(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'province' => 'required|string|max:120',
            'municipality' => 'required|string|max:120',
            'address' => 'nullable|string|max:255',
            'logistics_id' => 'nullable|exists:users,id',
        ]);

        $manager = !empty($data['logistics_id'])
            ? User::where('id', $data['logistics_id'])->where('role', 'logistics')->where('status', 'approved')->firstOrFail()
            : null;
        $municipality = Municipality::firstOrCreate([
            'province' => trim($data['province']),
            'name' => trim($data['municipality']),
        ]);

        LogisticsBranch::create([
            'municipality_id' => $municipality->id,
            'logistics_id' => $manager?->id,
            'name' => trim($data['name']),
            'address' => $data['address'] ?? null,
            'status' => 'active',
        ]);

        return redirect()->route('logistics.branches')->with('success', 'Branch added successfully.');
    }

    public function editBranch(LogisticsBranch $branch)
    {
        $branch->load('municipality');
        $logisticsManagers = User::where('role', 'logistics')->where('status', 'approved')->orderBy('last_name')->get();

        return view('logistics.branch-edit', compact('branch', 'logisticsManagers'));
    }

    public function updateBranch(Request $request, LogisticsBranch $branch)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120', Rule::unique('logistics_branches', 'name')->where(fn ($query) => $query->where('municipality_id', $branch->municipality_id))->ignore($branch->id)],
            'province' => 'required|string|max:120',
            'municipality' => 'required|string|max:120',
            'address' => 'nullable|string|max:255',
            'logistics_id' => 'nullable|exists:users,id',
            'status' => 'required|in:active,inactive',
        ]);

        $manager = !empty($data['logistics_id'])
            ? User::where('id', $data['logistics_id'])->where('role', 'logistics')->where('status', 'approved')->firstOrFail()
            : null;
        $municipality = Municipality::firstOrCreate([
            'province' => trim($data['province']),
            'name' => trim($data['municipality']),
        ]);

        $branch->update([
            'municipality_id' => $municipality->id,
            'logistics_id' => $manager?->id,
            'name' => trim($data['name']),
            'address' => $data['address'] ?? null,
            'status' => $data['status'],
        ]);

        return redirect()->route('logistics.branches')->with('success', 'Branch updated successfully.');
    }

    public function applications(Request $request)
    {
        $status = $request->get('status', 'pending');
        $search = trim((string) $request->get('search', ''));
        $query = User::where('role', 'courier');
        if ($status !== 'all') {
            $query->where('status', $status);
        }
        if ($search !== '') {
            $query->where(function ($builder) use ($search) {
                $builder->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('municipality', 'like', "%{$search}%")
                    ->orWhere('barangay', 'like', "%{$search}%")
                    ->orWhere('delivery_area', 'like', "%{$search}%")
                    ->orWhere('plate_number', 'like', "%{$search}%");
            });
        }

        $users = $query->latest()->paginate(15);
        return view('logistics.applications', compact('users', 'status', 'search'));
    }

    public function approveCourier(User $user)
    {
        abort_if($user->role !== 'courier', 404);
        $user->update(['status' => 'approved']);
        Mail::to($user->email)->send(new RegistrationApprovedMail($user));
        return back()->with('success', "Courier {$user->full_name} approved.");
    }

    public function disapproveCourier(Request $request, User $user)
    {
        abort_if($user->role !== 'courier', 404);
        $data = $request->validate(['reason' => 'required|string|max:500']);
        $user->update(['status' => 'disapproved']);

        try {
            Mail::to($user->email)->send(new RegistrationDisapprovedMail($user, $data['reason']));
        } catch (\Throwable $exception) {
            Log::warning('Courier disapproval email could not be sent.', ['user_id' => $user->id, 'error' => $exception->getMessage()]);
        }

        return back()->with('success', "Courier {$user->full_name} disapproved.");
    }

    public function updateRiderStatus(Request $request, User $user)
    {
        abort_if($user->role !== 'courier', 404);
        $data = $request->validate(['status' => 'required|in:approved,suspended']);
        $user->update(['status' => $data['status']]);

        return back()->with('success', "Rider {$user->full_name} is now {$data['status']}.");
    }

    public function parcels(Request $request)
    {
        $status = (string) $request->get('status', 'all');
        $baseQuery = Order::where('logistics_id', auth()->id());
        $pipelineStatuses = ['ready_for_pickup', 'picked_up', 'at_sorting_center', 'sorted', 'assigned_to_rider', 'out_for_delivery', 'delivery_failed', 'delivered', 'completed'];
        $statusAliases = [
            'awaiting_scan' => 'picked_up',
            'scanned' => 'at_sorting_center',
            'awaiting_sort' => 'at_sorting_center',
            'awaiting_rider' => 'sorted',
            'failed_delivery' => 'delivery_failed',
        ];
        $statusFilters = [...$pipelineStatuses, ...array_keys($statusAliases), 'verification_required', 'destination_exception', 'wrong_destination', 'missing_scan', 'rider_unavailable'];
        $status = in_array($status, $statusFilters, true) ? $status : 'all';
        $filterStatus = $statusAliases[$status] ?? ($status === 'wrong_destination' ? 'destination_exception' : $status);

        $queueCounts = [
            'awaiting_scan' => (clone $baseQuery)->where('status', 'picked_up')->count(),
            'scanned' => (clone $baseQuery)->where('status', 'at_sorting_center')->count(),
            'verification_required' => $this->verificationRequiredQuery(clone $baseQuery)->count(),
            'awaiting_sort' => (clone $baseQuery)->where('status', 'at_sorting_center')->count(),
            'sorted' => (clone $baseQuery)->where('status', 'sorted')->count(),
            'destination_exception' => $this->destinationExceptionQuery(clone $baseQuery)->count(),
            'awaiting_rider' => (clone $baseQuery)->where('status', 'sorted')->count(),
            'assigned' => (clone $baseQuery)->where('status', 'assigned_to_rider')->count(),
            'out_for_delivery' => (clone $baseQuery)->where('status', 'out_for_delivery')->count(),
            'failed_delivery' => (clone $baseQuery)->where('status', 'delivery_failed')->count(),
            'missing_scan' => (clone $baseQuery)->where('status', 'picked_up')->where('updated_at', '<', now()->subHours(self::MISSING_SCAN_AFTER_HOURS))->count(),
            'rider_unavailable' => User::where('role', 'courier')->where('status', 'approved')->exists()
                ? 0
                : (clone $baseQuery)->where('status', 'sorted')->count(),
        ];

        $query = (clone $baseQuery)->with(['buyer', 'seller', 'courier', 'parcelScans'])
            ->whereIn('status', $pipelineStatuses);
        if (in_array($filterStatus, $pipelineStatuses, true)) {
            $query->where('status', $filterStatus);
        } elseif ($filterStatus === 'verification_required') {
            $this->verificationRequiredQuery($query);
        } elseif ($filterStatus === 'destination_exception') {
            $this->destinationExceptionQuery($query);
        } elseif ($filterStatus === 'missing_scan') {
            $query->where('status', 'picked_up')->where('updated_at', '<', now()->subHours(self::MISSING_SCAN_AFTER_HOURS));
        } elseif ($filterStatus === 'rider_unavailable') {
            $query->where('status', 'sorted');
            if (User::where('role', 'courier')->where('status', 'approved')->exists()) {
                $query->whereRaw('1 = 0');
            }
        }

        $orders = $query->latest()->paginate(15);
        $couriers = User::where('role', 'courier')->where('status', 'approved')->orderBy('delivery_area')->orderBy('last_name')->get();
        $hasActiveCouriers = $couriers->isNotEmpty();
        return view('logistics.sorting-center', compact('orders', 'couriers', 'status', 'queueCounts', 'hasActiveCouriers'));
    }

    private function verificationRequiredQuery($query)
    {
        return $query->where(function ($query) {
            $query->where(function ($query) {
                $query->where('status', 'picked_up')
                    ->where(function ($query) {
                        $query->whereNull('tracking_status')
                            ->orWhereNotIn('tracking_status', ['Pickup approved by logistics', 'Handed over to courier']);
                    });
            })->orWhere(function ($query) {
                $query->where('status', 'at_sorting_center')
                    ->where(function ($query) {
                        $query->whereNull('tracking_status')
                            ->orWhere('tracking_status', '!=', 'Received and scanned at sorting center');
                    });
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
                    ->orWhereHas('buyer', function ($buyer) {
                        $buyer->whereNull('barangay')
                            ->orWhereNull('municipality')
                            ->orWhereNull('province');
                    });
            });
    }

    public function scanParcel(ScanParcelRequest $request, Order $order, LogisticsService $logistics)
    {
        $logistics->scanParcel($order, $request->user(), 'logistics', null, $request->validated());

        return back()->with('success', "Parcel {$order->order_number} scanned.");
    }

    public function sortParcel(Request $request, Order $order, LogisticsService $logistics)
    {
        $area = $logistics->sortParcel($order, $request->user(), 'logistics');

        return back()->with('success', "Parcel {$order->order_number} sorted for {$area}.");
    }

    public function approvePickup(Request $request, Order $order, LogisticsService $logistics)
    {
        $logistics->approvePickup($order, $request->user(), 'logistics');

        return back()->with('success', "Pickup for {$order->order_number} approved.");
    }

    public function assignCourier(AssignCourierRequest $request, Order $order, CourierAssignmentService $assignments)
    {
        $courierId = $request->validated('courier_id');
        $courierId = $courierId === null ? null : (int) $courierId;
        $courier = $assignments->assign($order, $request->user(), $courierId, 'logistics');

        return back()->with('success', "Parcel {$order->order_number} assigned to {$courier->full_name}.");
    }

    public function openException(
        OpenLogisticsExceptionRequest $request,
        Order $order,
        LogisticsExceptionService $exceptions,
    ) {
        $data = $request->validated();
        $exception = $exceptions->open($order, $request->user(), $data['type'], $data['description']);

        return back()->with('success', "Logistics exception #{$exception->id} opened.");
    }

    public function resolveException(
        ResolveParcelExceptionRequest $request,
        LogisticsException $exception,
        LogisticsExceptionService $exceptions,
    ) {
        $exceptions->resolve($exception, $request->user(), $request->validated('resolution'));

        return back()->with('success', "Logistics exception #{$exception->id} resolved.");
    }
}
