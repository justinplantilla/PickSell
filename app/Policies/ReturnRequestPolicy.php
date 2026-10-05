<?php

namespace App\Policies;

use App\Auth\Permission;
use App\Models\ReturnRequest;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/** Admin review of escalated return disputes. */
class ReturnRequestPolicy
{
    /** Admins supervise every return (read-only); only escalated disputes can be decided. */
    public function view(User $admin, ReturnRequest $returnRequest): Response
    {
        return $admin->hasPermission(Permission::RETURNS_VIEW)
            ? Response::allow()
            : Response::deny('You do not have permission to perform this action.');
    }

    public function resolveDispute(User $admin, ReturnRequest $returnRequest): Response
    {
        if (! $admin->hasPermission(Permission::RETURNS_MANAGE)) {
            return Response::deny('You do not have permission to perform this action.');
        }

        return $returnRequest->status === 'rejected' && $returnRequest->dispute_status === 'open'
            ? Response::allow()
            : Response::denyWithStatus(422, 'This dispute is no longer open.');
    }
}
