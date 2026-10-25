<?php

namespace App\Http\Controllers;

use App\Auth\Permission;
use App\Http\Requests\Admin\UpdateUserStatusRequest;
use App\Models\AuditLog;
use App\Models\Complaint;
use App\Models\Order;
use App\Models\User;
use App\Models\UserStatusHistory;
use App\Policies\UserPolicy;
use App\Services\Admin\UserModerationService;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class AdminUserController extends Controller
{
    public const JOINED_FILTERS = [
        '7d' => 'Joined in the last 7 days',
        '30d' => 'Joined in the last 30 days',
        '90d' => 'Joined in the last 90 days',
        'over_90d' => 'Joined over 90 days ago',
    ];

    public const STATUSES = ['approved', 'suspended', 'deactivated', 'pending', 'disapproved'];

    public const STATUS_TABS = [
        'all' => 'All',
        'approved' => 'Active',
        'pending' => 'Pending review',
        'suspended' => 'Suspended',
        'deactivated' => 'Deactivated',
        'disapproved' => 'Disapproved',
    ];

    public const ROLE_FILTERS = ['buyer' => 'Buyers', 'seller' => 'Sellers'];

    public function index(Request $request)
    {
        $filters = $this->filters($request);
        $query = $this->filteredUsers($filters);

        return view('admin.users.index', [
            'users' => $query->latest()->orderByDesc('id')->paginate(15)->withQueryString(),
            ...$filters,
            'joinedFilters' => self::JOINED_FILTERS,
            'statusTabs' => self::STATUS_TABS,
            'statusCounts' => collect(array_keys(self::STATUS_TABS))->mapWithKeys(fn ($status) => [
                $status => User::whereIn('role', UserPolicy::MANAGED_ROLES)->when($status !== 'all', fn ($q) => $q->where('status', $status))->count(),
            ]),
            'matchingCount' => (clone $query)->count(),
            'chips' => $this->chips($request, $filters),
            'moreFiltersCount' => (int) ($filters['joined'] !== 'any'),
        ]);
    }

    /** Side-panel summary with the same status actions as the row menu, loaded into the list page. */
    public function preview(User $user)
    {
        Gate::authorize('viewAccount', $user);

        return view('admin.users.preview', [
            'user' => $user,
            'orderSummary' => $this->orderSummary($user),
            'statusHistory' => $user->statusHistories()->with('changer')->reorder()->latest('id')->take(5)->get(),
            'transitions' => self::transitionsFor($user),
        ]);
    }

    /** CSV of the accounts matching the current filters. */
    public function export(Request $request, AuditLogger $audit): StreamedResponse
    {
        $filters = $this->filters($request);
        $selected = $this->selectedIds($request);
        $query = $selected ? $this->filteredUsers($this->filters(new Request()))->whereKey($selected) : $this->filteredUsers($filters);
        $audit->record('users.exported', null, [], $selected ? ['user_ids' => $selected] : ['filters' => array_filter([
            'role' => $filters['role'] !== 'all' ? $filters['role'] : null,
            'status' => $filters['status'] !== 'all' ? $filters['status'] : null,
            'joined' => $filters['joined'] !== 'any' ? $filters['joined'] : null,
            'search' => $filters['search'] ?: null,
        ])], Permission::REPORTS_EXPORT);

        $cell = fn (?string $value) => $value !== null && preg_match('/^[\s]*[=+\-@]/', $value) ? "'".$value : $value;

        return response()->streamDownload(function () use ($query, $cell) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['id', 'name', 'email', 'contact_no', 'role', 'business', 'status', 'status_reason', 'orders', 'completed_order_value', 'joined_at']);
            $query->reorder()->orderBy('id')->chunk(500, function ($users) use ($out, $cell) {
                foreach ($users as $user) {
                    $stats = self::orderStats($user);
                    fputcsv($out, [
                        $user->id,
                        $cell($user->full_name),
                        $cell($user->email),
                        $cell($user->contact_no),
                        $user->role,
                        $cell($user->business_name),
                        $user->status,
                        $cell($user->status_reason),
                        $stats['count'],
                        number_format($stats['value'], 2, '.', ''),
                        $user->created_at?->toIso8601String(),
                    ]);
                }
            });
            fclose($out);
        }, 'user-accounts-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv']);
    }

    /** Status changes the signed-in admin may make to this account, in menu order (deactivation last). */
    public static function transitionsFor(User $user): array
    {
        $order = ['approved' => 0, 'suspended' => 1, 'deactivated' => 2];

        return collect(UserModerationService::allowedTransitions((string) $user->status))
            ->filter(fn ($to) => auth()->user()->can('updateStatus', [$user, $to]))
            ->sortBy(fn ($to) => $order[$to] ?? 9)
            ->values()->all();
    }

    /** @return array{count: ?int, value: float} orders on the account's side (purchases or sales) and their completed value */
    public static function orderStats(User $user): array
    {
        return match ($user->role) {
            'buyer' => ['count' => (int) $user->orders_as_buyer_count, 'value' => (float) $user->buyer_completed_value],
            'seller' => ['count' => (int) $user->orders_as_seller_count, 'value' => (float) $user->seller_completed_value],
            default => ['count' => null, 'value' => 0.0],
        };
    }

    public function bulkStatus(Request $request, UserModerationService $moderation)
    {
        $validated = $request->validate([
            'to_status' => ['required', 'in:'.implode(',', array_keys(UserModerationService::ACTION_LABELS))],
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
            'confirm' => ['accepted'],
            'matching_count' => ['required', 'integer', 'min:1'],
        ]);
        $selected = $this->selectedIds($request);
        $query = $selected
            ? User::whereIn('role', UserPolicy::MANAGED_ROLES)->whereKey($selected)
            : $this->filteredUsers($this->filters($request));
        $currentCount = (clone $query)->count();
        if ($currentCount !== (int) $validated['matching_count']) {
            return back()->with('warning', 'The matching account count changed. Review the refreshed results before applying this bulk action.');
        }

        $processed = 0;
        $skipped = [];
        $query->reorder()->orderBy('id')->chunkById(100, function ($users) use ($validated, $moderation, &$processed, &$skipped): void {
            foreach ($users as $user) {
                $decision = Gate::inspect('updateStatus', [$user, $validated['to_status']]);
                if (! $decision->allowed()) {
                    $skipped[] = $user->full_name.': '.$decision->message();

                    continue;
                }

                try {
                    $moderation->changeStatus($user, request()->user(), $validated['to_status'], $validated['reason']);
                    $processed++;
                } catch (HttpExceptionInterface $exception) {
                    if (! in_array($exception->getStatusCode(), [403, 404, 409], true)) {
                        throw $exception;
                    }
                    $skipped[] = $user->full_name.': '.$exception->getMessage();
                }
            }
        });

        return back()->with('success', "{$processed} account(s) updated.")
            ->with('bulkSkipped', array_slice($skipped, 0, 20))
            ->with('bulkSkippedCount', count($skipped));
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
            'transitions' => collect(self::transitionsFor($user)),
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
            default => null,
        };
        if (! $column) {
            return null;
        }

        $orders = Order::where($column, $user->id);
        $byStatus = (clone $orders)->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        return [
            'perspective' => ['buyer_id' => 'Purchases', 'seller_id' => 'Sales'][$column],
            'total' => (int) $byStatus->sum(),
            'completed' => (int) ($byStatus['completed'] ?? 0),
            'in_progress' => (int) $byStatus->except(['completed', 'delivered', 'cancelled', 'returned', 'delivery_failed'])->sum(),
            'problems' => (int) $byStatus->only(['cancelled', 'returned', 'delivery_failed'])->sum(),
            'completed_value' => (float) (clone $orders)->where('status', 'completed')->sum('amount'),
            'recent' => (clone $orders)->latest()->take(5)->get(['id', 'order_number', 'product_name', 'amount', 'status', 'created_at']),
        ];
    }

    /** Up to 500 explicitly selected account ids (array or comma list); empty means "all matching". */
    private function selectedIds(Request $request): array
    {
        $ids = $request->input('user_ids', []);
        $ids = is_string($ids) ? explode(',', $ids) : (array) $ids;

        return collect($ids)->filter(fn ($id) => is_scalar($id) && ctype_digit((string) $id) && (int) $id > 0)
            ->map(fn ($id) => (int) $id)->unique()->take(500)->values()->all();
    }

    /** @return list<array{label: string, url: string}> */
    private function chips(Request $request, array $filters): array
    {
        $without = fn (string ...$keys) => route('admin.users', $request->except([...$keys, 'page']));

        return array_values(array_filter([
            $filters['search'] !== '' ? ['label' => "Search: \u{201C}{$filters['search']}\u{201D}", 'url' => $without('search')] : null,
            $filters['role'] !== 'all' ? ['label' => 'Role: '.self::ROLE_FILTERS[$filters['role']], 'url' => $without('role')] : null,
            $filters['joined'] !== 'any' ? ['label' => self::JOINED_FILTERS[$filters['joined']], 'url' => $without('joined')] : null,
        ]));
    }

    private function filters(Request $request): array
    {
        return [
            'role' => in_array($request->input('role'), UserPolicy::MANAGED_ROLES, true) ? $request->input('role') : 'all',
            'status' => in_array($request->input('filter_status', $request->input('status')), self::STATUSES, true) ? $request->input('filter_status', $request->input('status')) : 'all',
            'joined' => array_key_exists($request->input('joined'), self::JOINED_FILTERS) ? $request->input('joined') : 'any',
            'search' => trim((string) $request->input('search', '')),
        ];
    }

    private function filteredUsers(array $filters)
    {
        $completed = fn ($orders) => $orders->where('status', 'completed');
        $latestChange = fn (string $column) => UserStatusHistory::select($column)
            ->whereColumn('user_status_histories.user_id', 'users.id')->latest('id')->limit(1);
        $query = User::whereIn('role', UserPolicy::MANAGED_ROLES)
            ->select('users.*')
            ->addSelect(['status_changed_at' => $latestChange('created_at'), 'status_reason' => $latestChange('reason')])
            ->withCount(['ordersAsBuyer', 'ordersAsSeller'])
            ->withSum(['ordersAsBuyer as buyer_completed_value' => $completed], 'amount')
            ->withSum(['ordersAsSeller as seller_completed_value' => $completed], 'amount');
        if ($filters['role'] !== 'all') {
            $query->where('role', $filters['role']);
        }
        if ($filters['status'] !== 'all') {
            $query->where('status', $filters['status']);
        }
        match ($filters['joined']) {
            '7d' => $query->where('created_at', '>=', now()->subDays(7)),
            '30d' => $query->where('created_at', '>=', now()->subDays(30)),
            '90d' => $query->where('created_at', '>=', now()->subDays(90)),
            'over_90d' => $query->where('created_at', '<', now()->subDays(90)),
            default => null,
        };
        if ($filters['search'] !== '') {
            $search = $filters['search'];
            $query->where(fn ($q) => $q->whereLike('first_name', "%{$search}%")
                ->orWhereLike('last_name', "%{$search}%")
                ->orWhereLike('email', "%{$search}%")
                ->orWhereLike('business_name', "%{$search}%")
                ->orWhereLike('contact_no', "%{$search}%"));
        }

        return $query;
    }
}
