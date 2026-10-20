<?php

namespace App\Pickrush\Http\Controllers\Station;

use App\Pickrush\Enums\ParcelStatus;
use App\Pickrush\Models\Manifest;
use App\Pickrush\Models\Parcel;
use App\Pickrush\Services\Station\StationReceiving;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/** Inbound: one scan box for seller drop-offs, rider pickups and trips from the province hub. */
class ReceivingController extends StationController
{
    public function index()
    {
        $station = $this->station();

        return view('pickrush.station.receiving', [
            'scanKey' => (string) Str::uuid(),
            'trips' => Manifest::with('fromHub')->where('to_hub_id', $station->id)->whereIn('status', ['dispatched', 'received'])->latest('dispatched_at')->get(),
            'inTransit' => Parcel::where('next_hub_id', $station->id)->where('current_status', ParcelStatus::InTransitToStation)->latest('updated_at')->limit(100)->get(),
            'firstMile' => Parcel::where('origin_hub_id', $station->id)->whereIn('current_status', [ParcelStatus::WaitingPickup, ParcelStatus::PickedUp])
                ->with('openAssignment.rider')->oldest()->limit(100)->get(),
        ]);
    }

    public function scan(Request $request, StationReceiving $receiving)
    {
        $data = $request->validate(['tracking_no' => 'required|string|max:30', 'scan_key' => 'nullable|string|max:80']);

        return $this->attempt(
            fn () => $receiving->scan($data['tracking_no'], $this->staff(), self::scanKey($data)),
            fn (string $message) => $message,
        );
    }
}
