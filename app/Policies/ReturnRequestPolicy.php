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

        // A rejected request, or a returned item the seller reported a problem with.
        return in_array($returnRequest->status, ['rejected', 'received'], true) && $returnRequest->dispute_status === 'open'
            ? Response::allow()
            : Response::denyWithStatus(422, 'This dispute is no longer open.');
    }

    public function approve(User $admin, ReturnRequest $returnRequest): Response
    {
        return $this->manageAtStatus($admin, $returnRequest, 'requested');
    }

    public function reject(User $admin, ReturnRequest $returnRequest): Response
    {
        return $this->manageAtStatus($admin, $returnRequest, 'requested');
    }

    public function inspect(User $admin, ReturnRequest $returnRequest): Response
    {
        return $this->manageAtStatus($admin, $returnRequest, 'received');
    }

    public function approveRefund(User $admin, ReturnRequest $returnRequest): Response
    {
        return $this->manageAtStatus($admin, $returnRequest, 'inspected');
    }

    /** Refund the buyer without waiting for (or after not getting) the item back (ReturnFlow::refundWithoutItem). */
    public function refundWithoutItem(User $admin, ReturnRequest $returnRequest): Response
    {
        return $this->manageAtStatus($admin, $returnRequest, 'requested', 'awaiting_item', 'received');
    }

    private function manageAtStatus(User $admin, ReturnRequest $returnRequest, string ...$statuses): Response
    {
        if (! $admin->hasPermission(Permission::RETURNS_MANAGE)) {
            return Response::deny('You do not have permission to perform this action.');
        }

        return in_array($returnRequest->status, $statuses, true) && $returnRequest->dispute_status !== 'open'
            ? Response::allow()
            : Response::denyWithStatus(422, 'This return request is no longer in the expected state.');
    }
}
