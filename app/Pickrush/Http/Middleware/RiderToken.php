<?php

namespace App\Pickrush\Http\Middleware;

use App\Pickrush\Models\RiderToken as Token;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Signs in a rider app request from its bearer token. With "active", only an approved rider gets
 * through: an applicant can see their own application and nothing else.
 */
class RiderToken
{
    public function handle(Request $request, Closure $next, ?string $require = null): Response
    {
        $plain = $request->bearerToken();
        $token = $plain ? Token::findByPlain($plain) : null;
        $rider = $token?->rider;

        if (! $rider || $rider->status === 'deactivated') {
            return response()->json(['message' => 'Please log in again.'], 401);
        }
        if ($require === 'active' && ! $rider->isActive()) {
            return response()->json(['message' => 'Your account is not active yet.', 'status' => $rider->status], 403);
        }

        if (! $token->last_used_at || $token->last_used_at->lt(now()->subMinutes(5))) {
            $token->forceFill(['last_used_at' => now()])->saveQuietly();
        }
        app()->instance('pickrush.rider', $rider);
        $request->attributes->set('rider_token', $token);

        return $next($request);
    }
}
