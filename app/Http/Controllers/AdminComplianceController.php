<?php

namespace App\Http\Controllers;

use App\Http\Requests\Admin\CreateComplianceCaseRequest;
use App\Http\Requests\Admin\ResolveComplianceCaseRequest;
use App\Http\Requests\Admin\SuspendSellerRequest;
use App\Models\ComplianceAction;
use App\Models\ComplianceCase;
use App\Models\User;
use App\Services\Admin\ComplianceService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class AdminComplianceController extends Controller
{
    /** SQL weight per severity, mirroring ComplianceCase::SEVERITIES. */
    private const SEVERITY_WEIGHT_SQL = "CASE severity WHEN 'low' THEN 1 WHEN 'medium' THEN 2 WHEN 'high' THEN 3 WHEN 'critical' THEN 5 ELSE 0 END";

    public function index(Request $request)
    {
        $search = trim((string) $request->query('search', ''));
        $status = in_array($request->query('status'), ['approved', 'suspended'], true) ? $request->query('status') : 'all';
        $flag = in_array($request->query('flag'), ['open_case', 'mismatch', 'warned'], true) ? $request->query('flag') : 'all';

        $query = $this->withComplianceCounts(User::where('role', 'seller')->whereIn('status', ['approved', 'suspended']));
        if ($status !== 'all') {
            $query->where('status', $status);
        }
        match ($flag) {
            'open_case' => $query->whereHas('complianceCases', fn ($q) => $q->open()),
            'mismatch' => $query->whereHas('products', fn ($q) => $this->mismatched($q)),
            'warned' => $query->whereHas('complianceActions', fn ($q) => $q->where('action', ComplianceAction::WARNING)->where('created_at', '>=', now()->subDays(90))),
            default => null,
        };
        if ($search !== '') {
            $query->where(fn ($q) => $q->where('business_name', 'like', "%{$search}%")
                ->orWhere('first_name', 'like', "%{$search}%")->orWhere('last_name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%"));
        }
        // Highest risk first.
        $query->orderByDesc('open_case_weight')->orderByDesc('recent_warnings_count')->orderByDesc('mismatched_products_count')->orderBy('business_name');

        return view('admin.compliance.index', [
            'sellers' => $query->paginate(15)->withQueryString(),
            'search' => $search,
            'status' => $status,
            'flag' => $flag,
            'openCaseCount' => ComplianceCase::open()->count(),
        ]);
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

    public function addNote(Request $request, ComplianceCase $case, ComplianceService $compliance)
    {
        Gate::authorize('addNote', $case);
        $data = $request->validate([
            'note' => ['required', 'string', 'min:5', 'max:5000'],
            'severity' => ['nullable', Rule::in(array_keys(ComplianceCase::SEVERITIES))],
            'investigate' => ['nullable', 'boolean'],
            'evidence' => ['nullable', 'array', 'max:5'],
            'evidence.*' => CreateComplianceCaseRequest::EVIDENCE_RULES,
        ]);
        $compliance->addNote($case, $request->user(), $data['note'], $request->file('evidence', []), $data['severity'] ?? null, $request->boolean('investigate'));

        return back()->with('success', 'Note added to the case.');
    }

    public function resolveCase(ResolveComplianceCaseRequest $request, ComplianceCase $case, ComplianceService $compliance)
    {
        $compliance->resolveCase($case, $request->user(), $request->validated('outcome'), $request->validated('reason'));

        return back()->with('success', "Case #{$case->id} {$request->validated('outcome')}.");
    }

    public function warn(Request $request, User $user, ComplianceService $compliance)
    {
        Gate::authorize('warnSeller', $user);
        $data = $request->validate([
            'warning' => ['required', 'string', 'min:10', 'max:1000'],
            'case_id' => ['nullable', 'integer', Rule::exists('compliance_cases', 'id')->where('seller_id', $user->id)->whereIn('status', ComplianceCase::OPEN_STATUSES)],
        ]);
        $compliance->warn($user, $request->user(), $data['warning'], isset($data['case_id']) ? ComplianceCase::find($data['case_id']) : null);

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
