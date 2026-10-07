<?php

namespace App\Policies;

use App\Auth\Permission;
use App\Models\LogisticsException;
use App\Models\Order;
use App\Models\User;
use App\Services\Orders\OrderAdministrationService;
use Illuminate\Auth\Access\Response;

class LogisticsPolicy
{
    public function scanParcel(User $user, Order $order): Response
    {
        return $this->canOperate($user, $order, Permission::LOGISTICS_SCAN)
            ? Response::allow()
            : Response::deny('You do not have permission to scan logistics parcels.');
    }

    public function assignCourier(User $user, Order $order): Response
    {
        return $this->canOperate($user, $order, Permission::LOGISTICS_ASSIGN_RIDER)
            ? Response::allow()
            : Response::deny('You do not have permission to assign logistics couriers.');
    }

    public function openException(User $user, Order $order): Response
    {
        return $this->canOperate($user, $order, Permission::LOGISTICS_MANAGE)
            ? Response::allow()
            : Response::deny('You do not have permission to open logistics exceptions for this parcel.');
    }

    public function resolveParcelException(User $user, LogisticsException $exception): Response
    {
        $order = $exception->order;
        if (!$order) {
            return Response::deny('The parcel for this logistics exception no longer exists.');
        }

        return $this->canOperate($user, $order, Permission::LOGISTICS_RESOLVE_EXCEPTION)
            ? Response::allow()
            : Response::deny('You do not have permission to resolve this logistics exception.');
    }

    public function resolveLogisticsException(User $user, Order $order, string $action): Response
    {
        if (! $user->hasPermission(Permission::LOGISTICS_RESOLVE_EXCEPTION)) {
            return Response::deny('You do not have permission to resolve logistics exceptions.');
        }
        if (! array_key_exists($action, OrderAdministrationService::EXCEPTION_ACTIONS)) {
            return Response::deny('Unknown logistics exception resolution.');
        }

        return array_key_exists($action, OrderAdministrationService::exceptionActions($order))
            ? Response::allow()
            : Response::denyWithStatus(409, OrderAdministrationService::EXCEPTION_ACTIONS[$action]['label'] . ' does not apply to an order that is ' . ucfirst(str_replace('_', ' ', $order->status)) . '.');
    }

    private function canOperate(User $user, Order $order, string $permission): bool
    {
        if ($user->role === 'logistics') {
            return $user->isApproved() && $order->logistics_id === $user->id;
        }

        return $user->hasPermission($permission);
    }
}
