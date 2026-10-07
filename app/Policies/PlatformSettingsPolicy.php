<?php

namespace App\Policies;

use App\Auth\Permission;
use App\Models\User;

class PlatformSettingsPolicy
{
    public function view(User $user): bool
    {
        return $user->hasPermission(Permission::SETTINGS_VIEW);
    }

    public function update(User $user): bool
    {
        return $user->hasPermission(Permission::SETTINGS_MANAGE);
    }

    public function manageFinancial(User $user): bool
    {
        return $user->hasPermission(Permission::COMMISSION_MANAGE);
    }
}
