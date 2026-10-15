<?php

namespace App\Http\Controllers;

use App\Mail\NewMessageMail;
use App\Models\Complaint;
use App\Models\LogisticsBranch;
use App\Models\Message;
use App\Models\Municipality;
use App\Models\Order;
use App\Models\User;
use App\Services\HubOperatorService;
use App\Support\LogisticsDomains;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;

/**
 * Company (superadmin) logistics portal: manages province hubs and monitors the whole network.
 * Parcel operations live in ProvinceHubController and StationHubController.
 */
class LogisticsController extends Controller
{
    private const LEGACY_ORDER_STATUSES = ['pending', 'processing', 'shipped'];

    public function dashboard()
    {
        $company = auth()->user();
        $hubs = LogisticsBranch::query()
            ->where('logistics_id', $company->id)
            ->where('hub_type', 'province_hub')
            ->with(['municipality', 'assignedOperator', 'children'])
            ->orderBy('name')
            ->get();

        $orders = $this->companyOrders();
        $stats = [
            'province_hubs' => $hubs->count(),
            'station_hubs' => $hubs->sum(fn (LogisticsBranch $hub) => $hub->children->count()),
            'in_pipeline' => (clone $orders)->whereIn('status', ['ready_for_pickup', 'picked_up', 'at_sorting_center', 'sorted', 'in_transit', 'assigned_to_rider', 'out_for_delivery'])->count(),
            'in_transit' => (clone $orders)->where('status', 'in_transit')->count(),
            'failed' => (clone $orders)->where('status', 'delivery_failed')->count(),
        ];
        $hubsNeedingOperator = $hubs->filter(fn (LogisticsBranch $hub) => $hub->assignedOperator === null);
        $unroutedOrders = (clone $orders)
            ->whereIn('status', ['ready_for_pickup', 'picked_up', 'at_sorting_center', 'sorted'])
            ->where(fn ($query) => $query->whereNull('origin_branch_id')->orWhereNull('destination_branch_id'))
            ->count();

        return view('logistics.company-dashboard', compact('hubs', 'stats', 'hubsNeedingOperator', 'unroutedOrders'));
    }

    private function companyOrders()
    {
        return Order::query()->where('logistics_id', auth()->id());
    }

    public function module(string $module)
    {
        $definitions = [
            'complaints' => ['title' => 'Complaints', 'description' => 'Review logistics-related customer complaints.', 'query' => Complaint::with(['filer', 'against'])->latest()],
        ];
        abort_unless(isset($definitions[$module]), 404);

        $definition = $definitions[$module];
        $records = $definition['query']->paginate(20);
        $summary = collect();

        return view('logistics.module', compact('module', 'definition', 'records', 'summary'));
    }

    public function deliveryReports(Request $request)
    {
        $from = $request->get('from', now()->startOfMonth()->format('Y-m-d'));
        $to = $request->get('to', now()->format('Y-m-d'));
        $status = $this->validatedReportStatus($request);
        $statusOptions = array_values(array_unique([...Order::STATUS_LIFECYCLE, ...self::LEGACY_ORDER_STATUSES]));
        $query = $this->companyOrders()->whereBetween('created_at', [$from, $to.' 23:59:59']);
        if ($status !== 'all') {
            $query->where('status', $status);
        }

        $summaryQuery = clone $query;
        $orders = $query->with(['buyer', 'courier'])->latest()->paginate(20)->withQueryString();
        $summary = $summaryQuery->reorder()->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');

        return view('logistics.delivery-reports', compact('orders', 'summary', 'from', 'to', 'status', 'statusOptions'));
    }

    public function exportDeliveryReports(Request $request)
    {
        $from = $request->get('from', now()->startOfMonth()->format('Y-m-d'));
        $to = $request->get('to', now()->format('Y-m-d'));
        $status = $this->validatedReportStatus($request);
        $query = $this->companyOrders()->whereBetween('created_at', [$from, $to.' 23:59:59']);
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

    private function validatedReportStatus(Request $request): string
    {
        $statusOptions = ['all', ...Order::STATUS_LIFECYCLE, ...self::LEGACY_ORDER_STATUSES];

        return $request->validate([
            'status' => ['nullable', Rule::in($statusOptions)],
        ])['status'] ?? 'all';
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
            'email' => 'required|email|unique:users,email,'.auth()->id(),
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
        abort_unless(request()->attributes->get('hub_context') === 'superadmin', 404);
        $search = trim((string) $request->get('search', ''));
        $query = LogisticsBranch::with(['municipality', 'assignedOperator'])
            ->where('hub_type', 'province_hub')
            ->where('logistics_id', $request->user()->id);
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
        $branches = $query->with('children')->orderBy('name')->get();

        return view('logistics.branches', compact('branches', 'search'));
    }

    public function storeBranch(Request $request, HubOperatorService $operators)
    {
        abort_unless($request->attributes->get('hub_context') === 'superadmin', 404);
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'hub_type' => 'required|in:province_hub',
            'province' => 'required|string|max:120',
            'municipality' => 'required|string|max:120',
            'address' => 'nullable|string|max:255',
            'logistics_id' => 'prohibited',
            'subdomain' => ['required', 'string', 'max:'.LogisticsDomains::MAX_SUBDOMAIN_LENGTH, 'regex:/^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$/', Rule::unique('logistics_branches', 'subdomain')],
            'operator_first_name' => 'required|string|max:100',
            'operator_last_name' => 'required|string|max:100',
            'operator_sex' => 'required|in:Male,Female',
            'operator_email' => 'required|email|max:255|unique:users,email',
            'operator_password' => 'required|string|min:8|confirmed',
            'operator_contact_no' => ['required', 'regex:/^09\d{9}$/'],
            'operator_birthday' => 'required|date|before:-18 years',
        ]);

        $manager = $request->user();
        $municipality = Municipality::firstOrCreate([
            'province' => trim($data['province']),
            'name' => trim($data['municipality']),
        ]);

        DB::transaction(function () use ($data, $manager, $municipality, $operators): void {
            $hub = LogisticsBranch::create([
                'municipality_id' => $municipality->id,
                'logistics_id' => $manager->id,
                'hub_type' => 'province_hub',
                'parent_id' => null,
                'subdomain' => $data['subdomain'],
                'name' => trim($data['name']),
                'address' => $data['address'] ?? null,
                'status' => 'active',
            ]);
            $operators->create($hub, $data);
        });

        return redirect()->route('logistics.branches')->with('success', 'Province sorting center and operator account created.');
    }

    public function editBranch(LogisticsBranch $branch)
    {
        abort_unless(request()->attributes->get('hub_context') === 'superadmin', 404);
        abort_unless($branch->isProvinceHub() && (int) $branch->logistics_id === (int) request()->user()->id, 404);
        $branch->load(['municipality', 'assignedOperator']);

        return view('logistics.branch-edit', compact('branch'));
    }

    public function updateBranch(Request $request, LogisticsBranch $branch, HubOperatorService $operators)
    {
        abort_unless($request->attributes->get('hub_context') === 'superadmin', 404);
        abort_unless($branch->isProvinceHub() && (int) $branch->logistics_id === (int) $request->user()->id, 404);
        $operatorRequired = $branch->assignedOperator === null;
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'province' => 'required|string|max:120',
            'municipality' => 'required|string|max:120',
            'address' => 'nullable|string|max:255',
            'subdomain' => ['required', 'string', 'max:'.LogisticsDomains::MAX_SUBDOMAIN_LENGTH, 'regex:/^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$/', Rule::unique('logistics_branches', 'subdomain')->ignore($branch->id)],
            'status' => 'required|in:active,inactive',
            'operator_first_name' => [$operatorRequired ? 'required' : 'nullable', 'string', 'max:100'],
            'operator_last_name' => [$operatorRequired ? 'required' : 'nullable', 'string', 'max:100'],
            'operator_sex' => [$operatorRequired ? 'required' : 'nullable', 'in:Male,Female'],
            'operator_email' => [$operatorRequired ? 'required' : 'nullable', 'email', 'max:255', 'unique:users,email'],
            'operator_password' => [$operatorRequired ? 'required' : 'nullable', 'string', 'min:8', 'confirmed'],
            'operator_contact_no' => [$operatorRequired ? 'required' : 'nullable', 'regex:/^09\d{9}$/'],
            'operator_birthday' => [$operatorRequired ? 'required' : 'nullable', 'date', 'before:-18 years'],
        ]);

        $municipality = Municipality::firstOrCreate([
            'province' => trim($data['province']),
            'name' => trim($data['municipality']),
        ]);

        DB::transaction(function () use ($branch, $data, $municipality, $operatorRequired, $operators): void {
            $branch->update([
                'municipality_id' => $municipality->id,
                'subdomain' => $data['subdomain'],
                'name' => trim($data['name']),
                'address' => $data['address'] ?? null,
                'status' => $data['status'],
            ]);
            if ($operatorRequired) {
                $operators->create($branch, $data);
            }
        });

        return redirect()->route('logistics.branches')->with('success', 'Province hub updated.');
    }
}
