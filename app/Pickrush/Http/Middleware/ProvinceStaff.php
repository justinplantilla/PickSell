<?php

namespace App\Pickrush\Http\Middleware;

use App\Pickrush\Enums\StaffRole;
use App\Pickrush\Support\Actor;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Province hub (sorting center) pages; ->middleware('pickrush.province:manager') narrows to the hub manager. */
class ProvinceStaff
{
    public function handle(Request $request, Closure $next, ?string $level = null): Response
    {
        $staff = Actor::staff();
        abort_unless($staff?->hub?->isProvince(), 403, 'This page is for province hub staff.');
        if ($level === 'manager') {
            abort_unless($staff->hasRole(StaffRole::HubManager), 403, 'Only the province hub manager can do this.');
        }

        return $next($request);
    }
}
