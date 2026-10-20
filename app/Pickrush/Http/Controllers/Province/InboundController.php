<?php

namespace App\Pickrush\Http\Controllers\Province;

use App\Pickrush\Enums\ParcelStatus;
use App\Pickrush\Http\Controllers\PortalController;
use App\Pickrush\Models\Manifest;
use App\Pickrush\Models\Parcel;
use App\Pickrush\Services\ExceptionDesk;
use App\Pickrush\Services\ManifestService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/** Trips arriving at the sorting center, and a scan for parcels that come in without one. */
class InboundController extends PortalController
{
    public function index()
    {
        $hub = $this->hub();

        return view('pickrush.province.inbound', [
            'scanKey' => (string) Str::uuid(),
            'trips' => Manifest::with('fromHub')->where('to_hub_id', $hub->id)->whereIn('status', ['dispatched', 'received'])->oldest('dispatched_at')->get(),
            'onTheWay' => Parcel::where('next_hub_id', $hub->id)
                ->whereIn('current_status', [ParcelStatus::InTransitToProvince, ParcelStatus::InTransitToStation])->with('originHub')->oldest('updated_at')->limit(200)->get(),
        ]);
    }

    public function scan(Request $request, ManifestService $manifests)
    {
        $data = $request->validate(['tracking_no' => 'required|string|max:30', 'scan_key' => 'nullable|string|max:80']);
        $hub = $this->hub();

        return $this->attempt(function () use ($data, $manifests, $hub) {
            $parcel = Parcel::withoutGlobalScopes()->where('tracking_no', ManifestService::normalize($data['tracking_no']))->first()
                ?? throw new \App\Pickrush\Services\OperationRefused('Unknown tracking number. Report it as unlabeled.');
            $trip = Manifest::withoutGlobalScopes()->where('to_hub_id', $hub->id)->whereIn('status', ['dispatched', 'received'])
                ->whereHas('items', fn ($q) => $q->where('parcel_id', $parcel->id))->first();
            $trip ? $manifests->receive($trip, $parcel->tracking_no, $this->staff(), self::scanKey($data))
                : $manifests->arrive($parcel, $hub, self::scanKey($data));

            return $parcel;
        }, fn (Parcel $parcel) => "{$parcel->tracking_no} received. Sort it next.");
    }

    public function misSorted(Request $request, ExceptionDesk $desk)
    {
        $data = $request->validate(['tracking_no' => 'required|string|max:30', 'note' => 'nullable|string|max:500']);

        return $this->attempt(fn () => $desk->receiveMisSorted($data['tracking_no'], $this->staff(), $data['note'] ?? null),
            fn ($exception) => "Received as mis-sorted and logged against {$exception->againstHub?->name}. Sort it to send it the right way.");
    }
}
