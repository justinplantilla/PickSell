<?php

namespace App\Pickrush\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Pickrush\Support\Actor;

/**
 * "/" on the logistics host: the public landing page for guests, the application status for staff of
 * a company that is not approved yet, and for other signed-in staff a redirect to their own portal (whose routes check the role and sign out deactivated accounts).
 */
class DashboardController extends Controller
{
    public function __invoke()
    {
        $staff = Actor::staff();
        if (! $staff) {
            return view('pickrush.landing');
        }

        if (! $staff->company?->isApproved()) {
            return view('pickrush.auth.application-status', ['company' => $staff->company, 'staff' => $staff]);
        }

        $hub = $staff->hub;

        return redirect()->route(match (true) {
            $hub?->isStation() => 'pickrush.station.dashboard',
            $hub?->isProvince() => 'pickrush.province.dashboard',
            default => 'pickrush.hq.dashboard',
        });
    }
}
