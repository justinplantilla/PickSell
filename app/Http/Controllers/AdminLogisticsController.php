<?php

namespace App\Http\Controllers;

use App\Pickrush\Enums\ParcelStatus;
use App\Pickrush\Models\Hub;
use App\Pickrush\Models\Parcel;
use App\Pickrush\Models\ParcelException;
use App\Pickrush\Models\Rider;
use App\Pickrush\Models\Staff;

/**
 * Read-only admin overview of the logistics company. Pickrush delivers every PickSell order and
 * runs its hubs, riders and exceptions from its own portal; this page only reports on it.
 */
class AdminLogisticsController extends Controller
{
    public function index()
    {
        $parcels = Parcel::query()->where('source', 'picksell');
        $finished = [ParcelStatus::Delivered, ParcelStatus::ReturnedToSender, ParcelStatus::Cancelled, ParcelStatus::Lost];

        $company = [
            'name' => 'Pickrush',
            'portal_url' => 'https://'.config('pickrush.domain'),
            'province_hubs' => Hub::provinces()->where('is_active', true)->count(),
            'station_hubs' => Hub::stations()->where('is_active', true)->count(),
            'staff' => Staff::where('is_active', true)->where('is_sample', false)->count(),
            'riders' => Rider::where('status', 'active')->count(),
        ];

        $parcelCounts = [
            'in_transit' => (clone $parcels)->whereNotIn('current_status', array_map(fn ($s) => $s->value, $finished))->count(),
            'delivered' => (clone $parcels)->where('current_status', ParcelStatus::Delivered)->count(),
            'failed' => (clone $parcels)->whereIn('current_status', [ParcelStatus::DeliveryFailed, ParcelStatus::ReturningToSender, ParcelStatus::ReturnedToSender])->count(),
            'lost' => (clone $parcels)->where('current_status', ParcelStatus::Lost)->count(),
        ];

        $openExceptions = ParcelException::query()
            ->where('status', 'open')
            ->with(['parcel', 'hub'])
            ->latest()
            ->take(10)
            ->get();

        return view('admin.logistics.overview', [
            'company' => $company,
            'parcelCounts' => $parcelCounts,
            'openExceptions' => $openExceptions,
            'openExceptionCount' => ParcelException::where('status', 'open')->count(),
        ]);
    }
}
