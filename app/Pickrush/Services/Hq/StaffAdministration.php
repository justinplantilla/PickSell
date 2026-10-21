<?php

namespace App\Pickrush\Services\Hq;

use App\Pickrush\Enums\StaffRole;
use App\Pickrush\Models\Hub;
use App\Pickrush\Models\Staff;
use App\Pickrush\Services\OperationRefused;

/**
 * Staff accounts and their hub scope. HQ roles have no hub; hub roles must sit at a hub of the
 * right kind (sorters at province hubs, dispatchers at stations, managers at either).
 */
class StaffAdministration
{
    public function save(?Staff $staff, array $data, Staff $admin): Staff
    {
        $role = StaffRole::from($data['role']);
        $hub = empty($data['hub_id']) ? null : Hub::find($data['hub_id']);
        if ($role->isCompany()) {
            $hub = null;
        } elseif (! $hub) {
            throw new OperationRefused("A {$role->label()} must be posted to a hub.");
        } elseif (! in_array($hub->type, $role->hubTypes(), true)) {
            throw new OperationRefused("A {$role->label()} cannot work at a {$hub->type->value} hub.");
        }
        if ($staff?->is($admin) && ($role !== $staff->role || empty($data['is_active']))) {
            throw new OperationRefused('You cannot change your own role or deactivate yourself.');
        }
        if ($staff?->role === StaffRole::SuperAdmin && ($role !== StaffRole::SuperAdmin || empty($data['is_active'])) && $this->lastSuperAdmin($staff)) {
            throw new OperationRefused('Keep at least one active super admin.');
        }

        $attributes = [
            'full_name' => $data['full_name'],
            'email' => strtolower(trim($data['email'])),
            'mobile_no' => $data['mobile_no'] ?? null,
            'role' => $role,
            'hub_id' => $hub?->id,
            'is_active' => ! empty($data['is_active']),
            'is_sample' => false,
        ];
        if (! empty($data['password'])) {
            $attributes['password'] = $data['password'];
        }

        if ($staff) {
            $staff->update($attributes);

            return $staff;
        }
        if (empty($data['password'])) {
            throw new OperationRefused('Set a starting password for the new account.');
        }

        return Staff::create($attributes);
    }

    private function lastSuperAdmin(Staff $staff): bool
    {
        return ! Staff::where('role', StaffRole::SuperAdmin)->where('is_active', true)->whereKeyNot($staff->id)->exists();
    }
}
