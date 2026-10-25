<?php

namespace App\Pickrush\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Pickrush\Support\Actor;

/**
 * "/" on the logistics host: the public landing page for guests, and for signed-in staff a redirect to
 * their own portal (whose routes check the role and sign out deactivated accounts).
 */
class DashboardController extends Controller
{
    public function __invoke()
    {
        $staff = Actor::staff();
        if (! $staff) {
            return view('pickrush.landing');
        }

        $hub = $staff->hub;

        return redirect()->route(match (true) {
            $hub?->isStation() => 'pickrush.station.dashboard',
            $hub?->isProvince() => 'pickrush.province.dashboard',
            default => 'pickrush.hq.dashboard',
        });
    }
}
