<?php

namespace App\Http\Controllers;

use App\Auth\Permission;
use App\Http\Requests\Admin\CreateComplianceCaseRequest;
use App\Http\Requests\Admin\AddComplianceNoteRequest;
use App\Http\Requests\Admin\ResolveComplianceCaseRequest;
use App\Http\Requests\Admin\WarnSellerRequest;
use App\Http\Requests\Admin\SuspendSellerRequest;
use App\Models\ComplianceAction;
use App\Models\ComplianceCase;
use App\Models\User;
use App\Services\Admin\ComplianceService;
use App\Services\AuditLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminComplianceController extends Controller
{
    /** SQL weight per severity, mirroring ComplianceCase::SEVERITIES. */
    private const SEVERITY_WEIGHT_SQL = "CASE severity WHEN 'low' THEN 1 WHEN 'medium' THEN 2 WHEN 'high' THEN 3 WHEN 'critical' THEN 5 ELSE 0 END";

    public const STATUS_FILTERS = ['approved' => 'Active', 'suspended' => 'Suspended'];

    public const FLAG_FILTERS = ['open_case' => 'With open cases', 'mismatch' => 'Listings outside registered category', 'warned' => 'Warned in last 90 days'];

    public const RISK_LABELS = ['high' => 'High', 'medium' => 'Medium', 'low' => 'Low', 'none' => 'No flags'];

    public function index(Request $request)
    {
        $filters = $this->sellerFilters($request);
        $query = $this->filteredSellers($filters);

        return view('admin.compliance.index', [
            'sellers' => $query->paginate(15)->withQueryString(),
            ...$filters,
            'riskSummary' => $this->riskSummary(),
            'openCaseCount' => ComplianceCase::open()->count(),
            'matchingCount' => (clone $query)->count(),
            'chips' => $this->sellerChips($request, $filters),
            'moreFiltersCount' => (int) ($filters['flag'] !== 'all'),
        ]);
    }

    /** Side-panel summary of one seller's compliance signals, loaded into the list page. */
    public function sellerPreview(User $user)
    {
        Gate::authorize('viewSellerCompliance', $user);
        $seller = $this->withComplianceCounts(User::whereKey($user->id))->firstOrFail();

        return view('admin.compliance._preview', [
            'seller' => $seller,
            'risk' => self::riskFor($seller),
            'mismatched' => $seller->products()->join('users', 'users.id', '=', 'products.seller_id')
                ->where(fn ($q) => $this->mismatched($q))->select('products.*')->orderBy('products.name')->take(5)->get(),
            'openCases' => $seller->complianceCases()->open()->latest()->take(5)->get(),
            'warnings' => $seller->complianceActions()->with('actor')->where('action', ComplianceAction::WARNING)->latest()->latest('id')->take(3)->get(),
        ]);
    }

    /** CSV of the sellers matching the current filters, with every risk factor. */
    public function export(Request $request, AuditLogger $audit): StreamedResponse
    {
        $filters = $this->sellerFilters($request);
        $query = $this->filteredSellers($filters);
        $audit->record('compliance.sellers_exported', null, [], ['filters' => array_filter([
            'search' => $filters['search'] ?: null,
            'status' => $filters['status'] !== 'all' ? $filters['status'] : null,
            'flag' => $filters['flag'] !== 'all' ? $filters['flag'] : null,
            'risk' => $filters['risk'] !== 'all' ? $filters['risk'] : null,
        ])], Permission::REPORTS_EXPORT);

        $cell = fn (?string $value) => $value !== null && preg_match('/^[\s]*[=+\-@]/', $value) ? "'".$value : $value;

        return response()->streamDownload(function () use ($query, $cell) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['seller_id', 'business', 'owner', 'email', 'registered_category', 'status', 'risk_level', 'risk_score',
                'off_category_listings', 'open_cases', 'warnings_90d', 'admin_archived_products', 'open_complaints']);
            $query->chunk(500, function ($sellers) use ($out, $cell) {
                foreach ($sellers as $seller) {
                    $risk = self::riskFor($seller);
                    fputcsv($out, [
                        $seller->id,
                        $cell($seller->business_name),
                        $cell($seller->full_name),
                        $cell($seller->email),
                        $cell($seller->line_of_business),
                        $seller->status,
                        $risk['level'],
                        $risk['score'],
                        $seller->mismatched_products_count,
                        $seller->open_cases_count,
                        $seller->recent_warnings_count,
                        $seller->held_products_count,
                        $seller->open_complaints_count,
                    ]);
                }
            });
            fclose($out);
        }, 'seller-compliance-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv']);
    }

    public function cases(Request $request)
    {
        $status = in_array($request->query('status'), ['open', 'investigating', 'resolved', 'dismissed', 'all'], true) ? $request->query('status') : 'active';
        $severity = array_key_exists($request->query('severity'), ComplianceCase::SEVERITIES) ? $request->query('severity') : 'all';
        $type = array_key_exists($request->query('type'), ComplianceCase::TYPES) ? $request->query('type') : 'all';

        $query = ComplianceCase::with(['seller', 'opener', 'product']);
        match ($status) {
            'active' => $query->open(),
            'all' => null,
            default => $query->where('status', $status),
        };
        if ($severity !== 'all') {
            $query->where('severity', $severity);
        }
        if ($type !== 'all') {
            $query->where('type', $type);
        }
        $query->orderByRaw(self::SEVERITY_WEIGHT_SQL . ' DESC')->latest();

        return view('admin.compliance.cases', [
            'cases' => $query->paginate(20)->withQueryString(),
            'status' => $status,
            'severity' => $severity,
            'type' => $type,
            'openCaseCount' => ComplianceCase::open()->count(),
        ]);
    }

    public function seller(User $user)
    {
        Gate::authorize('viewSellerCompliance', $user);
        $seller = $this->withComplianceCounts(User::whereKey($user->id))->firstOrFail();

        $products = $seller->products()->with('latestStatusModeration')->orderBy('status')->orderBy('name')->get();
        $registered = mb_strtolower(trim((string) $seller->line_of_business));
        $products->each(fn ($product) => $product->setAttribute('category_matches',
            $registered === '' || $product->category === null || mb_strtolower(trim($product->category)) === $registered));

        $actions = $seller->complianceActions()->with(['actor', 'complianceCase'])->latest()->latest('id')->get();

        return view('admin.compliance.seller', [
            'seller' => $seller,
            'risk' => $this->riskFor($seller),
            'products' => $products,
            'mismatched' => $products->where('category_matches', false)->where('status', 'active'),
            'heldProducts' => $products->filter->isUnderAdminHold(),
            'warnings' => $actions->where('action', ComplianceAction::WARNING),
            'suspensions' => $actions->whereIn('action', [ComplianceAction::SUSPENSION, ComplianceAction::REINSTATEMENT]),
            'statusHistory' => $seller->statusHistories()->with('changer')->whereIn('to_status', ['suspended', 'approved', 'deactivated'])->get(),
            'cases' => $seller->complianceCases()->with('opener')->latest()->get(),
            'history' => $actions,
            'openCases' => $seller->complianceCases()->open()->latest()->get(),
            'prefillProduct' => request()->integer('product') ?: null,
        ]);
    }

    public function showCase(ComplianceCase $case)
    {
        Gate::authorize('view', $case);
        $case->load(['seller', 'opener', 'product', 'actions.actor']);

        return view('admin.compliance.case', ['case' => $case]);
    }

    public function openCase(CreateComplianceCaseRequest $request, User $user, ComplianceService $compliance)
    {
        $case = $compliance->openCase($user, $request->user(), $request->validated(), $request->file('evidence', []));

        return redirect()->route('admin.compliance.cases.show', $case)->with('success', "Compliance case #{$case->id} opened for {$user->business_name}.");
    }

    public function addNote(AddComplianceNoteRequest $request, ComplianceCase $case, ComplianceService $compliance)
    {
        $data = $request->validated();
        $compliance->addNote($case, $request->user(), $data['note'], $request->file('evidence', []), $data['severity'] ?? null, $request->boolean('investigate'));

        return back()->with('success', 'Note added to the case.');
    }

    public function resolveCase(ResolveComplianceCaseRequest $request, ComplianceCase $case, ComplianceService $compliance)
    {
        $compliance->resolveCase($case, $request->user(), $request->validated('outcome'), $request->validated('reason'));

        return back()->with('success', "Case #{$case->id} {$request->validated('outcome')}.");
    }

    public function warn(WarnSellerRequest $request, User $user, ComplianceService $compliance)
    {
        $data = $request->validated();
        $case = isset($data['case_id'])
            ? ComplianceCase::query()
                ->where('seller_id', $user->id)
                ->whereIn('status', ComplianceCase::OPEN_STATUSES)
                ->findOrFail($data['case_id'])
            : null;
        $compliance->warn($user, $request->user(), $data['warning'], $case);

        return $this->done($compliance, "Warning issued to {$user->business_name}.");
    }

    public function suspend(SuspendSellerRequest $request, User $user, ComplianceService $compliance)
    {
        $case = $request->validated('case_id') ? ComplianceCase::find($request->validated('case_id')) : null;
        $request->isReinstatement()
            ? $compliance->reinstate($user, $request->user(), $request->validated('reason'), $case)
            : $compliance->suspend($user, $request->user(), $request->validated('reason'), $case);

        return $this->done($compliance, $request->isReinstatement() ? "{$user->business_name} reinstated." : "{$user->business_name} suspended.");
    }

    /** Evidence files are private; stream them only to compliance viewers. */
    public function evidence(ComplianceAction $action, int $index)
    {
        Gate::authorize('viewSellerCompliance', $action->seller);
        $file = $action->attachments[$index] ?? abort(404);
        abort_unless(Storage::disk(ComplianceService::EVIDENCE_DISK)->exists($file['path']), 404);

        return Storage::disk(ComplianceService::EVIDENCE_DISK)->response($file['path'], $file['name']);
    }

    private function done(ComplianceService $compliance, string $message)
    {
        return $compliance->notified
            ? back()->with('success', "{$message} The seller has been notified.")
            : back()->with('success', $message)->with('warning', 'Recorded, but the notification email could not be sent. Check the mail settings.');
    }

    private function sellerFilters(Request $request): array
    {
        return [
            'search' => trim((string) $request->query('search', '')),
            'status' => array_key_exists((string) $request->query('status'), self::STATUS_FILTERS) ? $request->query('status') : 'all',
            'flag' => array_key_exists((string) $request->query('flag'), self::FLAG_FILTERS) ? $request->query('flag') : 'all',
            'risk' => array_key_exists((string) $request->query('risk'), self::RISK_LABELS) ? $request->query('risk') : 'all',
        ];
    }

    private function filteredSellers(array $filters): Builder
    {
        $query = $this->scoredSellers();
        if ($filters['status'] !== 'all') {
            $query->where('status', $filters['status']);
        }
        match ($filters['flag']) {
            'open_case' => $query->whereHas('complianceCases', fn ($q) => $q->open()),
            'mismatch' => $query->where('mismatched_products_count', '>', 0),
            'warned' => $query->where('recent_warnings_count', '>', 0),
            default => null,
        };
        if ($filters['risk'] !== 'all') {
            $this->whereRiskLevel($query, $filters['risk']);
        }
        if ($filters['search'] !== '') {
            $search = $filters['search'];
            $query->where(fn ($q) => $q->whereLike('business_name', "%{$search}%")
                ->orWhereLike('first_name', "%{$search}%")->orWhereLike('last_name', "%{$search}%")
                ->orWhereLike('email', "%{$search}%"));
        }

        // Highest risk first; the raw signals break ties.
        return $query->orderByDesc('risk_score')->orderByDesc('open_case_weight')->orderByDesc('recent_warnings_count')
            ->orderByDesc('mismatched_products_count')->orderBy('business_name');
    }

    /**
     * Sellers (active or suspended) with their compliance counts and the same risk score ComplianceService::risk()
     * computes, as a `risk_score` column so the list can be sorted, filtered and summarised by it in SQL.
     */
    private function scoredSellers(): Builder
    {
        $counts = $this->withComplianceCounts(User::where('role', 'seller')->whereIn('status', ['approved', 'suspended']));
        $cap = fn (string $column) => "CASE WHEN {$column} > ".ComplianceService::FACTOR_CAP.' THEN '.ComplianceService::FACTOR_CAP." ELSE {$column} END";
        $score = 'open_case_weight + recent_warnings_count + '.$cap('mismatched_products_count').' + '.$cap('held_products_count').' + '.$cap('open_complaints_count');
        $scored = User::query()->fromSub($counts, 'users')->select('users.*')->selectRaw("({$score}) as risk_score");

        return User::query()->fromSub($scored, 'users');
    }

    private function whereRiskLevel(Builder $query, string $level): Builder
    {
        $levels = array_keys(ComplianceService::RISK_LEVELS);
        $minimum = ComplianceService::RISK_LEVELS[$level];
        $above = array_search($level, $levels, true) - 1;
        $query->where('risk_score', '>=', $minimum);
        if ($above >= 0) {
            $query->where('risk_score', '<', ComplianceService::RISK_LEVELS[$levels[$above]]);
        }

        return $query;
    }

    /** @return array<string, int> sellers per risk level, highest first */
    private function riskSummary(): array
    {
        return collect(array_keys(ComplianceService::RISK_LEVELS))
            ->mapWithKeys(fn (string $level) => [$level => $this->whereRiskLevel($this->scoredSellers(), $level)->count()])
            ->all();
    }

    /** @return list<array{label: string, url: string}> */
    private function sellerChips(Request $request, array $filters): array
    {
        $without = fn (string ...$keys) => route('admin.compliance', $request->except([...$keys, 'page']));

        return array_values(array_filter([
            $filters['search'] !== '' ? ['label' => "Search: \u{201C}{$filters['search']}\u{201D}", 'url' => $without('search')] : null,
            $filters['risk'] !== 'all' ? ['label' => 'Risk: '.self::RISK_LABELS[$filters['risk']], 'url' => $without('risk')] : null,
            $filters['status'] !== 'all' ? ['label' => 'Status: '.self::STATUS_FILTERS[$filters['status']], 'url' => $without('status')] : null,
            $filters['flag'] !== 'all' ? ['label' => self::FLAG_FILTERS[$filters['flag']], 'url' => $without('flag')] : null,
        ]));
    }

    private function withComplianceCounts(Builder $query): Builder
    {
        return $query
            ->addSelect(['open_case_weight' => ComplianceCase::selectRaw('COALESCE(SUM(' . self::SEVERITY_WEIGHT_SQL . '), 0)')
                ->whereColumn('compliance_cases.seller_id', 'users.id')->whereIn('status', ComplianceCase::OPEN_STATUSES)])
            ->withCount([
                'complianceCases as open_cases_count' => fn ($q) => $q->open(),
                'complianceActions as recent_warnings_count' => fn ($q) => $q->where('action', ComplianceAction::WARNING)->where('created_at', '>=', now()->subDays(90)),
                'products as mismatched_products_count' => fn ($q) => $this->mismatched($q),
                'products as held_products_count' => fn ($q) => $q->where('status', 'archived')
                    ->whereHas('latestStatusModeration', fn ($m) => $m->where('action', 'archived')),
                'complaintsAgainst as open_complaints_count' => fn ($q) => $q->whereIn('status', ['open', 'under_review']),
            ]);
    }

    /** Active listings whose category differs from the seller's registered line of business. */
    private function mismatched($query)
    {
        return $query->where('products.status', 'active')
            ->whereNotNull('products.category')
            ->whereRaw('LOWER(TRIM(products.category)) <> LOWER(TRIM(COALESCE(users.line_of_business, products.category)))');
    }

    public static function riskFor(User $seller): array
    {
        return ComplianceService::risk((int) $seller->open_case_weight, (int) $seller->recent_warnings_count,
            (int) $seller->mismatched_products_count, (int) $seller->held_products_count, (int) $seller->open_complaints_count);
    }
}
