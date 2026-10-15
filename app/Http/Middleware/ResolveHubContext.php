<?php

namespace App\Http\Middleware;

use App\Models\LogisticsBranch;
use App\Support\LogisticsDomains;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

/**
 * Resolves which hub context the current request belongs to and sets
 * request attributes used throughout the logistics portal:
 *
 *   hub_context  = 'superadmin' | 'province_hub' | 'station_hub'
 *   province_hub = LogisticsBranch (when hub_context is province_hub)
 *   station_hub  = LogisticsBranch (when hub_context is station_hub)
 *
 * Domain hierarchy (see config/logistics.php):
 *   logistics.pick-sell.shop        → superadmin (company portal)
 *   hub-cebu.pick-sell.shop         → province hub
 *   station-mandaue.pick-sell.shop  → station hub
 */
class ResolveHubContext
{
    public function handle(Request $request, Closure $next)
    {
        $host = strtolower($request->getHost());
        $user = $request->user();

        [$hubType, $subdomain] = LogisticsDomains::parseHubHost($host) ?? [null, null];

        if ($host === LogisticsDomains::superadminHost() || $this->isLocalHost($host)) {
            if ($user->assigned_hub_id !== null) {
                return redirect()->away($user->assignedHub->portalUrl());
            }
            $request->attributes->set('hub_context', 'superadmin');

            return $next($request);
        }

        if ($hubType === 'province_hub') {
            $hub = LogisticsBranch::query()
                ->where('hub_type', 'province_hub')
                ->where('subdomain', $subdomain)
                ->where('status', 'active')
                ->firstOrFail();

            abort_unless(
                (int) $user->assigned_hub_id === (int) $hub->id,
                403,
                'This province hub is not assigned to your account.',
            );

            $request->attributes->set('hub_context', 'province_hub');
            $request->attributes->set('province_hub', $hub);
            URL::defaults(['province' => $hub->subdomain]);
            // The domain parameter would otherwise be passed positionally into controller actions.
            $request->route()?->forgetParameter('province');

            return $next($request);
        }

        if ($hubType === 'station_hub') {
            $station = LogisticsBranch::query()
                ->where('hub_type', 'station_hub')
                ->where('subdomain', $subdomain)
                ->where('status', 'active')
                ->whereHas('parent', fn ($hub) => $hub
                    ->where('hub_type', 'province_hub')
                    ->where('status', 'active'))
                ->firstOrFail();

            abort_unless(
                (int) $user->assigned_hub_id === (int) $station->id,
                403,
                'This station hub is not assigned to your account.',
            );

            $request->attributes->set('hub_context', 'station_hub');
            $request->attributes->set('station_hub', $station);
            URL::defaults(['station' => $station->subdomain]);
            $request->route()?->forgetParameter('station');

            return $next($request);
        }

        abort(404, 'Unknown logistics portal.');
    }

    private function isLocalHost(string $host): bool
    {
        return app()->environment(['local', 'testing']) && in_array($host, ['localhost', '127.0.0.1', 'test'], true);
    }
}
