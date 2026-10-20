<?php

namespace App\Http\Middleware;

use App\Support\LogisticsDomains;
use Closure;
use Illuminate\Http\Request;

/**
 * Resolves which hub context the current request belongs to and sets
 * request attributes used throughout the logistics portal:
 *
 *   hub_context  = 'superadmin' | 'province_hub' | 'station_hub'
 *   province_hub = LogisticsBranch (when hub_context is province_hub)
 *   station_hub  = LogisticsBranch (when hub_context is station_hub)
 *
 * Every portal lives on the one logistics host (see config/logistics.php); the account decides the portal:
 *   no assigned hub          → company portal   /logistics/...
 *   assigned province hub    → province hub     /logistics/hub/...
 *   assigned station hub     → station hub      /logistics/station/...
 */
class ResolveHubContext
{
    public function handle(Request $request, Closure $next)
    {
        $host = strtolower($request->getHost());
        abort_unless($host === LogisticsDomains::superadminHost() || $this->isLocalHost($host), 404, 'Unknown logistics portal.');

        $user = $request->user();
        $portal = $this->requestedPortal($request);
        $hub = $user->assignedHub;

        if ($hub === null) {
            abort_unless($portal === 'superadmin', 403, $portal === 'province_hub'
                ? 'This province hub is not assigned to your account.'
                : 'This station hub is not assigned to your account.');
            $request->attributes->set('hub_context', 'superadmin');

            return $next($request);
        }

        // Hub operators only ever see their own hub's portal.
        if ($portal !== $hub->hub_type) {
            return redirect($hub->portalUrl());
        }

        $active = $hub->status === 'active'
            && (! $hub->isStationHub() || ($hub->parent?->isProvinceHub() && $hub->parent->status === 'active'));
        abort_unless($active, 404);

        $request->attributes->set('hub_context', $hub->hub_type);
        $request->attributes->set($hub->hub_type, $hub);

        return $next($request);
    }

    private function requestedPortal(Request $request): string
    {
        $name = (string) $request->route()?->getName();

        return match (true) {
            str_starts_with($name, 'province-hub.') => 'province_hub',
            str_starts_with($name, 'station-hub.') => 'station_hub',
            default => 'superadmin',
        };
    }

    private function isLocalHost(string $host): bool
    {
        return app()->environment(['local', 'testing']) && in_array($host, ['localhost', '127.0.0.1', 'test'], true);
    }
}
