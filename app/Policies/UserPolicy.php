<?php

namespace App\Policies;

use App\Auth\Permission;
use App\Models\User;
use App\Services\Admin\UserModerationService;
use Illuminate\Auth\Access\Response;

/**
 * Admin operations on user accounts. Each ability checks, in order:
 * permission → resource (which accounts admins may act on) → business rule (valid transition).
 * Anything not explicitly allowed is denied. Registration decisions live in RegistrationPolicy.
 */
class UserPolicy
{
    /** Account roles the admin portal manages. Admin accounts are never targets. */
    public const MANAGED_ROLES = ['buyer', 'seller', 'courier', 'logistics'];

    public function viewAccount(User $admin, User $user): Response
    {
        if (! $admin->hasPermission(Permission::USERS_VIEW)) {
            return Response::deny('You do not have permission to perform this action.');
        }

        return in_array($user->role, self::MANAGED_ROLES, true)
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    public function updateStatus(User $admin, User $user, string $status): Response
    {
        if ($denied = $this->permission($admin, Permission::USERS_MANAGE) ?? $this->managedAccount($admin, $user)) {
            return $denied;
        }
        if (! array_key_exists($status, UserModerationService::ACTION_LABELS)) {
            return Response::deny('Unsupported account status.');
        }
        if ($user->status === $status) {
            return Response::denyWithStatus(409, "This account is already {$status}.");
        }
        if (in_array($user->status, ['pending', 'disapproved'], true)) {
            return Response::deny('Pending or disapproved applications must be decided through Registrations.');
        }

        return in_array($status, UserModerationService::allowedTransitions($user->status), true)
            ? Response::allow()
            : Response::deny("An account cannot move from {$user->status} to {$status}.");
    }

    private function permission(User $admin, string $permission): ?Response
    {
        return $admin->hasPermission($permission) ? null : Response::deny('You do not have permission to perform this action.');
    }

    private function managedAccount(User $admin, User $user): ?Response
    {
        if ($admin->is($user)) {
            return Response::deny('You cannot perform this action on your own account.');
        }

        return in_array($user->role, self::MANAGED_ROLES, true) ? null : Response::deny('This account is not managed through the admin portal.');
    }
}
