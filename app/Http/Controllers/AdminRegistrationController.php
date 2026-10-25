<?php

namespace App\Http\Controllers;

use App\Auth\Permission;
use App\Http\Requests\Admin\ApproveRegistrationRequest;
use App\Http\Requests\Admin\DisapproveRegistrationRequest;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\Admin\RegistrationReviewService;
use App\Services\AuditLogger;
use App\Support\RegistrationChecklist;
use App\Support\SellerTypes;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class AdminRegistrationController extends Controller
{
    /** Logistics companies are Pickrush accounts now and never register on the marketplace. */
    private const REGISTRATION_ROLES = ['buyer', 'seller'];

    public const STATUS_TABS = ['pending' => 'Pending', 'approved' => 'Approved', 'disapproved' => 'Disapproved', 'all' => 'All'];

    public const ROLE_FILTERS = ['buyer' => 'Buyer', 'seller' => 'Seller'];

    public const DATE_FILTERS = [
        'today' => 'Submitted today',
        '7d' => 'Last 7 days',
        '30d' => 'Last 30 days',
        'older_7d' => 'Waiting over 7 days',
    ];

    /** A pending application waiting longer than this is flagged as overdue. */
    public const OVERDUE_DAYS = 7;

    /** Short names for document fields, for the Documents column. */
    private const DOCUMENT_SHORT_LABELS = [
        'id_upload' => 'ID',
        'business_permit' => 'Permit',
        'dti_certificate' => 'DTI',
        'sec_certificate' => 'SEC',
        'bir_certificate' => 'BIR',
    ];

    public function index(Request $request)
    {
        $filters = $this->filters($request);
        $query = $this->filteredApplicants($filters);
        $users = $query->paginate(15)->withQueryString();

        return view('admin.registrations', [
            'users' => $users,
            ...$filters,
            'statusTabs' => self::STATUS_TABS,
            'statusCounts' => collect(array_keys(self::STATUS_TABS))->mapWithKeys(fn ($status) => [
                $status => $this->applicants()->when($status !== 'all', fn ($q) => $q->where('status', $status))->count(),
            ]),
            'overdueCount' => $this->applicants()->where('status', 'pending')->where('created_at', '<', now()->subDays(self::OVERDUE_DAYS))->count(),
            'dateFilters' => self::DATE_FILTERS,
            'documentStatus' => $users->getCollection()->mapWithKeys(fn (User $user) => [$user->id => self::documentStatus($user)]),
            'duplicates' => $this->sameNameCounts($users->getCollection()),
            'chips' => $this->chips($request, $filters),
            'matchingCount' => (clone $query)->count(),
        ]);
    }

    public function show(User $user)
    {
        Gate::authorize('viewRegistration', $user);

        return view('admin.application-detail', [
            'user' => $user,
            'checklist' => RegistrationChecklist::for($user),
            'documents' => RegistrationChecklist::documents($user),
            'reviews' => $user->registrationReviews()->with('reviewer')->get(),
            'auditHistory' => AuditLog::with('actor')
                ->where('subject_type', $user->getMorphClass())
                ->where('subject_id', $user->id)
                ->latest('id')->take(50)->get(),
        ]);
    }

    /** Side-panel summary with documents and a quick disapproval, loaded into the queue page. */
    public function preview(User $user)
    {
        Gate::authorize('viewRegistration', $user);
        $sameName = $this->sameNameQuery(collect([$user]))->whereKeyNot($user->id)->orderByDesc('id')->take(5)->get();

        return view('admin.registrations-preview', [
            'user' => $user,
            'documents' => RegistrationChecklist::documents($user),
            'documentStatus' => self::documentStatus($user),
            'reviews' => $user->registrationReviews()->with('reviewer')->take(3)->get(),
            'sameName' => $sameName,
        ]);
    }

    /** CSV of the applicants matching the current filters. Document files are not included. */
    public function export(Request $request, AuditLogger $audit): StreamedResponse
    {
        $filters = $this->filters($request);
        $query = $this->filteredApplicants($filters);
        $audit->record('registrations.exported', null, [], ['filters' => array_filter([
            'status' => $filters['status'],
            'role' => $filters['role'] !== 'all' ? $filters['role'] : null,
            'date' => $filters['date'] !== 'any' ? $filters['date'] : null,
            'search' => $filters['search'] ?: null,
        ])], Permission::REPORTS_EXPORT);

        $cell = fn (?string $value) => $value !== null && preg_match('/^[\s]*[=+\-@]/', $value) ? "'".$value : $value;

        return response()->streamDownload(function () use ($query, $cell) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['id', 'name', 'email', 'role', 'seller_type', 'business', 'status', 'submitted_at', 'documents_uploaded', 'documents_required', 'decision', 'reviewer', 'reviewed_at']);
            $query->chunk(500, function ($users) use ($out, $cell) {
                foreach ($users as $user) {
                    $documents = self::documentStatus($user);
                    $review = $user->latestRegistrationReview;
                    fputcsv($out, [
                        $user->id,
                        $cell($user->full_name),
                        $cell($user->email),
                        $user->role,
                        $user->seller_type,
                        $cell($user->business_name),
                        $user->status,
                        $user->created_at?->toIso8601String(),
                        $documents['uploaded'],
                        $documents['required'],
                        $review?->decision,
                        $cell($review?->reviewer?->full_name),
                        $review?->reviewed_at?->toIso8601String(),
                    ]);
                }
            });
            fclose($out);
        }, 'registrations-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv']);
    }

    public function approve(ApproveRegistrationRequest $request, User $user, RegistrationReviewService $reviews)
    {
        $reviews->approve($user, $request->user(), $request->confirmedChecklist(), $request->validated('reason'));

        return $this->decided($reviews, "Application of {$user->full_name} approved.");
    }

    public function disapprove(DisapproveRegistrationRequest $request, User $user, RegistrationReviewService $reviews)
    {
        $reviews->disapprove($user, $request->user(), $request->validated('reason'), $request->confirmedChecklist());

        return $this->decided($reviews, "Application of {$user->full_name} disapproved.");
    }

    /**
     * Disapproves the selected pending applications with one reason (spam, test or duplicate sign-ups).
     * There is deliberately no bulk approval: every approval goes through the checklist on its own page.
     */
    public function bulkDisapprove(Request $request, RegistrationReviewService $reviews)
    {
        $validated = $request->validate([
            'user_ids' => ['required', 'array', 'min:1', 'max:100'],
            'user_ids.*' => ['integer'],
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
            'confirm' => ['accepted'],
            'matching_count' => ['required', 'integer', 'min:1'],
        ]);
        $applicants = $this->applicants()->whereKey(array_unique($validated['user_ids']))->orderBy('id')->get();
        if ($applicants->count() !== (int) $validated['matching_count']) {
            return back()->with('warning', 'Some selected applications no longer exist. Review the refreshed queue before trying again.');
        }

        $processed = 0;
        $skipped = [];
        $emailFailures = 0;
        foreach ($applicants as $applicant) {
            $decision = Gate::inspect('disapproveRegistration', $applicant);
            if (! $decision->allowed()) {
                $skipped[] = $applicant->full_name.': '.$decision->message();

                continue;
            }
            try {
                $reviews->disapprove($applicant, $request->user(), trim($validated['reason']));
                $processed++;
                $emailFailures += $reviews->notified ? 0 : 1;
            } catch (HttpExceptionInterface $exception) {
                if (! in_array($exception->getStatusCode(), [403, 404, 409], true)) {
                    throw $exception;
                }
                $skipped[] = $applicant->full_name.': '.$exception->getMessage();
            }
        }

        $response = back()->with('success', "{$processed} application(s) disapproved.")
            ->with('bulkSkipped', array_slice($skipped, 0, 20))
            ->with('bulkSkippedCount', count($skipped));

        return $emailFailures
            ? $response->with('warning', "{$emailFailures} applicant(s) could not be emailed. Check the mail settings.")
            : $response;
    }

    /**
     * Uploaded vs required documents, from the same checklist reviewers confirm.
     *
     * @return array{uploaded: int, required: int, missing: list<string>, complete: bool}
     */
    public static function documentStatus(User $user): array
    {
        $documents = collect(RegistrationChecklist::for($user))->filter(fn ($item) => $item['document'] !== null);
        $missing = $documents->reject(fn ($item) => $item['available'])
            ->map(fn ($item) => self::DOCUMENT_SHORT_LABELS[$item['document']] ?? $item['document'])->values()->all();

        return [
            'uploaded' => $documents->count() - count($missing),
            'required' => $documents->count(),
            'missing' => $missing,
            'complete' => $missing === [],
        ];
    }

    public static function sellerTypeLabel(User $user): ?string
    {
        return $user->role === 'seller' ? (SellerTypes::label($user->seller_type) ?? 'Legacy seller') : null;
    }

    private function decided(RegistrationReviewService $reviews, string $message)
    {
        return $reviews->notified
            ? back()->with('success', "{$message} The applicant has been notified.")
            : back()->with('success', $message)->with('warning', 'The decision was saved, but the notification email could not be sent. Check the mail settings.');
    }

    private function applicants(): Builder
    {
        return User::query()->whereIn('role', self::REGISTRATION_ROLES);
    }

    private function filters(Request $request): array
    {
        return [
            'status' => array_key_exists((string) $request->query('status'), self::STATUS_TABS) ? $request->query('status') : 'pending',
            'role' => array_key_exists((string) $request->query('role'), self::ROLE_FILTERS) ? $request->query('role') : 'all',
            'date' => array_key_exists((string) $request->query('date'), self::DATE_FILTERS) ? $request->query('date') : 'any',
            'search' => trim((string) $request->query('search', '')),
        ];
    }

    private function filteredApplicants(array $filters): Builder
    {
        $query = $this->applicants()->with('latestRegistrationReview.reviewer');
        if ($filters['role'] !== 'all') {
            $query->where('role', $filters['role']);
        }
        if ($filters['status'] !== 'all') {
            $query->where('status', $filters['status']);
        }
        match ($filters['date']) {
            'today' => $query->whereDate('created_at', today()),
            '7d' => $query->where('created_at', '>=', now()->subDays(7)),
            '30d' => $query->where('created_at', '>=', now()->subDays(30)),
            'older_7d' => $query->where('created_at', '<', now()->subDays(self::OVERDUE_DAYS)),
            default => null,
        };
        if ($filters['search'] !== '') {
            $search = $filters['search'];
            $query->where(fn ($q) => $q->whereLike('first_name', "%{$search}%")
                ->orWhereLike('last_name', "%{$search}%")
                ->orWhereLike('email', "%{$search}%")
                ->orWhereLike('business_name', "%{$search}%"));
        }

        // The pending queue is first-come, first-served; decided lists show the newest first.
        return $filters['status'] === 'pending' ? $query->oldest()->orderBy('id') : $query->latest()->orderByDesc('id');
    }

    /** Other marketplace accounts with the same first and last name (case and spacing ignored). */
    private function sameNameQuery(Collection $users): Builder
    {
        $names = $users->map(fn (User $user) => [mb_strtolower(trim((string) $user->first_name)), mb_strtolower(trim((string) $user->last_name))])
            ->unique(fn ($pair) => implode("\0", $pair))->values();

        return $this->applicants()->where(function ($query) use ($names) {
            foreach ($names as [$first, $last]) {
                $query->orWhere(fn ($q) => $q->whereRaw('LOWER(TRIM(first_name)) = ?', [$first])->whereRaw('LOWER(TRIM(last_name)) = ?', [$last]));
            }
            if ($names->isEmpty()) {
                $query->whereRaw('1 = 0');
            }
        });
    }

    /** @return Collection<int, int> applicant id => number of other accounts sharing the name */
    private function sameNameCounts(Collection $users): Collection
    {
        $key = fn ($user) => mb_strtolower(trim((string) $user->first_name))."\0".mb_strtolower(trim((string) $user->last_name));
        $byName = $this->sameNameQuery($users)->get(['id', 'first_name', 'last_name'])->groupBy($key);

        return $users->mapWithKeys(fn (User $user) => [$user->id => max(0, ($byName->get($key($user))?->count() ?? 1) - 1)]);
    }

    /** @return list<array{label: string, url: string}> */
    private function chips(Request $request, array $filters): array
    {
        $without = fn (string ...$keys) => route('admin.registrations', $request->except([...$keys, 'page']));

        return array_values(array_filter([
            $filters['search'] !== '' ? ['label' => "Search: \u{201C}{$filters['search']}\u{201D}", 'url' => $without('search')] : null,
            $filters['role'] !== 'all' ? ['label' => 'Role: '.self::ROLE_FILTERS[$filters['role']], 'url' => $without('role')] : null,
            $filters['date'] !== 'any' ? ['label' => self::DATE_FILTERS[$filters['date']], 'url' => $without('date')] : null,
        ]));
    }
}
