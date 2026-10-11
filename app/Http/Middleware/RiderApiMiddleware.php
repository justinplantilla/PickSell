<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RiderApiMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        abort_unless($user && $user->role === 'courier' && $user->isApproved(), 403, 'Access denied.');

        return $next($request);
    }
}
