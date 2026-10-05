<?php

namespace App\Policies;

use App\Auth\Permission;
use App\Models\Order;
use App\Models\User;
use App\Services\Orders\OrderAdministrationService;
use App\Services\Orders\OrderLifecycleService;
use Illuminate\Auth\Access\Response;

/** Admin order supervision: permission → business rule (valid lifecycle transition); deny by default. */
class OrderPolicy
{
    public function view(User $admin, Order $order): Response
    {
        return $admin->hasPermission(Permission::ORDERS_VIEW)
            ? Response::allow()
            : Response::deny('You do not have permission to perform this action.');
    }

    public function overrideStatus(User $admin, Order $order, string $to): Response
    {
        if (! $admin->hasPermission(Permission::ORDERS_OVERRIDE_STATUS)) {
            return Response::deny('You do not have permission to override order status.');
        }
        if (in_array($to, OrderAdministrationService::OVERRIDE_BLOCKED_TARGETS, true)) {
            return Response::deny(OrderLifecycleService::label($to) . ' is set by the seller or logistics team, who supply the waybill or rider.');
        }

        return in_array($to, OrderAdministrationService::overrideTargets($order), true)
            ? Response::allow()
            : Response::denyWithStatus(409, 'An order cannot move from ' . OrderLifecycleService::label($order->status) . ' to ' . OrderLifecycleService::label($to) . '.');
    }

    public function resolveException(User $admin, Order $order, string $action): Response
    {
        if (! $admin->hasPermission(Permission::ORDERS_MANAGE)) {
            return Response::deny('You do not have permission to manage orders.');
        }
        if (! array_key_exists($action, OrderAdministrationService::EXCEPTION_ACTIONS)) {
            return Response::deny('Unknown resolution.');
        }

        return array_key_exists($action, OrderAdministrationService::exceptionActions($order))
            ? Response::allow()
            : Response::denyWithStatus(409, OrderAdministrationService::EXCEPTION_ACTIONS[$action]['label'] . ' does not apply to an order that is ' . OrderLifecycleService::label($order->status) . '.');
    }
}
