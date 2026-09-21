<?php

namespace App\Http\Controllers;

use App\Mail\RegistrationApprovedMail;
use App\Mail\RegistrationDisapprovedMail;
use App\Models\Order;
use App\Models\LogisticsBranch;
use App\Models\Municipality;
use App\Models\BranchRider;
use App\Models\RiderBarangay;
use App\Models\Complaint;
use App\Models\Message;
use App\Models\User;
use App\Services\LogisticsRoutingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;

class LogisticsController extends Controller
{
    public function dashboard()
    {
        $stats = [
            'pending_couriers' => User::where('role', 'courier')->where('status', 'pending')->count(),
            'approved_couriers' => User::where('role', 'courier')->where('status', 'approved')->count(),
            'to_sort' => Order::where('status', 'shipped')->whereNull('courier_id')->count(),
            'assigned' => Order::where('status', 'shipped')->whereNotNull('courier_id')->count(),
        ];

        $orders = Order::with(['buyer', 'courier'])->where('logistics_id', auth()->id())->whereIn('status', ['shipped', 'completed'])->latest()->take(10)->get();
        return view('logistics.dashboard', compact('stats', 'orders'));
    }

    public function module(string $module)
    {
        $definitions = [
            'coverage' => ['title' => 'Barangay Coverage', 'description' => 'See which riders cover each barangay.', 'query' => RiderBarangay::with(['barangay.municipality', 'assignment.rider', 'assignment.branch'])],
            'assignments' => ['title' => 'Rider Assignments', 'description' => 'Review branch and barangay assignments for every rider.', 'query' => BranchRider::with(['branch.municipality', 'rider', 'barangays.barangay'])],
            'tracking' => ['title' => 'Delivery Tracking', 'description' => 'Monitor parcels as they move from branch to doorstep.', 'query' => Order::with(['buyer', 'courier', 'destinationBranch', 'destinationBarangay'])->latest()],
            'riders' => ['title' => 'Active Riders', 'description' => 'Review approved riders and their delivery territories.', 'query' => User::where('role', 'courier')->where('status', 'approved')->with('branchAssignments.branch')],
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

        return view('logistics.module', compact('module', 'definition', 'records', 'summary'));
    }

    public function account()
    {
        return view('logistics.account', ['user' => auth()->user()]);
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

    public function parcels(Request $request)
    {
        $status = $request->get('status', 'all');
        $query = Order::with(['buyer', 'seller', 'courier'])->where('logistics_id', auth()->id())->whereIn('status', ['shipped', 'completed']);
        if ($status !== 'all') {
            $query->where('status', $status);
        }

        $orders = $query->latest()->paginate(15);
        $couriers = User::where('role', 'courier')->where('status', 'approved')->orderBy('delivery_area')->orderBy('last_name')->get();
        return view('logistics.parcels', compact('orders', 'couriers', 'status'));
    }

    public function scanParcel(Order $order)
    {
        abort_if($order->status !== 'shipped', 422, 'Only seller handovers can be scanned.');
        $order->update(['tracking_status' => 'Received and scanned at sorting center']);
        return back()->with('success', "Parcel {$order->order_number} scanned.");
    }

    public function assignCourier(Request $request, Order $order, LogisticsRoutingService $routing)
    {
        $data = $request->validate(['courier_id' => 'nullable|exists:users,id']);
        $courier = $data['courier_id']
            ? User::where('id', $data['courier_id'])->where('role', 'courier')->where('status', 'approved')->firstOrFail()
            : $routing->suggestedCourier($order);
        abort_if(!$courier, 422, 'No active rider is assigned to this barangay yet.');
        abort_if($order->status !== 'shipped', 422, 'Only parcels in the sorting center can be assigned.');
        abort_if($order->tracking_status !== 'Received and scanned at sorting center' && !$order->courier_id, 422, 'Scan the parcel before assigning it.');

        $area = $order->buyer ? trim(collect([$order->buyer->municipality, $order->buyer->province])->filter()->join(', ')) : 'Unspecified area';
        $order->update([
            'courier_id' => $courier->id,
            'assigned_at' => now(),
            'tracking_status' => 'Sorted for ' . $area . '; assigned to ' . $courier->full_name,
        ]);

        return back()->with('success', "Parcel {$order->order_number} assigned to {$courier->full_name}.");
    }
}
