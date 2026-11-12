<?php

namespace App\Pickrush\Http\Controllers\Station;

use App\Pickrush\Models\CodRemittance;
use App\Pickrush\Models\HubRemittance;
use App\Pickrush\Models\Rider;
use App\Pickrush\Models\Staff;
use App\Pickrush\Services\Station\CashHandling;
use App\Pickrush\Services\Station\RemittanceDeadline;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/** COD handling: end-of-day rider remittances, variances, and the station's remittance to HQ. */
class CashController extends StationController
{
    private const SLIP_MESSAGES = [
        'slip.required' => 'Attach a photo or PDF of the deposit slip.',
        'slip.mimes' => 'The deposit slip must be a JPG, PNG or PDF.',
        'slip.max' => 'The deposit slip must be 5 MB or smaller.',
    ];

    public function __construct(private CashHandling $cash, private RemittanceDeadline $deadline) {}

    public function index()
    {
        $station = $this->station();

        return view('pickrush.station.cash', [
            'riders' => Rider::where('station_hub_id', $station->id)->whereIn('status', ['active', 'suspended'])->orderBy('full_name')->get()
                ->map(function (Rider $rider) {
                    $cash = $this->cash->breakdown($rider);

                    return (object) ['rider' => $rider, 'cod' => $cash['cod'], 'pay' => $cash['pay'], 'expected' => $cash['to_remit']];
                })
                ->filter(fn ($row) => $row->cod > 0)->values(),
            'disputed' => CodRemittance::with('rider', 'receiver')->where('status', 'disputed')->latest()->get(),
            'awaiting' => CodRemittance::with('rider', 'receiver')->where('status', CodRemittance::AWAITING_RIDER)->latest()->get(),
            // Counts riders rejected that were not counted again yet: their cash is back on the "holding cash" list.
            'rejected' => CodRemittance::with('rider', 'receiver')->where('status', CodRemittance::REJECTED)
                ->whereNotExists(fn ($q) => $q->from('pr_cod_remittances as recount')->whereColumn('recount.rider_id', 'pr_cod_remittances.rider_id')
                    ->whereColumn('recount.id', '>', 'pr_cod_remittances.id')->where('recount.status', '!=', CodRemittance::REJECTED))
                ->latest('rider_responded_at')->get(),
            'recent' => CodRemittance::with('rider', 'receiver')->latest()->limit(30)->get(),
            'cashOnHand' => $this->cash->stationCashOnHand($station),
            // Confirmed rider remittances collecting until the manager remits them to HQ together.
            'ready' => CodRemittance::with('rider')->where('status', 'reconciled')->whereNull('hub_remittance_id')->oldest()->get(),
            'deadline' => $this->deadline->for($station),
            // Deposits HQ disputed: the station tops up the shortfall or contests the dispute.
            'disputes' => HubRemittance::where('status', 'disputed')->whereNull('settles_id')->with('topUps')->oldest('confirmed_at')->get(),
            'hubRemittances' => HubRemittance::latest()->limit(10)->get(),
            'colleagues' => Staff::where('hub_id', $station->id)->where('is_active', true)->where('id', '!=', $this->staff()->id)->orderBy('full_name')->get(),
            'twoPersonAbove' => (float) config('pickrush.policies.two_person_count_above'),
        ]);
    }

    public function record(Request $request, Rider $rider)
    {
        $data = $request->validate([
            'expected_amount' => 'nullable|numeric|min:0',
            // Left empty when the staff member confirms the expected total; typed only when the cash differs.
            'received_amount' => 'nullable|numeric|min:0|max:10000000',
            'second_counter_id' => 'nullable|integer',
            'note' => 'nullable|string|max:500',
        ]);
        $second = empty($data['second_counter_id']) ? null : Staff::find($data['second_counter_id']);

        return $this->attempt(
            fn () => $this->cash->recordRiderRemittance($rider, isset($data['received_amount']) ? (float) $data['received_amount'] : null, $this->staff(), $second,
                $data['note'] ?? null, isset($data['expected_amount']) ? (float) $data['expected_amount'] : null),
            fn (CodRemittance $r) => (float) $r->variance == 0.0
                ? "₱".number_format((float) $r->received_amount, 2)." counted from {$rider->full_name}. Matches the system total. Waiting for {$rider->full_name} to confirm it in the rider app."
                : "Counted with a variance of ₱".number_format((float) $r->variance, 2).". Waiting for {$rider->full_name} to confirm it in the rider app; then the hub manager resolves the variance.",
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
            // The total the manager saw and confirmed; the amount itself always comes from the system.
            'expected_amount' => 'required|numeric|min:0',
            'reference_no' => 'required|string|max:60',
            'slip' => 'required|file|mimes:jpg,jpeg,png,pdf|max:5120',
            'note' => 'nullable|string|max:500',
        ], self::SLIP_MESSAGES);

        return $this->attempt(fn () => $this->cash->submitHubRemittance($this->staff(), $data['reference_no'], $request->file('slip'), $data['note'] ?? null, (float) $data['expected_amount']),
            fn ($r) => '₱'.number_format((float) $r->remitted_amount, 2).' remittance to HQ submitted. HQ finance will check it against the bank statement.');
    }

    /** Deposit the shortfall of a remittance HQ disputed. */
    public function topUp(Request $request, HubRemittance $hubRemittance)
    {
        $data = $request->validate([
            'shortfall' => 'required|numeric|min:0',
            'reference_no' => 'required|string|max:60',
            'slip' => 'required|file|mimes:jpg,jpeg,png,pdf|max:5120',
        ], self::SLIP_MESSAGES);

        return $this->attempt(fn () => $this->cash->topUp($this->staff(), $hubRemittance, $data['reference_no'], $request->file('slip'), (float) $data['shortfall']),
            fn ($r) => '₱'.number_format((float) $r->remitted_amount, 2).' top-up submitted. HQ finance will check it against the bank statement.');
    }

    /** Tell HQ why its dispute is wrong; it checks the bank again. */
    public function contest(Request $request, HubRemittance $hubRemittance)
    {
        $data = $request->validate([
            'explanation' => 'required|string|min:10|max:1000',
            'evidence' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120',
        ], ['evidence.mimes' => 'The evidence must be a JPG, PNG or PDF.', 'evidence.max' => 'The evidence must be 5 MB or smaller.']);

        return $this->attempt(fn () => $this->cash->contest($this->staff(), $hubRemittance, $data['explanation'], $request->file('evidence')),
            'Sent back to HQ finance to check again.');
    }

    /** The deposit slip this station attached to its remittance. */
    public function slip(HubRemittance $hubRemittance)
    {
        abort_unless($hubRemittance->slip_path && Storage::disk('local')->exists($hubRemittance->slip_path), 404);

        return Storage::disk('local')->response($hubRemittance->slip_path);
    }
}
