<?php

namespace App\Pickrush\Support;

use App\Pickrush\Enums\StaffRole;
use App\Pickrush\Models\Staff;

/**
 * Sidebar menu per role. Hiding a link is not the control: every route checks the role too.
 * Modules are added here as each portal is built.
 */
final class Navigation
{
    /** @return list<array{label: string, route: string, active: string}> */
    public static function for(Staff $staff): array
    {
        if ($staff->hub?->isStation()) {
            return [
                ['label' => 'Dashboard', 'route' => 'pickrush.station.dashboard', 'active' => 'pickrush.station.dashboard'],
                ['label' => 'Receiving', 'route' => 'pickrush.station.receiving', 'active' => 'pickrush.station.receiving*'],
                ['label' => 'Dispatch', 'route' => 'pickrush.station.dispatch', 'active' => 'pickrush.station.dispatch*'],
                ['label' => 'Failed deliveries', 'route' => 'pickrush.station.failed', 'active' => 'pickrush.station.failed*'],
                ['label' => 'Trips', 'route' => 'pickrush.station.trips.index', 'active' => 'pickrush.station.trips.*'],
                ['label' => 'Riders', 'route' => 'pickrush.station.riders.index', 'active' => 'pickrush.station.riders.*'],
                ['label' => 'Zones', 'route' => 'pickrush.station.zones.index', 'active' => 'pickrush.station.zones.*'],
                ['label' => 'Cash (COD)', 'route' => 'pickrush.station.cash', 'active' => 'pickrush.station.cash*'],
                ['label' => 'Exceptions', 'route' => 'pickrush.exceptions.index', 'active' => 'pickrush.exceptions.*'],
                ['label' => 'Parcels', 'route' => 'pickrush.parcels.index', 'active' => 'pickrush.parcels.*'],
            ];
        }

        if ($staff->hub?->isProvince()) {
            return [
                ['label' => 'Dashboard', 'route' => 'pickrush.province.dashboard', 'active' => 'pickrush.province.dashboard'],
                ['label' => 'Inbound', 'route' => 'pickrush.province.inbound', 'active' => 'pickrush.province.inbound*'],
                ['label' => 'Sorting', 'route' => 'pickrush.province.sorting', 'active' => 'pickrush.province.sorting*'],
                ['label' => 'Outbound trips', 'route' => 'pickrush.province.trips.index', 'active' => 'pickrush.province.trips.*'],
                ['label' => 'Inventory on hand', 'route' => 'pickrush.province.inventory', 'active' => 'pickrush.province.inventory'],
                ['label' => 'Exceptions', 'route' => 'pickrush.exceptions.index', 'active' => 'pickrush.exceptions.*'],
                ['label' => 'Vehicles & drivers', 'route' => 'pickrush.fleet.index', 'active' => 'pickrush.fleet.*'],
                ['label' => 'Parcels', 'route' => 'pickrush.parcels.index', 'active' => 'pickrush.parcels.*'],
            ];
        }

        // HQ: what each company role uses, in the order they use it.
        $is = fn (StaffRole ...$roles) => $staff->hasRole(...$roles);
        $items = [
            ['label' => 'Dashboard', 'route' => 'pickrush.hq.dashboard', 'active' => 'pickrush.hq.dashboard'],
            ['label' => 'Alerts', 'route' => 'pickrush.hq.alerts', 'active' => 'pickrush.hq.alerts'],
            ['label' => 'Parcel tracking', 'route' => 'pickrush.parcels.index', 'active' => 'pickrush.parcels.*'],
        ];
        if ($is(StaffRole::SuperAdmin, StaffRole::Support)) {
            $items[] = ['label' => 'Addresses to fix', 'route' => 'pickrush.hq.address-fix', 'active' => 'pickrush.hq.address-fix*'];
        }
        $items[] = ['label' => 'Exceptions', 'route' => 'pickrush.exceptions.index', 'active' => 'pickrush.exceptions.*'];
        $items[] = ['label' => 'Hubs', 'route' => 'pickrush.hubs.index', 'active' => 'pickrush.hubs.*'];
        if ($is(StaffRole::SuperAdmin)) {
            $items[] = ['label' => 'Staff & roles', 'route' => 'pickrush.hq.staff.index', 'active' => 'pickrush.hq.staff.*'];
        }
        $items[] = ['label' => 'Finance', 'route' => 'pickrush.hq.finance', 'active' => 'pickrush.hq.finance*'];
        $items[] = ['label' => 'Reports', 'route' => 'pickrush.hq.reports', 'active' => 'pickrush.hq.reports*'];
        $items[] = ['label' => 'Rates & policies', 'route' => 'pickrush.hq.settings', 'active' => 'pickrush.hq.settings*'];
        if ($is(StaffRole::SuperAdmin, StaffRole::Finance)) {
            $items[] = ['label' => 'Audit log', 'route' => 'pickrush.hq.audit', 'active' => 'pickrush.hq.audit'];
        }

        return $items;
    }
}
