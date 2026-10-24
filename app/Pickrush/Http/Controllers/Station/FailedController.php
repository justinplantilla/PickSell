<?php

namespace App\Pickrush\Http\Controllers\Station;

use App\Pickrush\Enums\ParcelStatus;
use App\Pickrush\Models\Parcel;
use App\Pickrush\Services\Station\DispatchService;
use App\Pickrush\Services\Station\FailedDeliveries;
use Illuminate\Http\Request;

/** Failed attempts at this station: retry (through dispatch) or send back to the seller. */
class FailedController extends StationController
{
    public function index()
    {
        $parcels = Parcel::where('current_hub_id', $this->station()->id)->where('current_status', ParcelStatus::DeliveryFailed)
            ->with(['events' => fn ($q) => $q->where('status', ParcelStatus::DeliveryFailed),
                // Not handed back yet: still on the delivery rider's vehicle.
                'assignments' => fn ($q) => DispatchService::stillWithRider($q)->with('rider')])
            ->oldest('updated_at')->get();

        return view('pickrush.station.failed', [
            'parcels' => $parcels,
            'maxAttempts' => config('pickrush.policies.max_delivery_attempts'),
            'returning' => Parcel::where('current_hub_id', $this->station()->id)->where('current_status', ParcelStatus::ReturningToSender)->get(),
        ]);
    }

    public function returnToSender(Request $request, Parcel $parcel, FailedDeliveries $failed)
    {
        $data = $request->validate(['reason' => 'required|string|max:255']);

        return $this->attempt(fn () => $failed->returnToSender($parcel, $this->staff(), $data['reason']),
            "{$parcel->tracking_no} is going back to the seller. Put it on the next trip to the province hub.");
    }
}
