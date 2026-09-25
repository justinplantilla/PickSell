<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class AdminMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        if (auth()->check() && auth()->user()->role !== 'admin') {
            return redirect('/dashboard')->with('error', 'This page is only available to administrators.');
        }

        if (!auth()->check()) {
            abort(403, 'Unauthorized.');
        }
        return $next($request);
    }
}
