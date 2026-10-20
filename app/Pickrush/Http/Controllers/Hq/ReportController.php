<?php

namespace App\Pickrush\Http\Controllers\Hq;

use App\Http\Controllers\Controller;
use App\Pickrush\Models\AuditLog;
use App\Pickrush\Services\Hq\NetworkMetrics;
use Illuminate\Http\Request;

/** Hub performance and failure reasons, on screen and as CSV for Excel. */
class ReportController extends Controller
{
    public function index(Request $request, NetworkMetrics $metrics)
    {
        [$period, $from, $to] = $this->period($request);

        return view('pickrush.hq.reports', [
            'period' => $period,
            'periods' => DashboardController::PERIODS,
            'ranking' => $metrics->hubRanking($from, $to),
            'failures' => $metrics->failureReasons($from, $to),
            'summary' => $metrics->summary($from, $to),
            'sla' => (float) config('pickrush.policies.sla_success_rate'),
        ]);
    }

    public function export(Request $request, NetworkMetrics $metrics)
    {
        [$period, $from, $to] = $this->period($request);
        $rows = $metrics->hubRanking($from, $to);
        AuditLog::record(null, 'reports.exported', [], ['report' => 'hub_performance', 'period' => $period]);

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");   // BOM so Excel reads UTF-8 (ñ in place names)
            fputcsv($out, ['Station', 'Province', 'Delivered', 'Returned', 'Failed attempts', 'Success rate %'], escape: '');
            foreach ($rows as $row) {
                fputcsv($out, [$row->hub->name, $row->hub->province_name, $row->delivered, $row->returned, $row->failed, $row->success_rate ?? ''], escape: '');
            }
            fclose($out);
        }, "pickrush-hub-performance-{$period}-".now()->format('Ymd').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function period(Request $request): array
    {
        $period = array_key_exists($request->query('period'), DashboardController::PERIODS) ? $request->query('period') : 'month';

        return [$period, ...DashboardController::range($period)];
    }
}
