<?php

namespace App\Services;

use App\Notifications\CourierAssignedNotification;
use App\Models\Order;
use App\Models\User;
use App\Services\Orders\OrderLifecycleService;
use Illuminate\Support\Facades\DB;

class CourierAssignmentService
{
    public function __construct(
        private LogisticsRoutingService $routing,
        private OrderLifecycleService $lifecycle,
        private AuditLogger $audit,
    ) {}

    public function assign(Order $order, User $actor, ?int $courierId, string $source, ?string $auditPermission = null): User
    {
        return DB::transaction(function () use ($order, $actor, $courierId, $source, $auditPermission) {
            $locked = Order::whereKey($order->id)->with('buyer')->lockForUpdate()->firstOrFail();
            abort_if($locked->status !== 'sorted', 422, 'Only sorted parcels can be assigned.');
            abort_unless($locked->parcelScans()->exists(), 422, 'A parcel scan is required before rider assignment.');

            $courier = $courierId !== null
                ? User::whereKey($courierId)->where('role', 'courier')->where('status', 'approved')->first()
                : $this->routing->suggestedCourier($locked);
            abort_if(!$courier, 422, 'No active rider is assigned to this barangay yet.');
            abort_unless($this->routing->courierCoversOrder($courier, $locked), 422, 'The selected rider is not assigned to this parcel destination.');

            $from = $locked->status;
            $fromCourierId = $locked->courier_id;
            $area = $locked->buyer
                ? trim(collect([$locked->buyer->municipality, $locked->buyer->province])->filter()->join(', '))
                : 'Unspecified area';

            $reason = $source === 'admin' ? 'Rider assigned by admin' : null;
            $this->lifecycle->transition($locked, 'assigned_to_rider', $actor->id, $source, $reason, [
                'courier_id' => $courier->id,
                'assigned_at' => now(),
                'tracking_status' => 'Sorted for ' . $area . '; assigned to ' . $courier->full_name,
            ]);

            $this->audit->record('logistics.rider_assigned', $locked, [
                'status' => ['from' => $from, 'to' => $locked->status],
                'courier_id' => ['from' => $fromCourierId, 'to' => $courier->id],
            ], [], $auditPermission);

            $courier->notify(new CourierAssignedNotification($locked));

            return $courier;
        });
    }
}
