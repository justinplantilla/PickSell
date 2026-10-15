<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureCompanyLogisticsPortal
{
    public function handle(Request $request, Closure $next)
    {
        abort_unless(
            $request->attributes->get('hub_context') === 'superadmin'
                && $request->user()->assigned_hub_id === null,
            404,
            'This feature is only available to the logistics company account.',
        );

        return $next($request);
    }
}
