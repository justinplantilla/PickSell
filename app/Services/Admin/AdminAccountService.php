<?php

namespace App\Services\Admin;

use App\Auth\Permission;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class AdminAccountService
{
    public function __construct(private AuditLogger $audit) {}

    public function updateProfile(User $admin, array $values): bool
    {
        return DB::transaction(function () use ($admin, $values): bool {
            $changes = AuditLogger::diff($admin, $values, ['first_name', 'last_name', 'email', 'contact_no']);
            if ($changes === []) {
                return false;
            }

            $admin->update($values);
            $this->audit->record('admin_account.profile_updated', $admin, $changes, [], Permission::ACCOUNT_MANAGE);

            return true;
        });
    }

    public function changePassword(User $admin, string $password): void
    {
        DB::transaction(function () use ($admin, $password): void {
            $admin->forceFill(['password' => Hash::make($password)])->save();
            $this->audit->record(
                'admin_account.password_changed',
                $admin,
                ['password_changed' => ['from' => false, 'to' => true]],
                [],
                Permission::ACCOUNT_MANAGE,
            );
        });
    }

    public function delete(User $admin): void
    {
        DB::transaction(function () use ($admin): void {
            $this->audit->record(
                'admin_account.deleted',
                $admin,
                [],
                ['deleted_actor_id' => $admin->id],
                Permission::ACCOUNT_MANAGE,
            );

            $admin->delete();
        });

        Log::notice('An administrator account was deleted.', ['user_id' => $admin->id]);
        Auth::logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();
    }
}
