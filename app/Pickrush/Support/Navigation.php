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
        $items = [['label' => 'Dashboard', 'route' => 'pickrush.dashboard', 'active' => 'pickrush.dashboard']];

        if ($staff->hasRole(StaffRole::SuperAdmin, StaffRole::Finance, StaffRole::Support)) {
            $items[] = ['label' => 'Hubs', 'route' => 'pickrush.hubs.index', 'active' => 'pickrush.hubs.*'];
        }

        return $items;
    }
}
