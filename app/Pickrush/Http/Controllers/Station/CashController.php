<?php

namespace App\Pickrush\Http\Controllers\Station;

use App\Pickrush\Models\CodRemittance;
use App\Pickrush\Models\HubRemittance;
use App\Pickrush\Models\Rider;
use App\Pickrush\Models\Staff;
use App\Pickrush\Services\Station\CashHandling;
use Illuminate\Http\Request;

/** COD handling: end-of-day rider remittances, variances, and the station's remittance to HQ. */
class CashController extends StationController
{
    public function __construct(private CashHandling $cash) {}

    public function index()
    {
        $station = $this->station();

        return view('pickrush.station.cash', [
            'riders' => Rider::where('station_hub_id', $station->id)->whereIn('status', ['active', 'suspended'])->orderBy('full_name')->get()
                ->map(fn (Rider $rider) => (object) ['rider' => $rider, 'expected' => $this->cash->expectedFrom($rider)])
                ->filter(fn ($row) => $row->expected > 0)->values(),
            'disputed' => CodRemittance::with('rider', 'receiver')->where('status', 'disputed')->latest()->get(),
            'recent' => CodRemittance::with('rider', 'receiver')->latest()->limit(30)->get(),
            'cashOnHand' => $this->cash->stationCashOnHand($station),
            'hubRemittances' => HubRemittance::latest()->limit(10)->get(),
            'colleagues' => Staff::where('hub_id', $station->id)->where('is_active', true)->where('id', '!=', $this->staff()->id)->orderBy('full_name')->get(),
            'twoPersonAbove' => (float) config('pickrush.policies.two_person_count_above'),
        ]);
    }

    public function record(Request $request, Rider $rider)
    {
        $data = $request->validate([
            'received_amount' => 'required|numeric|min:0|max:10000000',
            'second_counter_id' => 'nullable|integer',
            'note' => 'nullable|string|max:500',
        ]);
        $second = empty($data['second_counter_id']) ? null : Staff::find($data['second_counter_id']);

        return $this->attempt(
            fn () => $this->cash->recordRiderRemittance($rider, (float) $data['received_amount'], $this->staff(), $second, $data['note'] ?? null),
            fn (CodRemittance $r) => $r->status === 'reconciled'
                ? "₱".number_format((float) $r->received_amount, 2)." received from {$rider->full_name}. Matches the system total."
                : "Recorded with a variance of ₱".number_format((float) $r->variance, 2).". {$rider->full_name} cannot be dispatched until the hub manager resolves it.",
        );
    }

    public function resolve(Request $request, CodRemittance $remittance)
    {
        $data = $request->validate(['resolution' => 'required|string|min:10|max:1000']);

        return $this->attempt(fn () => $this->cash->resolve($remittance, $this->staff(), $data['resolution']), 'Variance resolved.');
    }

    public function remit(Request $request)
    {
        $data = $request->validate([
            'amount' => 'required|numeric|min:0|max:100000000',
            'reference_no' => 'required|string|max:60',
            'note' => 'nullable|string|max:500',
        ]);

        return $this->attempt(fn () => $this->cash->submitHubRemittance($this->staff(), (float) $data['amount'], $data['reference_no'], $data['note'] ?? null),
            'Remittance to HQ submitted. HQ finance will confirm it.');
    }
}
