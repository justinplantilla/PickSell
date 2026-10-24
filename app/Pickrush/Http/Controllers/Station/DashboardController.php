<?php

namespace App\Pickrush\Http\Controllers\Station;

use App\Pickrush\Enums\ParcelStatus;
use App\Pickrush\Models\Assignment;
use App\Pickrush\Models\Parcel;
use App\Pickrush\Models\ParcelEvent;
use App\Pickrush\Models\Rider;
use App\Pickrush\Models\RiderReport;
use App\Pickrush\Services\Station\CashHandling;

class DashboardController extends StationController
{
    public function __invoke(CashHandling $cash)
    {
        $station = $this->station();
        $today = fn (ParcelStatus $status) => ParcelEvent::where('hub_id', $station->id)->where('status', $status)->whereDate('occurred_at', today())->distinct()->count('parcel_id');
        $riders = Rider::where('station_hub_id', $station->id)->get();

        return view('pickrush.station.dashboard', [
            'station' => $station,
            'gaps' => $station->readinessGaps(),
            'arrivedToday' => $today(ParcelStatus::ArrivedAtStation),
            'deliveredToday' => $today(ParcelStatus::Delivered),
            'failedToday' => $today(ParcelStatus::DeliveryFailed),
            'waitingDispatch' => Parcel::where('current_hub_id', $station->id)
                ->whereIn('current_status', [ParcelStatus::ArrivedAtStation, ParcelStatus::DeliveryFailed, ParcelStatus::ReturningToSender, ParcelStatus::AtOriginHub])
                ->whereNull('next_hub_id')->count(),
            'outForDelivery' => Parcel::where('current_hub_id', $station->id)->where('current_status', ParcelStatus::OutForDelivery)->count(),
            'waitingPickup' => Parcel::where('origin_hub_id', $station->id)->where('current_status', ParcelStatus::WaitingPickup)->count(),
            'inbound' => Parcel::where('next_hub_id', $station->id)->whereIn('current_status', [ParcelStatus::InTransitToStation])->count(),
            'codOnRoad' => (float) Assignment::whereIn('rider_id', $riders->pluck('id'))->whereIn('pr_assignments.status', Assignment::OPEN)
                ->where('type', 'delivery')->join('pr_parcels', 'pr_parcels.id', '=', 'pr_assignments.parcel_id')->sum('pr_parcels.cod_amount'),
            'cashOnHand' => $cash->stationCashOnHand($station),
            'ridersOnShift' => $riders->where('status', 'active')->where('shift_status', 'on_shift')->count(),
            'ridersActive' => $riders->where('status', 'active')->count(),
            'riderApplications' => $riders->whereIn('status', ['pending', 'under_review'])->count(),
            'riderReports' => RiderReport::with('rider', 'batch')->where('station_hub_id', $station->id)->whereNull('resolved_at')->latest()->get(),
            'needsAddressFix' => Parcel::where('needs_address_fix', true)->whereNotIn('current_status', [ParcelStatus::Cancelled, ParcelStatus::Delivered])->count(),
        ]);
    }
}
