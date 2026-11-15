<?php

namespace App\Pickrush\Services\Hq;

use App\Pickrush\Enums\ParcelStatus;
use App\Pickrush\Models\HubRemittance;
use App\Pickrush\Models\Parcel;
use App\Pickrush\Models\RiderPayslip;
use App\Pickrush\Models\RiderWithdrawal;
use App\Pickrush\Models\Staff;
use App\Pickrush\Services\OperationRefused;
use App\Pickrush\Support\Actor;
use App\Pickrush\Support\ParcelHubScope;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Company finance: confirming the cash stations send, and what Pickrush earned and owes.
 * Every parcel is cash on delivery, so delivered COD belongs to the shipper less Pickrush's fee.
 */
class Finance
{
    /**
     * Confirming records that the bank received what the station declared. Disputing needs
     * $bankAmount, what the bank statement shows for the deposit reference: the station tops up
     * the difference.
     */
    public function confirm(HubRemittance $remittance, Staff $finance, bool $matches, ?string $note, ?float $bankAmount = null): void
    {
        $bankAmount = $matches ? (float) $remittance->remitted_amount : $bankAmount;
        if ($bankAmount === null) {
            throw new OperationRefused('Enter the amount the bank statement shows for this deposit.');
        }
        if ($remittance->status !== 'submitted') {
            throw new OperationRefused('This remittance was already '.$remittance->status.'.');
        }
        if ($remittance->submitted_by === $finance->id) {
            throw new OperationRefused('The person who submitted a remittance cannot confirm it.');
        }
        if (! $matches && blank($note)) {
            throw new OperationRefused('Say what does not match.');
        }
        $remittance->update([
            'status' => $matches ? 'confirmed' : 'disputed',
            'bank_amount' => round($bankAmount, 2),
            'confirmed_by' => $finance->id,
            'confirmed_at' => now(),
            'note' => trim(($remittance->note ? $remittance->note."\n" : '').($note ?? '')) ?: null,
        ]);

        // A confirmed top-up that covers the shortfall settles the disputed remittance it was for.
        $disputed = $matches ? $remittance->settles : null;
        if ($disputed && $disputed->status === 'disputed' && $disputed->shortfall() <= 0) {
            $disputed->update(['status' => HubRemittance::SETTLED]);
        }
    }

    /**
     * The company's rider payroll: pay earned in the period by task, each station's payslips
     * for the weeks starting in it (released or waiting), what riders withdrew, and two current
     * figures whatever the period: payslips waiting for release, and the payroll payable (released
     * nets not yet withdrawn: what the company owes its riders). Withdrawals are simulated, so
     * "withdrawn" is what the company would have paid out.
     */
    public function payroll(CarbonInterface $from, CarbonInterface $to): array
    {
        $companyId = Actor::companyId();
        $riders = DB::table('pr_riders')->select('id')->when($companyId !== null, fn ($q) => $q->where('company_id', $companyId));
        $earned = DB::table('pr_rider_earnings')->whereIn('rider_id', $riders)->whereBetween('earned_at', [$from, $to])
            ->selectRaw('kind, count(*) as tasks, coalesce(sum(amount), 0) as amount')->groupBy('kind')->get()->keyBy('kind');

        $stations = RiderPayslip::with('station')->whereBetween('week_start', [$from, $to])
            ->selectRaw("station_hub_id, count(*) as payslips, coalesce(sum(earned), 0) as earned, coalesce(sum(deductions), 0) as deductions,
                coalesce(sum(case when status = 'released' then net else 0 end), 0) as released,
                coalesce(sum(case when status = 'closed' then net else 0 end), 0) as waiting")
            ->groupBy('station_hub_id')->get()
            ->map(fn ($row) => [
                'station' => $row->station?->name,
                'payslips' => (int) $row->payslips,
                'earned' => (float) $row->earned,
                'deductions' => (float) $row->deductions,
                'released' => (float) $row->released,
                'waiting' => (float) $row->waiting,
            ])->sortBy('station')->values();

        $released = (float) RiderPayslip::where('status', RiderPayslip::RELEASED)->sum('net');
        $withdrawn = (float) RiderWithdrawal::sum('amount');

        return [
            'by_kind' => collect(['pickup', 'delivery', 'return'])->mapWithKeys(fn ($kind) => [$kind => [
                'tasks' => (int) ($earned[$kind]->tasks ?? 0),
                'amount' => (float) ($earned[$kind]->amount ?? 0),
            ]])->all(),
            'earned' => round((float) $earned->sum('amount'), 2),
            'stations' => $stations,
            'withdrawn' => round((float) RiderWithdrawal::whereBetween('created_at', [$from, $to])->sum('amount'), 2),
            'payable' => round($released - $withdrawn, 2),
            'waiting' => round((float) RiderPayslip::where('status', RiderPayslip::CLOSED)->sum('net'), 2),
        ];
    }

    /** Delivered COD and fees in the period, split by shipper source (Picksell or direct). */
    public function settlement(CarbonInterface $from, CarbonInterface $to): array
    {
        $delivered = Parcel::withoutGlobalScope(ParcelHubScope::class)->where('current_status', ParcelStatus::Delivered)
            ->whereBetween('delivered_at', [$from, $to])
            ->selectRaw('source, count(*) as parcels, sum(cod_amount) as cod, sum(shipping_fee) as fees')
            ->groupBy('source')->get()->keyBy('source');

        return collect(['picksell', 'direct'])->mapWithKeys(fn ($source) => [$source => [
            'parcels' => (int) ($delivered[$source]->parcels ?? 0),
            'cod' => (float) ($delivered[$source]->cod ?? 0),
            'fees' => (float) ($delivered[$source]->fees ?? 0),
            'owed' => (float) ($delivered[$source]->cod ?? 0) - (float) ($delivered[$source]->fees ?? 0),
        ]])->all();
    }
}
