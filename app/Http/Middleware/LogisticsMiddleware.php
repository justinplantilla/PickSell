<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class LogisticsMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        if (!auth()->check() || auth()->user()->role !== 'logistics' || !auth()->user()->isApproved()) {
            abort(403, 'Logistics access only.');
        }

        return $next($request);
    }
}
