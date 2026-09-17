<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CourierMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        if (!auth()->check() || auth()->user()->role !== 'courier' || !auth()->user()->isApproved()) {
            abort(403, 'Access denied.');
        }

        return $next($request);
    }
}
