<?php

namespace App\Pickrush\Http\Controllers\Hq;

use App\Pickrush\Http\Controllers\PortalController;
use App\Pickrush\Models\AuditLog;
use App\Pickrush\Models\CodRemittance;
use App\Pickrush\Models\HubRemittance;
use App\Pickrush\Services\Hq\Finance;
use App\Pickrush\Services\Hq\NetworkMetrics;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/** Station remittances to confirm, cash outstanding, variances, revenue and what is owed to shippers. */
class FinanceController extends PortalController
{
    public function index(Request $request, Finance $finance, NetworkMetrics $metrics)
    {
        $period = array_key_exists($request->query('period'), DashboardController::PERIODS) ? $request->query('period') : 'month';
        [$from, $to] = DashboardController::range($period);

        return view('pickrush.hq.finance', [
            'period' => $period,
            'periods' => DashboardController::PERIODS,
            // Newest first; a contested remittance counts from when the station contested it.
            'pending' => HubRemittance::with('station', 'settles')->where('status', 'submitted')
                ->orderByRaw('coalesce(contested_at, created_at) desc')->orderByDesc('id')->get(),
            'disputes' => HubRemittance::with('station', 'topUps')->where('status', 'disputed')->whereNull('settles_id')->latest('confirmed_at')->orderByDesc('id')->get(),
            'recent' => HubRemittance::with('station')->where('status', '!=', 'submitted')->latest('confirmed_at')->limit(20)->get(),
            'cash' => $metrics->cashOutstanding(),
            'settlement' => $finance->settlement($from, $to),
            'variances' => CodRemittance::with('rider.station')->where('variance', '!=', 0)->whereBetween('remit_date', [$from->toDateString(), $to->toDateString()])->latest()->limit(50)->get(),
            'canConfirm' => in_array($this->staff()->role->value, ['super_admin', 'finance'], true),
        ]);
    }

    public function confirm(Request $request, HubRemittance $remittance, Finance $finance)
    {
        abort_unless(in_array($this->staff()->role->value, ['super_admin', 'finance'], true), 403);
        $data = $request->validate([
            'decision' => 'required|in:confirm,dispute',
            // Only a dispute needs the bank's figure: confirming means the bank shows what was declared.
            'bank_amount' => 'required_if:decision,dispute|nullable|numeric|min:0|max:100000000',
            'note' => 'nullable|string|max:1000',
        ], ['bank_amount.required_if' => 'Enter the amount the bank statement shows for this deposit.']);
        $confirming = $data['decision'] === 'confirm';

        return $this->attempt(fn () => $finance->confirm($remittance, $this->staff(), $confirming, $data['note'] ?? null,
            $confirming ? null : (float) $data['bank_amount']),
            $confirming ? 'Remittance confirmed.' : 'Remittance disputed; the station will see it.');
    }

    /** The deposit slip the station attached, for checking against the bank statement. */
    public function slip(HubRemittance $remittance)
    {
        return $this->file($remittance, $remittance->slip_path, 'slip_viewed');
    }

    /** What the station attached when it contested HQ's dispute. */
    public function evidence(HubRemittance $remittance)
    {
        return $this->file($remittance, $remittance->contest_path, 'contest_evidence_viewed');
    }

    private function file(HubRemittance $remittance, ?string $path, string $action)
    {
        abort_unless($path && Storage::disk('local')->exists($path), 404);
        AuditLog::record($remittance, $action);

        return Storage::disk('local')->response($path);
    }
}
