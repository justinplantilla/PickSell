<?php

namespace App\Http\Controllers;

use App\Auth\Permission;
use App\Http\Requests\OpenLogisticsExceptionRequest;
use App\Http\Requests\ResolveLogisticsExceptionRequest;
use App\Http\Requests\ResolveParcelExceptionRequest;
use App\Models\LogisticsException;
use App\Models\Order;
use App\Services\LogisticsExceptionService;
use App\Services\Orders\OrderAdministrationService;

class AdminLogisticsExceptionController extends Controller
{
    public function resolveException(ResolveLogisticsExceptionRequest $request, Order $order, OrderAdministrationService $orders)
    {
        $data = $request->validated();
        $orders->resolveException(
            $order,
            $request->user(),
            $data['action'],
            $data['reason'],
            Permission::LOGISTICS_RESOLVE_EXCEPTION,
        );

        return back()->with('success', OrderAdministrationService::EXCEPTION_ACTIONS[$data['action']]['label']." applied to {$order->order_number}.");
    }

    public function openException(
        OpenLogisticsExceptionRequest $request,
        Order $order,
        LogisticsExceptionService $exceptions,
    ) {
        $data = $request->validated();
        $exception = $exceptions->open(
            $order,
            $request->user(),
            $data['type'],
            $data['description'],
            Permission::LOGISTICS_MANAGE,
        );

        return back()->with('success', "Logistics exception #{$exception->id} opened.");
    }

    public function resolveParcelException(
        ResolveParcelExceptionRequest $request,
        LogisticsException $exception,
        LogisticsExceptionService $exceptions,
    ) {
        $exceptions->resolve(
            $exception,
            $request->user(),
            $request->validated('resolution'),
            Permission::LOGISTICS_RESOLVE_EXCEPTION,
        );

        return back()->with('success', "Logistics exception #{$exception->id} resolved.");
    }
}
