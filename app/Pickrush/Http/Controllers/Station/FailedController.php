<?php

namespace App\Pickrush\Http\Controllers\Station;

use App\Pickrush\Enums\ParcelStatus;
use App\Pickrush\Models\Parcel;
use App\Pickrush\Services\Station\DispatchService;
use App\Pickrush\Services\Station\FailedDeliveries;
use Illuminate\Http\Request;

/**
 * Failed attempts at this station: retry (through dispatch) or send back to the seller; and the
 * returns sellers did not take back, held here until they do (or, past the abandon period, kept).
 */
class FailedController extends StationController
{
    public function index()
    {
        $station = $this->station()->id;
        // Buyer returns the seller did not take are listed with the held returns, not retried towards a buyer.
        $heldBuyerReturn = fn ($q) => $q->where('source', 'picksell')->where('external_ref', 'like', 'RET-%')->whereNotNull('return_refused_at');
        $parcels = Parcel::where('current_hub_id', $station)->where('current_status', ParcelStatus::DeliveryFailed)
            ->whereNot($heldBuyerReturn)
            ->with(['events' => fn ($q) => $q->where('status', ParcelStatus::DeliveryFailed),
                // Not handed back yet: still on the delivery rider's vehicle.
                'assignments' => fn ($q) => DispatchService::stillWithRider($q)->with('rider')])
            ->oldest('updated_at')->get();

        return view('pickrush.station.failed', [
            'parcels' => $parcels,
            'maxAttempts' => config('pickrush.policies.max_delivery_attempts'),
            'returning' => Parcel::where('current_hub_id', $this->station()->id)->where('current_status', ParcelStatus::ReturningToSender)
                ->whereNull('return_refused_at')->get(),
            'held' => Parcel::where('current_hub_id', $station)
                ->where(fn ($q) => $q->where(fn ($r) => $r->where('current_status', ParcelStatus::ReturningToSender)->whereNotNull('return_refused_at'))
                    ->orWhere(fn ($r) => $heldBuyerReturn($r->where('current_status', ParcelStatus::DeliveryFailed))))
                ->with(['assignments' => fn ($q) => DispatchService::stillWithRider($q)->with('rider')])
                ->oldest('return_refused_at')->get(),
            'holdDays' => (int) config('pickrush.policies.return_hold_days'),
            'abandonDays' => (int) config('pickrush.policies.return_abandon_days'),
        ]);
    }

    public function returnToSender(Request $request, Parcel $parcel, FailedDeliveries $failed)
    {
        $data = $request->validate(['reason' => 'required|string|max:255']);

        return $this->attempt(fn () => $failed->returnToSender($parcel, $this->staff(), $data['reason']),
            "{$parcel->tracking_no} is going back to the seller. Put it on the next trip to the province hub.");
    }

    public function abandon(Request $request, Parcel $parcel, FailedDeliveries $failed)
    {
        $data = $request->validate(['note' => 'nullable|string|max:255']);

        return $this->attempt(fn () => $failed->abandon($parcel, $this->staff(), $data['note'] ?? null),
            "{$parcel->tracking_no} marked abandoned. It is now the company's to keep.");
    }
}
