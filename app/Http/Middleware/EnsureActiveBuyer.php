<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards the buyer mobile API (alias buyer.api, routes/buyer-api.php): only approved buyers get
 * through. Sellers and admins are refused, and a buyer suspended after their token was issued
 * loses it on the next request.
 */
class EnsureActiveBuyer
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user || $user->role !== 'buyer') {
            return response()->json(['message' => 'This app is for buyers only.'], 403);
        }

        if ($user->status !== 'approved') {
            // Suspended / disapproved accounts lose API access immediately.
            $token = $user->currentAccessToken();
            if ($token instanceof \Laravel\Sanctum\PersonalAccessToken) $token->delete();
            return response()->json([
                'message' => $user->status === 'suspended'
                    ? 'Your account has been suspended. Please contact support.'
                    : 'Your account is not active. Please contact support.',
            ], 401);
        }

        return $next($request);
    }
}
