<?php

namespace App\Pickrush\Http\Middleware;

use App\Pickrush\Support\Actor;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Pages for staff posted to a hub (station or province), not HQ. */
class HubStaff
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(Actor::staff()?->hub, 403, 'This page is for hub staff.');

        return $next($request);
    }
}
