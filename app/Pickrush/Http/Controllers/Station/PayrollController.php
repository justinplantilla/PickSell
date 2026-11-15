<?php

namespace App\Pickrush\Http\Controllers\Station;

use App\Pickrush\Models\Parcel;
use App\Pickrush\Models\Rider;
use App\Pickrush\Models\RiderDeduction;
use App\Pickrush\Models\RiderPayslip;
use App\Pickrush\Services\OperationRefused;
use App\Pickrush\Services\Rider\RiderPayroll;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The station's rider payroll: each week's payslips (released by the hub manager, or by
 * themselves on Tuesday), this week's pay so far, disputed deductions to decide, and deductions
 * for damaged or lost parcels. Cash shortages are deducted on the Cash page.
 */
class PayrollController extends StationController
{
    public function __construct(private RiderPayroll $payroll) {}

    public function index(Request $request)
    {
        $station = $this->station();
        $weeks = RiderPayslip::where('station_hub_id', $station->id)->select('week_start')->distinct()->orderByDesc('week_start')->limit(12)->pluck('week_start')
            ->map(fn ($d) => Carbon::parse($d)->toDateString())->unique()->values();
        $week = $request->query('week');
        $week = $week && $weeks->contains($week) ? $week : $weeks->first();
        $riders = Rider::where('station_hub_id', $station->id)->whereIn('status', ['active', 'suspended'])->orderBy('full_name')->get();

        return view('pickrush.station.payroll', [
            'weeks' => $weeks,
            'week' => $week,
            'payslips' => $week ? RiderPayslip::with('rider', 'releaser')->where('station_hub_id', $station->id)->whereDate('week_start', $week)->get()
                ->sortBy(fn ($p) => $p->rider->full_name)->values() : collect(),
            'thisWeek' => RiderPayroll::weekStart(now()),
            'pending' => $riders->map(fn (Rider $rider) => (object) (['rider' => $rider] + $this->payroll->wallet($rider)))
                ->filter(fn ($row) => $row->pending_earned > 0 || $row->pending_deductions > 0 || $row->available != 0.0)->values(),
            'disputed' => RiderDeduction::with('rider', 'parcel')->where('status', RiderDeduction::DISPUTED)
                ->whereIn('rider_id', $riders->pluck('id'))->oldest('disputed_at')->get(),
            'riders' => $riders,
        ]);
    }

    public function show(RiderPayslip $payslip)
    {
        return view('pickrush.station.payslip', [
            'payslip' => $payslip->load('rider', 'releaser'),
            'earnings' => DB::table('pr_rider_earnings')->leftJoin('pr_parcels', 'pr_parcels.id', '=', 'pr_rider_earnings.parcel_id')
                ->where('pr_rider_earnings.payslip_id', $payslip->id)->orderBy('earned_at')
                ->get(['pr_rider_earnings.kind', 'pr_rider_earnings.amount', 'pr_rider_earnings.earned_at', 'pr_parcels.tracking_no', 'pr_parcels.parcel_class']),
            'deductions' => RiderDeduction::with('parcel')->where('payslip_id', $payslip->id)->orderBy('occurred_at')->get(),
        ]);
    }

    public function release(RiderPayslip $payslip)
    {
        return $this->attempt(fn () => $this->payroll->release($payslip, $this->staff()),
            fn (RiderPayslip $p) => "Released {$p->rider->full_name}'s ₱".number_format((float) $p->net, 2).'.');
    }

    public function releaseWeek(Request $request)
    {
        $data = $request->validate(['week' => 'required|date']);

        return $this->attempt(function () use ($data) {
            $closed = RiderPayslip::where('station_hub_id', $this->station()->id)->whereDate('week_start', $data['week'])->where('status', RiderPayslip::CLOSED)->get();
            if ($closed->isEmpty()) {
                throw new OperationRefused('Every payslip for that week is already released.');
            }
            $closed->each(fn (RiderPayslip $p) => $this->payroll->release($p, $this->staff()));

            return $closed->count();
        }, fn (int $count) => "Released {$count} ".str('payslip')->plural($count).'.');
    }

    public function deduct(Request $request)
    {
        $data = $request->validate([
            'rider_id' => 'required|integer',
            'kind' => 'required|in:damage,lost,other',
            'amount' => 'required|numeric|min:0.01|max:100000',
            'reason' => 'required|string|min:5|max:255',
            'tracking_no' => 'nullable|string|max:40',
        ]);
        $rider = Rider::where('station_hub_id', $this->station()->id)->findOrFail($data['rider_id']);

        return $this->attempt(function () use ($data, $rider) {
            $parcel = null;
            if (! empty($data['tracking_no'])) {
                $parcel = Parcel::withoutGlobalScopes()->where('tracking_no', strtoupper(trim($data['tracking_no'])))->first()
                    ?? throw new OperationRefused("No parcel has tracking number {$data['tracking_no']}.");
            }

            return $this->payroll->deduct($rider, $this->staff(), $data['kind'], (float) $data['amount'], $data['reason'], $parcel);
        }, fn (RiderDeduction $d) => '₱'.number_format((float) $d->amount, 2)." deducted from {$rider->full_name}'s next payslip.");
    }

    public function decide(Request $request, RiderDeduction $deduction)
    {
        $data = $request->validate(['decision' => 'required|in:upheld,waived', 'note' => 'required|string|min:5|max:1000']);

        return $this->attempt(fn () => $this->payroll->decide($deduction, $this->staff(), $data['decision'] === 'upheld', $data['note']),
            $data['decision'] === 'upheld' ? 'Deduction upheld: it comes off the next payslip.' : 'Deduction waived.');
    }
}
