<?php

namespace App\Support;

use App\Models\Order;
use App\Pickrush\Bridge\PicksellBridge;
use App\Pickrush\Models\Assignment;

/**
 * What the buyer sees about the last mile of an order: the rider bringing it, the photo the rider
 * took at the door, and whether the cash on delivery has been paid. Used by the website's order
 * pages and the buyer app. Only a completed delivery task counts as proof (a return to the seller
 * or a failed attempt is never shown to the buyer).
 */
final class OrderDelivery
{
    /** The rider's completed delivery to the buyer, with its proof photo. */
    public static function proof(Order $order): ?Assignment
    {
        $parcel = PicksellBridge::parcelFor($order);

        return $parcel ? Assignment::where('parcel_id', $parcel->id)->where('type', 'delivery')->where('status', 'completed')
            ->whereNotNull('proof_photo_path')->latest('completed_at')->first() : null;
    }

    /**
     * The rider on the way (with their number while they are delivering it) or the one who
     * delivered it (without). Null before a rider is assigned.
     *
     * @return array{name: string, vehicle: ?string, phone: ?string}|null
     */
    public static function courier(Order $order): ?array
    {
        $parcel = PicksellBridge::parcelFor($order);
        if (! $parcel) {
            return null;
        }
        $task = Assignment::with('rider')->where('parcel_id', $parcel->id)->where('type', 'delivery')
            ->whereIn('status', [...Assignment::OPEN, 'completed'])->latest('id')->first();
        $rider = $task?->rider;
        if (! $rider) {
            return null;
        }

        return [
            'name'    => $rider->full_name,
            'vehicle' => $rider->vehicleLabel(),
            // Riders call buyers while the parcel is out for delivery; not before or after.
            'phone'   => $task->status === 'out_for_delivery' ? $rider->mobile_no : null,
        ];
    }

    /** Cash on delivery: unpaid until the rider hands it over, then paid; refunded after a processed refund; null if cancelled first. */
    public static function paymentStatus(Order $order): ?string
    {
        if ($order->returnRequest?->refund?->status === 'processed') {
            return 'refunded';
        }
        if (in_array($order->status, ['delivered', 'completed'], true) || self::proof($order)) {
            return 'paid';
        }

        return $order->status === 'cancelled' ? null : 'unpaid';
    }
}
