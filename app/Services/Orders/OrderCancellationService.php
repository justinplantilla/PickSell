<?php

namespace App\Services\Orders;

use App\Models\Order;
use App\Models\User;
use App\Pickrush\Bridge\PicksellBridge;
use Illuminate\Support\Facades\DB;

class OrderCancellationService
{
    /** The seller can cancel until the parcel is handed over. */
    public const SELLER_CANCELLABLE = ['placed', 'confirmed', 'preparing'];

    /** The buyer can cancel only until the seller starts preparing the order. */
    public const BUYER_CANCELLABLE = ['placed', 'confirmed'];

    public function cancel(Order $order, User $actor, string $role, ?string $reason = null): void
    {
        DB::transaction(function () use ($order, $actor, $role, $reason): void {
            $locked = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $ownerColumn = $role === 'buyer' ? 'buyer_id' : 'seller_id';
            abort_unless($locked->{$ownerColumn} === $actor->id, 403);
            if ($role === 'buyer' && $locked->status === 'preparing') {
                abort(409, 'The seller is already preparing this order, so it can no longer be cancelled. If it does not ship in time it is cancelled for you.');
            }
            abort_unless(in_array($locked->status, $role === 'buyer' ? self::BUYER_CANCELLABLE : self::SELLER_CANCELLABLE, true), 409, 'This order can no longer be cancelled.');

            $this->void($locked, $actor->id, $role, $reason);
        });
    }

    /**
     * The seller did not hand the parcel to the courier in time: cancelled for the buyer and
     * recorded against the seller (late_cancelled_at feeds seller performance).
     *
     * @return bool whether this call cancelled it (false: it moved on or was cancelled meanwhile)
     */
    public function cancelLate(Order $order): bool
    {
        return DB::transaction(function () use ($order): bool {
            $locked = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            if (! in_array($locked->status, ['preparing', 'processing'], true) || ! $locked->ship_by || $locked->ship_by->isFuture()) {
                return false;
            }
            $locked->forceFill(['late_cancelled_at' => now()])->save();
            $this->void($locked, null, 'system', 'The seller did not hand the order to the courier by '.$locked->ship_by->format('M d, Y h:i A').'.');
            $order->setRawAttributes($locked->getAttributes(), true);

            return true;
        });
    }

    private function void(Order $locked, ?int $actorId, string $role, ?string $reason): void
    {
        // The seller may already have printed the waybill; void it so it can never be picked up.
        app(PicksellBridge::class)->cancel($locked, $reason ?: 'Order cancelled before handover');

        app(OrderLifecycleService::class)->transition(
            $locked,
            'cancelled',
            $actorId,
            $role,
            $reason,
            ['tracking_status' => $role === 'system' ? 'Cancelled: the seller did not ship in time' : 'Order cancelled before pickup'],
        );
    }
}
