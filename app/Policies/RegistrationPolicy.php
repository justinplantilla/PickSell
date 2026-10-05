<?php

namespace App\Policies;

use App\Auth\Permission;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Registration review decisions. Registered as the viewRegistration / approveRegistration /
 * disapproveRegistration gates (UserPolicy owns account-status abilities on the same model).
 * Order of checks: permission → resource → business rule; anything not allowed is denied.
 */
class RegistrationPolicy
{
    public function view(User $admin, User $applicant): Response
    {
        return $this->permission($admin, Permission::REGISTRATIONS_VIEW)
            ?? $this->reviewable($admin, $applicant)
            ?? Response::allow();
    }

    public function approve(User $admin, User $applicant): Response
    {
        return $this->decide($admin, $applicant);
    }

    public function disapprove(User $admin, User $applicant): Response
    {
        return $this->decide($admin, $applicant);
    }

    private function decide(User $admin, User $applicant): Response
    {
        if ($denied = $this->permission($admin, Permission::REGISTRATIONS_MANAGE) ?? $this->reviewable($admin, $applicant)) {
            return $denied;
        }
        if ($applicant->role === 'courier') {
            return Response::deny('Courier applications must be reviewed by Logistics.');
        }

        // Blocks repeated decisions: once approved or disapproved, the application is closed.
        return $applicant->status === 'pending'
            ? Response::allow()
            : Response::denyWithStatus(409, "This application was already {$applicant->status}.");
    }

    private function permission(User $admin, string $permission): ?Response
    {
        return $admin->hasPermission($permission) ? null : Response::deny('You do not have permission to perform this action.');
    }

    private function reviewable(User $admin, User $applicant): ?Response
    {
        if ($admin->is($applicant)) {
            return Response::deny('You cannot review your own registration.');
        }

        return in_array($applicant->role, UserPolicy::MANAGED_ROLES, true)
            ? null
            : Response::deny('This account is not managed through the admin portal.');
    }
}
