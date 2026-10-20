<?php

namespace App\Pickrush\Http\Middleware;

use App\Pickrush\Support\PickrushHost;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Pickrush routes are bound to the logistics host; this keeps marketplace pages (shop, buyer,
 * seller, admin) from answering on it. Anything that is not a pickrush.* route is a 404 there.
 */
class IsolatePickrushHost
{
    public function handle(Request $request, Closure $next): Response
    {
        if (PickrushHost::matches($request) && ! str_starts_with((string) $request->route()?->getName(), 'pickrush.')) {
            abort(404);
        }

        return $next($request);
    }
}
