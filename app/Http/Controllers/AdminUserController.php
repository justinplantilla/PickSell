<?php

namespace App\Http\Controllers;

use App\Http\Requests\Admin\UpdateUserStatusRequest;
use App\Models\AuditLog;
use App\Models\Complaint;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Policies\UserPolicy;
use App\Services\Admin\UserModerationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AdminUserController extends Controller
{
    public const JOINED_FILTERS = [
        '7d' => 'Joined in the last 7 days',
        '30d' => 'Joined in the last 30 days',
        '90d' => 'Joined in the last 90 days',
        'over_90d' => 'Joined over 90 days ago',
    ];

    public const STATUSES = ['approved', 'suspended', 'deactivated', 'pending', 'disapproved'];

    public function index(Request $request)
    {
        $role = in_array($request->query('role'), UserPolicy::MANAGED_ROLES, true) ? $request->query('role') : 'all';
        $status = in_array($request->query('status'), self::STATUSES, true) ? $request->query('status') : 'all';
        $joined = array_key_exists($request->query('joined'), self::JOINED_FILTERS) ? $request->query('joined') : 'any';
        $search = trim((string) $request->query('search', ''));

        $query = User::whereIn('role', UserPolicy::MANAGED_ROLES)
            ->withCount(['ordersAsBuyer', 'ordersAsSeller', 'ordersAsCourier']);
        if ($role !== 'all') {
            $query->where('role', $role);
        }
        if ($status !== 'all') {
            $query->where('status', $status);
        }
        match ($joined) {
            '7d' => $query->where('created_at', '>=', now()->subDays(7)),
            '30d' => $query->where('created_at', '>=', now()->subDays(30)),
            '90d' => $query->where('created_at', '>=', now()->subDays(90)),
            'over_90d' => $query->where('created_at', '<', now()->subDays(90)),
            default => null,
        };
        if ($search !== '') {
            $query->where(fn ($q) => $q->where('first_name', 'like', "%{$search}%")
                ->orWhere('last_name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")
                ->orWhere('business_name', 'like', "%{$search}%")
                ->orWhere('contact_no', 'like', "%{$search}%"));
        }

        return view('admin.users.index', [
            'users' => $query->latest()->paginate(15)->withQueryString(),
            'role' => $role,
            'status' => $status,
            'joined' => $joined,
            'search' => $search,
            'joinedFilters' => self::JOINED_FILTERS,
            'statuses' => self::STATUSES,
        ]);
    }

    public function show(User $user)
    {
        Gate::authorize('viewAccount', $user);

        return view('admin.users.show', [
            'user' => $user,
            'statusHistory' => $user->statusHistories()->with('changer')->get(),
            'registrationReviews' => $user->registrationReviews()->with('reviewer')->get(),
            'orderSummary' => $this->orderSummary($user),
            'complaints' => Complaint::with(['filer', 'against'])
                ->where(fn ($q) => $q->where('filed_by', $user->id)->orWhere('against_user_id', $user->id))
                ->latest()->take(10)->get(),
            'compliance' => $user->role === 'seller'
                ? $user->complianceActions()->with(['actor', 'complianceCase'])->latest()->latest('id')->take(20)->get()
                : collect(),
            'auditHistory' => AuditLog::with('actor')
                ->where('subject_type', $user->getMorphClass())->where('subject_id', $user->id)
                ->latest('id')->take(50)->get(),
            'transitions' => collect(UserModerationService::allowedTransitions((string) $user->status))
                ->filter(fn ($to) => auth()->user()->can('updateStatus', [$user, $to]))
                ->values(),
        ]);
    }

    public function updateStatus(UpdateUserStatusRequest $request, User $user, UserModerationService $moderation)
    {
        $status = $request->validated('status');
        $moderation->changeStatus($user, $request->user(), $status, $request->validated('reason'));

        $message = "Account status of {$user->full_name} updated to {$status}.";

        return $moderation->notified
            ? back()->with('success', $message)
            : back()->with('success', $message)->with('warning', 'The status was updated, but the notification email could not be sent. Check the mail settings.');
    }

    /** Orders the account took part in, from the side that matches its role. */
    private function orderSummary(User $user): ?array
    {
        $column = match ($user->role) {
            'buyer' => 'buyer_id',
            'seller' => 'seller_id',
            'courier' => 'courier_id',
            default => null,
        };
        if (! $column) {
            return null;
        }

        $orders = Order::where($column, $user->id);
        $byStatus = (clone $orders)->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        return [
            'perspective' => ['buyer_id' => 'Purchases', 'seller_id' => 'Sales', 'courier_id' => 'Deliveries'][$column],
            'total' => (int) $byStatus->sum(),
            'completed' => (int) ($byStatus['completed'] ?? 0),
            'in_progress' => (int) $byStatus->except(['completed', 'delivered', 'cancelled', 'returned', 'delivery_failed'])->sum(),
            'problems' => (int) $byStatus->only(['cancelled', 'returned', 'delivery_failed'])->sum(),
            'completed_value' => $column === 'courier_id' ? null : (float) (clone $orders)->where('status', 'completed')->sum('amount'),
            'recent' => (clone $orders)->latest()->take(5)->get(['id', 'order_number', 'product_name', 'amount', 'status', 'created_at']),
        ];
    }
}
