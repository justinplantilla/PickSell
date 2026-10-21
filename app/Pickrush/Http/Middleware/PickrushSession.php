<?php

namespace App\Pickrush\Http\Middleware;

use App\Pickrush\Support\PickrushHost;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Global middleware, runs before the session starts: requests on the Pickrush host get their
 * own session cookie scoped to that host only, so the marketplace and the logistics portal
 * never share (or end) each other's sessions.
 */
class PickrushSession
{
    public function handle(Request $request, Closure $next): Response
    {
        if (PickrushHost::matches($request)) {
            config([
                'session.cookie' => config('pickrush.session_cookie'),
                'session.domain' => null,
            ]);
        }

        return $next($request);
    }
}
