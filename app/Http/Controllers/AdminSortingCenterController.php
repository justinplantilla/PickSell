<?php

namespace App\Http\Controllers;

use App\Auth\Permission;
use App\Http\Requests\AssignCourierRequest;
use App\Http\Requests\OpenLogisticsExceptionRequest;
use App\Http\Requests\ResolveLogisticsExceptionRequest;
use App\Http\Requests\ResolveParcelExceptionRequest;
use App\Http\Requests\ScanParcelRequest;
use App\Models\LogisticsException;
use App\Models\Order;
use App\Services\CourierAssignmentService;
use App\Services\LogisticsService;
use App\Services\LogisticsExceptionService;
use App\Services\Orders\OrderAdministrationService;
use Illuminate\Support\Facades\Gate;

class AdminSortingCenterController extends Controller
{
    public function scan(ScanParcelRequest $request, Order $order, LogisticsService $logistics)
    {
        $logistics->scanParcel($order, $request->user(), 'admin', Permission::LOGISTICS_SCAN, $request->validated());

        return back()->with('success', "Parcel {$order->order_number} scanned.");
    }

    public function assign(AssignCourierRequest $request, Order $order, CourierAssignmentService $assignments)
    {
        Gate::authorize(Permission::LOGISTICS_ASSIGN_RIDER);
        Gate::authorize('assignCourier', $order);

        $data = $request->validated();
        $courier = $assignments->assign(
            $order,
            $request->user(),
            (int) $data['courier_id'],
            'admin',
            Permission::LOGISTICS_ASSIGN_RIDER,
        );

        return back()->with('success', "Parcel {$order->order_number} assigned to {$courier->full_name}.");
    }

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

        return back()->with('success', OrderAdministrationService::EXCEPTION_ACTIONS[$data['action']]['label'] . " applied to {$order->order_number}.");
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
