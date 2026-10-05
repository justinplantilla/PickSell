<?php

namespace App\Http\Middleware;

use App\Auth\Permission;
use Closure;
use Illuminate\Http\Request;

class AdminMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        if (!auth()->check()) {
            abort(403, 'Unauthorized.');
        }

        if (! Permission::canEnterAdminPortal(auth()->user()->role)) {
            return redirect('/dashboard')->with('error', 'This page is only available to administrators.');
        }

        // Deny by default: every admin route must declare a known permission (`can:` middleware).
        // Entering the portal never authorizes an operation by itself.
        if (! $this->routeDeclaresPermission($request)) {
            abort(403, 'This admin route has no permission assigned.');
        }

        return $next($request);
    }

    private function routeDeclaresPermission(Request $request): bool
    {
        $declared = collect($request->route()?->gatherMiddleware() ?? [])
            ->filter(fn ($middleware) => is_string($middleware) && str_starts_with($middleware, 'can:'))
            ->map(fn ($middleware) => explode(',', substr($middleware, 4))[0]);

        return $declared->isNotEmpty() && $declared->diff(Permission::ALL)->isEmpty();
    }
}
