<?php

namespace App\Http\Controllers;

use App\Auth\Permission;
use App\Models\Order;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Reports\FinancialReportService;
use App\Services\Reports\OperationalReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class AdminReportController extends Controller
{
    public function index(
        Request $request,
        OperationalReportService $operationalReports,
        FinancialReportService $financialReports,
    ) {
        $filters = $this->filters($request);
        $from = CarbonImmutable::parse($filters['from'])->startOfDay();
        $to = CarbonImmutable::parse($filters['to'])->endOfDay();
        $sellers = User::query()->where('role', 'seller')->orderBy('first_name')->orderBy('last_name')->get(['id', 'first_name', 'last_name']);
        $tab = $filters['tab'];

        if ($tab === 'financial') {
            if (! Schema::hasTable('financial_transactions')) {
                return redirect()->route('admin.reports', [
                    'tab' => 'operational',
                    'from' => $filters['from'],
                    'to' => $filters['to'],
                    'status' => 'all',
                    'seller_id' => $filters['seller_id'],
                ])->with('warning', 'Financial reports are temporarily unavailable until the financial ledger migration is applied.');
            }

            $data = $financialReports->report($from, $to, $filters['status'], $filters['seller_id']);

            return view('admin.reports.financial', compact('data', 'filters', 'sellers'));
        }

        $data = $operationalReports->report($from, $to, $filters['status'], $filters['seller_id']);

        return view('admin.reports.operational', compact('data', 'filters', 'sellers'));
    }

    public function exportPdf(
        Request $request,
        OperationalReportService $operationalReports,
        FinancialReportService $financialReports,
        AuditLogger $audit,
    ) {
        $filters = $this->filters($request, true);
        $from = CarbonImmutable::parse($filters['from'])->startOfDay();
        $to = CarbonImmutable::parse($filters['to'])->endOfDay();
        $type = in_array($filters['type'], ['sales', 'commission'], true) ? 'financial' : $filters['type'];

        if ($type === 'financial') {
            abort_unless(
                Schema::hasTable('financial_transactions'),
                503,
                'Financial reports are temporarily unavailable until the financial ledger migration is applied.',
            );

            $data = $financialReports->report($from, $to, $filters['status'], $filters['seller_id']);
        } else {
            $data = $operationalReports->report($from, $to, $filters['status'], $filters['seller_id']);
        }

        $pdf = Pdf::loadView("admin.pdf.{$type}-report", compact('data', 'filters'))
            ->setPaper('a4', 'landscape');
        $filename = "picksell-{$type}-report-{$filters['from']}-to-{$filters['to']}.pdf";
        $response = $pdf->download($filename);

        $audit->record('report.exported', null, [], [
            'report_type' => $type,
            'from' => $filters['from'],
            'to' => $filters['to'],
            'status' => $filters['status'],
            'seller_id' => $filters['seller_id'],
        ], Permission::REPORTS_EXPORT);

        return $response;
    }

    private function filters(Request $request, bool $forExport = false): array
    {
        $defaultFrom = now()->startOfMonth()->toDateString();
        $defaultTo = now()->toDateString();
        $requestedType = $request->query('type');
        $tab = $request->query('tab', 'operational');
        $financial = in_array($requestedType, ['financial', 'sales', 'commission'], true)
            || (! $forExport && $tab === 'financial');
        $request->merge(['tab' => $tab]);
        $request->merge([
            'from' => $request->query('from', $defaultFrom),
            'to' => $request->query('to', $defaultTo),
            'status' => $request->query('status', $financial ? 'posted' : 'all'),
            'seller_id' => $request->query('seller_id') ?: null,
        ]);

        $availableStatuses = $financial
            ? ['all', 'pending', 'posted', 'voided']
            : ['all', ...OperationalReportService::statuses()];
        $rules = [
            ...(! $forExport ? ['tab' => ['required', Rule::in(['operational', 'financial'])]] : []),
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
            'status' => ['required', 'string', Rule::in($availableStatuses)],
            'seller_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('role', 'seller')],
        ];
        if ($forExport) {
            $rules['type'] = ['required', 'string', Rule::in(['operational', 'financial', 'sales', 'commission'])];
        }
        $filters = $request->validate($rules);
        $filters['seller_id'] = isset($filters['seller_id']) ? (int) $filters['seller_id'] : null;

        return $filters;
    }
}
