<?php

namespace App\Http\Controllers;

use App\Auth\Permission;
use App\Models\Geo\Barangay;
use App\Models\Geo\Municipality;
use App\Models\Geo\Province;
use App\Pickrush\Models\Company;
use App\Pickrush\Models\CompanyDocument;
use App\Pickrush\Services\OperationRefused;
use App\Pickrush\Services\Registration\CompanyReviewer;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Logistics companies that registered on the logistics portal: the review queue, each company's
 * application with its documents, and the administrator's decisions.
 */
class AdminLogisticsCompanyController extends Controller
{
    public const STATUS_TABS = [
        'pending' => 'Pending',
        'approved' => 'Approved',
        'rejected' => 'Rejected',
        'suspended' => 'Suspended',
        'all' => 'All',
    ];

    public function index(Request $request)
    {
        $status = array_key_exists($request->query('status'), self::STATUS_TABS) ? $request->query('status') : 'pending';
        $search = trim((string) $request->query('search', ''));

        $companies = Company::query()
            ->with('owner')
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w->where('name', 'like', "%{$search}%")
                ->orWhereHas('owner', fn ($o) => $o->where('email', 'like', "%{$search}%")->orWhere('full_name', 'like', "%{$search}%"))))
            ->orderBy($status === 'pending' ? 'submitted_at' : 'updated_at', $status === 'pending' ? 'asc' : 'desc')
            ->paginate(15)->withQueryString();

        return view('admin.logistics.companies', [
            'companies' => $companies,
            'status' => $status,
            'search' => $search,
            'statusTabs' => self::STATUS_TABS,
            'statusCounts' => collect(array_keys(self::STATUS_TABS))->mapWithKeys(fn ($key) => [
                $key => Company::when($key !== 'all', fn ($q) => $q->where('status', $key))->count(),
            ]),
        ]);
    }

    public function show(Company $company)
    {
        $company->load(['owner', 'documents' => fn ($q) => $q->orderBy('type')->orderByDesc('version'), 'reviews.reviewer']);

        return view('admin.logistics.company-detail', [
            'company' => $company,
            'owner' => $company->owner,
            'address' => $this->address($company),
            'documents' => $company->documents->groupBy('type'),
            'canDecide' => auth()->user()->can(Permission::LOGISTICS_MANAGE),
        ]);
    }

    /** Streams a submitted document to an administrator; there is no public URL for it. */
    public function document(CompanyDocument $document, AuditLogger $audit)
    {
        $disk = Storage::disk(CompanyDocument::DISK);
        abort_unless($disk->exists($document->path), 404);
        $audit->record('logistics.document.viewed', $document->company, [], ['type' => $document->type, 'version' => $document->version], Permission::LOGISTICS_VIEW);

        return $disk->response($document->path, $document->original_name, [
            'Content-Type' => $document->mime,
            'Content-Disposition' => 'inline; filename="'.addcslashes($document->original_name, '"\\').'"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function approve(Company $company, CompanyReviewer $reviewer)
    {
        return $this->attempt(fn () => $reviewer->approve($company, auth()->user()), "{$company->name} is approved and has been emailed.", $company);
    }

    public function reject(Request $request, Company $company, CompanyReviewer $reviewer)
    {
        $reason = $this->reason($request, 'Explain what the company must correct.');

        return $this->attempt(fn () => $reviewer->reject($company, auth()->user(), $reason), "{$company->name} was rejected and has been emailed the reason.", $company);
    }

    public function suspend(Request $request, Company $company, CompanyReviewer $reviewer)
    {
        $reason = $this->reason($request, 'Explain why the company is suspended.');

        return $this->attempt(fn () => $reviewer->suspend($company, auth()->user(), $reason), "{$company->name} is suspended.", $company);
    }

    public function reinstate(Company $company, CompanyReviewer $reviewer)
    {
        return $this->attempt(fn () => $reviewer->reinstate($company, auth()->user()), "{$company->name} is active again.", $company);
    }

    private function reason(Request $request, string $required): string
    {
        $request->merge(['reason' => trim((string) $request->input('reason'))]);

        return $request->validate(['reason' => ['required', 'string', 'min:10', 'max:1000']], [
            'reason.required' => $required,
            'reason.min' => 'Give a reason of at least 10 characters; the company will read it.',
        ])['reason'];
    }

    private function attempt(callable $decision, string $success, Company $company)
    {
        try {
            $decision();
        } catch (OperationRefused $e) {
            return back()->withErrors(['decision' => $e->getMessage()]);
        }

        return redirect()->route('admin.logistics.companies.show', $company)->with('success', $success);
    }

    /** @return array{province: ?string, municipality: ?string, barangay: ?string, street: ?string} */
    private function address(Company $company): array
    {
        $municipality = $company->municipality_code ? Municipality::find($company->municipality_code) : null;
        $province = $company->province_code ? Province::find($company->province_code) : null;

        return [
            'province' => $province?->isDistrict() ? "Metro Manila ({$province->name})" : $province?->name,
            'municipality' => $municipality?->name,
            'barangay' => $company->barangay_code ? Barangay::find($company->barangay_code)?->name : null,
            'street' => $company->street_address,
        ];
    }
}
