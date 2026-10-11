<?php

namespace App\Services\Orders;

class DeliveryStatusTransitionPolicy
{
    private const ALLOWED = [
        'assigned_to_rider' => ['out_for_delivery'],
        'out_for_delivery' => ['delivered', 'delivery_failed'],
        'delivery_failed' => ['assigned_to_rider', 'return_to_sender'],
        'return_to_sender' => ['returned'],
    ];

    public function canTransition(
        string $from,
        string $to,
        string $actorRole,
        int $attempts = 0,
        ?string $deliveryStatus = null,
    ): bool
    {
        if (in_array($actorRole, ['system', 'admin'], true)) {
            return true;
        }

        if ($from === 'delivery_failed' && $to === 'returned') {
            return $actorRole === 'logistics' && $deliveryStatus === 'return_to_sender';
        }

        $isRider = in_array($actorRole, ['courier', 'rider'], true);
        if (in_array($to, ['out_for_delivery', 'delivered', 'delivery_failed'], true)) {
            return $isRider && in_array($to, self::ALLOWED[$from] ?? [], true);
        }

        if ($from === 'delivery_failed' && $to === 'assigned_to_rider') {
            return $isRider && $attempts < 4;
        }

        if ($from === 'delivery_failed' && $to === 'return_to_sender') {
            return $isRider && $attempts > 3;
        }

        if ($from === 'return_to_sender' && $to === 'returned') {
            return $actorRole === 'logistics';
        }

        if (in_array($actorRole, ['logistics', 'seller', 'buyer'], true)) {
            return true;
        }

        if (! $isRider) {
            return true;
        }

        if (! in_array($to, self::ALLOWED[$from] ?? [], true)) {
            return false;
        }

        return $isRider;
    }
}
