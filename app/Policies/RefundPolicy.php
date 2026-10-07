<?php

namespace App\Policies;

use App\Auth\Permission;
use App\Models\Refund;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class RefundPolicy
{
    public function view(User $admin, Refund $refund): Response
    {
        return $admin->hasPermission(Permission::REFUNDS_VIEW)
            ? Response::allow()
            : Response::deny('You do not have permission to view refunds.');
    }

    public function approve(User $admin, Refund $refund): Response
    {
        return $this->review($admin, $refund, Permission::REFUNDS_APPROVE);
    }

    public function reject(User $admin, Refund $refund): Response
    {
        return $this->review($admin, $refund, Permission::REFUNDS_MANAGE);
    }

    private function review(User $admin, Refund $refund, string $permission): Response
    {
        if (! $admin->hasPermission($permission)) {
            return Response::deny('You do not have permission to review refunds.');
        }

        if (in_array((int) $admin->id, array_map('intval', array_filter([
            $refund->requested_by,
            $refund->order?->buyer_id,
            $refund->order?->seller_id,
        ])), true)) {
            return Response::deny('A refund participant cannot approve or reject the refund.');
        }

        return $refund->status === 'requested'
            ? Response::allow()
            : Response::denyWithStatus(422, 'Only a pending refund can be reviewed.');
    }
}
