<?php

namespace App\Policies;

use App\Auth\Permission;
use App\Models\Product;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/** Admin moderation of products: permission → resource → business rule; deny by default. */
class ProductPolicy
{
    public function view(User $admin, Product $product): Response
    {
        return $admin->hasPermission(Permission::PRODUCTS_VIEW)
            ? Response::allow()
            : Response::deny('You do not have permission to perform this action.');
    }

    public function toggleFeatured(User $admin, Product $product): Response
    {
        if (! $admin->hasPermission(Permission::PRODUCTS_MODERATE)) {
            return Response::deny('You do not have permission to perform this action.');
        }

        // Un-featuring is always allowed; only active products can be featured.
        return $product->is_featured || $product->status === 'active'
            ? Response::allow()
            : Response::deny('Only active products can be featured.');
    }

    public function moderate(User $admin, Product $product, string $status): Response
    {
        if (! $admin->hasPermission(Permission::PRODUCTS_MODERATE)) {
            return Response::deny('You do not have permission to perform this action.');
        }
        if (! in_array($status, ['active', 'archived'], true)) {
            return Response::deny('Unsupported product status.');
        }

        return $product->status !== $status
            ? Response::allow()
            : Response::denyWithStatus(409, "This product is already {$status}.");
    }
}
