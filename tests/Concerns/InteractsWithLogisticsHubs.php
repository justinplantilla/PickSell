<?php

namespace Tests\Concerns;

use App\Models\LogisticsBranch;
use App\Models\Municipality;
use App\Models\User;

trait InteractsWithLogisticsHubs
{
    protected function createProvinceHub(User $company, Municipality $municipality, string $subdomain): LogisticsBranch
    {
        return LogisticsBranch::create([
            'municipality_id' => $municipality->id,
            'logistics_id' => $company->id,
            'hub_type' => 'province_hub',
            'subdomain' => $subdomain,
            'name' => ucfirst($subdomain).' Province Hub',
            'status' => 'active',
        ]);
    }

    protected function createStationHub(LogisticsBranch $provinceHub, Municipality $municipality, string $subdomain, ?string $name = null): LogisticsBranch
    {
        return LogisticsBranch::create([
            'municipality_id' => $municipality->id,
            'logistics_id' => $provinceHub->logistics_id,
            'hub_type' => 'station_hub',
            'parent_id' => $provinceHub->id,
            'subdomain' => $subdomain,
            'name' => $name ?? ucfirst($subdomain).' Station',
            'status' => 'active',
        ]);
    }

    protected function createHubOperator(LogisticsBranch $hub): User
    {
        return User::create([
            'role' => 'logistics',
            'status' => 'approved',
            'assigned_hub_id' => $hub->id,
            'first_name' => 'Operator',
            'last_name' => ucfirst((string) $hub->subdomain),
            'email' => "operator-{$hub->id}@hubs.test",
            'password' => bcrypt('password'),
            'sex' => 'Female',
            'contact_no' => '09171234567',
            'birthday' => '1990-01-01',
            'age' => 36,
            'province' => 'Metro Manila',
            'municipality' => 'Pasig',
            'barangay' => 'Hub Operations',
        ]);
    }

    /** Absolute URL of a station-hub portal route, on that station's own subdomain. */
    protected function stationRoute(LogisticsBranch $station, string $name, array $parameters = []): string
    {
        return route($name, ['station' => $station->subdomain, ...$parameters]);
    }

    /** Absolute URL of a province-hub portal route, on that hub's own subdomain. */
    protected function provinceRoute(LogisticsBranch $hub, string $name, array $parameters = []): string
    {
        return route($name, ['province' => $hub->subdomain, ...$parameters]);
    }

    /** Absolute URL of a company (superadmin) logistics portal route. */
    protected function companyRoute(string $name, array $parameters = []): string
    {
        return 'https://'.config('logistics.superadmin_domain').route($name, $parameters, false);
    }
}
