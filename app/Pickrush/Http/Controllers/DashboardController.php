<?php

namespace App\Pickrush\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Pickrush\Enums\ParcelStatus;
use App\Pickrush\Enums\StaffRole;
use App\Pickrush\Models\Hub;
use App\Pickrush\Models\Parcel;
use App\Pickrush\Support\Actor;

/** Landing page after sign-in. HQ sees the network; hub staff see their own hub. */
class DashboardController extends Controller
{
    public function __invoke()
    {
        $staff = Actor::staff();
        if ($staff->hub?->isStation()) {
            return redirect()->route('pickrush.station.dashboard');
        }
        if ($staff->hub?->isProvince()) {
            return redirect()->route('pickrush.province.dashboard');
        }
        $parcels = Parcel::query();   // already limited to the viewer's hub by ParcelHubScope

        $counts = (clone $parcels)->selectRaw('current_status, count(*) as total')->groupBy('current_status')->pluck('total', 'current_status');

        return view('pickrush.dashboard', [
            'staff' => $staff,
            'hub' => $staff->hub,
            'roleLabel' => $staff->role->label(),
            'parcelTotal' => (int) $counts->sum(),
            'inTransit' => (int) $counts->only([ParcelStatus::InTransitToProvince->value, ParcelStatus::InTransitToStation->value])->sum(),
            'delivered' => (int) ($counts[ParcelStatus::Delivered->value] ?? 0),
            'failed' => (int) ($counts[ParcelStatus::DeliveryFailed->value] ?? 0),
            'needsAddressFix' => (clone $parcels)->where('needs_address_fix', true)->count(),
            'network' => $staff->isCompany() ? [
                'provinces' => Hub::provinces()->count(),
                'stations' => Hub::stations()->count(),
                'withoutManager' => Hub::whereDoesntHave('staff', fn ($q) => $q
                    ->where('role', StaffRole::HubManager)->where('is_active', true)->where('is_sample', false))->count(),
            ] : null,
            'readinessGaps' => $staff->hub?->readinessGaps() ?? [],
        ]);
    }
}
