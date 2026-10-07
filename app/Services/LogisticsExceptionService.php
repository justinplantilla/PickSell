<?php

namespace App\Services;

use App\Auth\Permission;
use App\Models\LogisticsException;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class LogisticsExceptionService
{
    public function __construct(private AuditLogger $audit, private AdminNotificationService $notifications) {}

    public function open(
        Order $order,
        User $actor,
        string $type,
        string $description,
        ?string $permission = null,
    ): LogisticsException {
        return DB::transaction(function () use ($order, $actor, $type, $description, $permission) {
            $lockedOrder = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            $exception = LogisticsException::create([
                'order_id' => $lockedOrder->id,
                'opened_by' => $actor->id,
                'type' => $type,
                'status' => 'open',
                'description' => $description,
            ]);

            $this->audit->record('logistics.exception_opened', $exception, [], [
                'order_id' => $lockedOrder->id,
                'type' => $type,
            ], $permission);
            $this->notifications->notifyAdmins(
                'order.exception',
                'Order exception opened',
                "A logistics exception was opened for order {$lockedOrder->order_number}.",
                route('admin.orders.show', $lockedOrder, false),
                $actor->hasPermission(Permission::ACCOUNT_VIEW) ? $actor->id : null,
            );

            return $exception;
        });
    }

    public function resolve(
        LogisticsException $exception,
        User $actor,
        string $resolution,
        ?string $permission = null,
    ): void {
        DB::transaction(function () use ($exception, $actor, $resolution, $permission) {
            $locked = LogisticsException::whereKey($exception->id)->lockForUpdate()->firstOrFail();
            abort_if($locked->status !== 'open', 409, 'This logistics exception has already been resolved.');

            $before = $locked->status;
            $locked->update([
                'status' => 'resolved',
                'resolution' => $resolution,
                'resolved_by' => $actor->id,
                'resolved_at' => now(),
            ]);

            $this->audit->record('logistics.exception_resolved', $locked, [
                'status' => ['from' => $before, 'to' => $locked->status],
            ], [
                'order_id' => $locked->order_id,
                'type' => $locked->type,
                'resolution' => $resolution,
            ], $permission);
        });
    }
}
