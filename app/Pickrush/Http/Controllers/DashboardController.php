<?php

namespace App\Pickrush\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Pickrush\Support\Actor;

/** Landing page after sign-in: each staff member goes to their own portal. */
class DashboardController extends Controller
{
    public function __invoke()
    {
        $hub = Actor::staff()->hub;

        return redirect()->route(match (true) {
            $hub?->isStation() => 'pickrush.station.dashboard',
            $hub?->isProvince() => 'pickrush.province.dashboard',
            default => 'pickrush.hq.dashboard',
        });
    }
}
