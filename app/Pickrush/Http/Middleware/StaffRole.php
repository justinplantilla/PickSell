<?php

namespace App\Pickrush\Http\Middleware;

use App\Pickrush\Enums\StaffRole as Role;
use App\Pickrush\Support\Actor;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route guard by staff role, e.g. ->middleware('pickrush.role:super_admin,finance').
 * Also signs out accounts that were deactivated while their session was open.
 */
class StaffRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $staff = Actor::staff();
        if (! $staff) {
            return redirect()->route('pickrush.login');
        }
        if (! $staff->is_active) {
            auth('pickrush')->logout();
            $request->session()->invalidate();

            return redirect()->route('pickrush.login')->withErrors(['email' => 'Your account is inactive. Ask HQ to reactivate it.']);
        }
        if ($roles !== [] && ! in_array($staff->role, array_map(fn ($role) => Role::from($role), $roles), true)) {
            abort(403, 'Your role does not have access to this page.');
        }

        return $next($request);
    }
}
