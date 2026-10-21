<?php

namespace App\Pickrush\Http\Controllers\Hq;

use App\Pickrush\Http\Controllers\PortalController;
use App\Pickrush\Models\CodRemittance;
use App\Pickrush\Models\HubRemittance;
use App\Pickrush\Services\Hq\Finance;
use App\Pickrush\Services\Hq\NetworkMetrics;
use Illuminate\Http\Request;

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
            'pending' => HubRemittance::with('station')->where('status', 'submitted')->oldest()->get(),
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
        $data = $request->validate(['decision' => 'required|in:confirm,dispute', 'note' => 'nullable|string|max:1000']);

        return $this->attempt(fn () => $finance->confirm($remittance, $this->staff(), $data['decision'] === 'confirm', $data['note'] ?? null),
            $data['decision'] === 'confirm' ? 'Remittance confirmed.' : 'Remittance disputed; the station will see it.');
    }
}
