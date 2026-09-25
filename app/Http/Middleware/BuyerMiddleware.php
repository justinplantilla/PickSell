<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class BuyerMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        if (auth()->check() && auth()->user()->role !== 'buyer') {
            return redirect('/dashboard')->with('error', 'This page is only available in the buyer portal.');
        }

        if (!auth()->check() || !auth()->user()->isApproved()) {
            abort(403, 'Access denied.');
        }
        return $next($request);
    }
}
