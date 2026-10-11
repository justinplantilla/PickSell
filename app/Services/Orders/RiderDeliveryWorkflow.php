<?php

namespace App\Services\Orders;

use App\Models\Order;
use App\Models\User;
use App\Services\DeliveryDomainSynchronizer;

class RiderDeliveryWorkflow
{
    public function __construct(
        private OrderLifecycleService $lifecycle,
        private DeliveryDomainSynchronizer $deliveries,
    ) {}

    /** Caller holds an order row lock and an open transaction. */
    public function update(
        Order $order,
        User $rider,
        string $status,
        ?string $reason = null,
        array $logContext = [],
    ): void {
        abort_unless($rider->role === 'courier' && $order->courier_id === $rider->id, 403);

        $trackingStatus = match ($status) {
            'out_for_delivery' => 'Out for delivery',
            'delivered' => 'Delivered',
            'delivery_failed' => 'Delivery failed',
            default => abort(422, 'Unsupported rider delivery status.'),
        };

        $order->deliveryLogContext = $logContext;
        $this->lifecycle->transition(
            $order,
            $status,
            $rider->id,
            'courier',
            $reason,
            ['tracking_status' => $trackingStatus],
        );

        if ($status !== 'delivery_failed') {
            return;
        }

        $order->refresh();
        $attempts = $order->delivery()->firstOrFail()->delivery_attempts;
        if ($attempts <= 3) {
            $this->lifecycle->transition(
                $order,
                'assigned_to_rider',
                $rider->id,
                'courier',
                "Delivery attempt {$attempts} failed; retry remains available.",
                [
                    'courier_id' => $rider->id,
                    'tracking_status' => 'Delivery attempt failed; rider may retry',
                ],
            );

            return;
        }

        $this->deliveries->markReturnToSender(
            $order,
            $rider->id,
            'Delivery attempts exceeded the maximum of three retries. '.($reason ?? ''),
        );
    }
}
