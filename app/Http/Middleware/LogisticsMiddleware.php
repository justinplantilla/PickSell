<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class LogisticsMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        if (auth()->check() && auth()->user()->role !== 'logistics') {
            return redirect('/dashboard')->with('error', 'This page is only available in the logistics portal.');
        }

        if (!auth()->check() || !auth()->user()->isApproved()) {
            abort(403, 'Logistics access only.');
        }

        return $next($request);
    }
}
