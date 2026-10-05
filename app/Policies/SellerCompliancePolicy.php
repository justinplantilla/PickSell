<?php

namespace App\Policies;

use App\Auth\Permission;
use App\Models\ComplianceCase;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Seller compliance. Case abilities (view, addNote, resolve) are the ComplianceCase policy; seller
 * abilities are registered as gates (viewSellerCompliance, openComplianceCase, warnSeller,
 * suspendSeller, reinstateSeller) because UserPolicy already owns the User model.
 */
class SellerCompliancePolicy
{
    // Seller-level ----------------------------------------------------------------------------

    public function viewSeller(User $admin, User $seller): Response
    {
        if (! $admin->hasPermission(Permission::SELLER_COMPLIANCE_VIEW)) {
            return $this->noPermission();
        }

        return $seller->role === 'seller' ? Response::allow() : Response::denyAsNotFound();
    }

    public function openCase(User $admin, User $seller): Response
    {
        return $this->manageSeller($admin, $seller)
            ?? (in_array($seller->status, ['approved', 'suspended'], true)
                ? Response::allow()
                : Response::deny('Cases can only be opened for active or suspended sellers.'));
    }

    public function warn(User $admin, User $seller): Response
    {
        return $this->manageSeller($admin, $seller)
            ?? ($seller->status === 'approved' ? Response::allow() : Response::deny('Warnings can only be issued to active sellers.'));
    }

    public function suspend(User $admin, User $seller): Response
    {
        return $this->manageSeller($admin, $seller)
            ?? ($seller->status === 'approved' ? Response::allow() : Response::denyWithStatus(409, "This seller is {$seller->status}, not active."));
    }

    public function reinstate(User $admin, User $seller): Response
    {
        return $this->manageSeller($admin, $seller)
            ?? ($seller->status === 'suspended' ? Response::allow() : Response::denyWithStatus(409, 'Only suspended sellers can be reinstated.'));
    }

    // Case-level (ComplianceCase policy) ----------------------------------------------------

    public function view(User $admin, ComplianceCase $case): Response
    {
        return $admin->hasPermission(Permission::SELLER_COMPLIANCE_VIEW) ? Response::allow() : $this->noPermission();
    }

    public function addNote(User $admin, ComplianceCase $case): Response
    {
        if (! $admin->hasPermission(Permission::SELLER_COMPLIANCE_MANAGE)) {
            return $this->noPermission();
        }

        return $case->isOpen() ? Response::allow() : Response::denyWithStatus(409, 'This case is closed.');
    }

    public function resolve(User $admin, ComplianceCase $case): Response
    {
        return $this->addNote($admin, $case);
    }

    private function manageSeller(User $admin, User $seller): ?Response
    {
        if (! $admin->hasPermission(Permission::SELLER_COMPLIANCE_MANAGE)) {
            return $this->noPermission();
        }

        return $seller->role === 'seller' ? null : Response::deny('Compliance actions apply to sellers only.');
    }

    private function noPermission(): Response
    {
        return Response::deny('You do not have permission to perform this action.');
    }
}
