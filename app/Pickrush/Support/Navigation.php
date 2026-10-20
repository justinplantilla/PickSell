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

        $items = [['label' => 'Dashboard', 'route' => 'pickrush.dashboard', 'active' => 'pickrush.dashboard']];
        if ($staff->hasRole(StaffRole::SuperAdmin, StaffRole::Finance, StaffRole::Support)) {
            $items[] = ['label' => 'Hubs', 'route' => 'pickrush.hubs.index', 'active' => 'pickrush.hubs.*'];
        }
        $items[] = ['label' => 'Parcels', 'route' => 'pickrush.parcels.index', 'active' => 'pickrush.parcels.*'];

        return $items;
    }
}
