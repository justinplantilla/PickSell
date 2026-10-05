<?php

namespace App\Policies;

use App\Auth\Permission;
use App\Models\Complaint;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ComplaintPolicy
{
    public function view(User $admin, Complaint $complaint): Response
    {
        return $admin->hasPermission(Permission::COMPLAINTS_VIEW)
            ? Response::allow()
            : Response::deny('You do not have permission to perform this action.');
    }

    public function update(User $admin, Complaint $complaint): Response
    {
        if (! $admin->hasPermission(Permission::COMPLAINTS_MANAGE)) {
            return Response::deny('You do not have permission to perform this action.');
        }

        // An admin who filed the complaint, or is its subject, must not decide it.
        return in_array((int) $admin->id, array_map('intval', array_filter([$complaint->filed_by, $complaint->against_user_id])), true)
            ? Response::deny('You cannot decide a complaint you are a party to.')
            : Response::allow();
    }
}
