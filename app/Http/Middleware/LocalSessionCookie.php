<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Global middleware, runs before the session starts: on a local host (localhost, 127.0.0.1, *.localhost, *.test) the
 * session cookie drops the production domain and, over plain http, the secure flag. Without this the browser refuses
 * a ".pick-sell.shop" or secure cookie on http://127.0.0.1:8000, so nobody can stay logged in locally. Production
 * hosts are untouched.
 */
class LocalSessionCookie
{
    public function handle(Request $request, Closure $next): Response
    {
        $host = $request->getHost();
        $local = in_array($host, ['localhost', '127.0.0.1', '::1'], true)
            || str_ends_with($host, '.localhost')
            || str_ends_with($host, '.test');

        if ($local) {
            $domain = ltrim((string) config('session.domain'), '.');
            if ($domain !== '' && $host !== $domain && ! str_ends_with($host, '.'.$domain)) {
                config(['session.domain' => null]);
            }
            if (! $request->isSecure()) {
                config(['session.secure' => false]);
            }
        }

        return $next($request);
    }
}
