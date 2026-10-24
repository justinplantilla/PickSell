<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        // Our own pages may ask for the camera (Pickrush waybill scanner) and location (map pins);
        // the browser still prompts the user. Embedded third-party frames get neither.
        $response->headers->set('Permissions-Policy', 'camera=(self), microphone=(), geolocation=(self)');

        if ($request->getHost() === 'pick-sell.shop' || $request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
