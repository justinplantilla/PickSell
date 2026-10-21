<?php

namespace App\Pickrush\Http\Middleware;

use App\Pickrush\Enums\StaffRole;
use App\Pickrush\Support\Actor;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Station portal pages: staff posted to a station hub. ->middleware('pickrush.station:manager')
 * narrows it to the station's hub manager.
 */
class StationStaff
{
    public function handle(Request $request, Closure $next, ?string $level = null): Response
    {
        $staff = Actor::staff();
        abort_unless($staff?->hub?->isStation(), 403, 'This page is for station hub staff.');
        if ($level === 'manager') {
            abort_unless($staff->hasRole(StaffRole::HubManager), 403, 'Only the station hub manager can do this.');
        }

        return $next($request);
    }
}
