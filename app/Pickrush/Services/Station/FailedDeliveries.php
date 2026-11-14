<?php

namespace App\Pickrush\Services\Station;

use App\Pickrush\Bridge\PicksellBridge;
use App\Pickrush\Enums\ParcelStatus;
use App\Pickrush\Models\Parcel;
use App\Pickrush\Models\Staff;
use App\Pickrush\Services\OperationRefused;
use App\Pickrush\Services\ParcelLedger;

/**
 * After a failed attempt the dispatcher either gives the parcel another rider (dispatch queue)
 * or starts the return to the seller. Once the attempt limit is reached, return is the only way.
 *
 * A PickSell parcel the buyer did not receive goes back with its original shipping fee as the
 * return fee, which the seller pays the return rider on hand-back (PickSell takes no commission).
 * A seller who does not take it back has it held at the station; after
 * policies.return_abandon_days the station may mark it abandoned, and the company keeps it.
 */
class FailedDeliveries
{
    public function __construct(private ParcelLedger $ledger) {}

    public function returnToSender(Parcel $parcel, Staff $staff, string $reason): void
    {
        $returnable = [ParcelStatus::DeliveryFailed, ParcelStatus::ArrivedAtStation];
        if ($parcel->current_hub_id !== $staff->hub_id || ! in_array($parcel->current_status, $returnable, true) || $parcel->is_return) {
            throw new OperationRefused("{$parcel->tracking_no} cannot be sent back from here ({$parcel->current_status->label()}).");
        }
        if ($parcel->assignments()->where(fn ($q) => DispatchService::stillWithRider($q))->exists()) {
            throw new OperationRefused("{$parcel->tracking_no} is still with the rider who tried to deliver it. Scan it in at Receiving first.");
        }

        // Only after a real attempt: the buyer did not receive it, so the seller pays its way back.
        // Saved first, so the seller is told the fee with the return.
        if ($fee = self::returnFee($parcel)) {
            $parcel->forceFill(['return_fee' => $fee])->save();
        }
        $this->ledger->record($parcel, ParcelStatus::ReturningToSender, $staff->hub, ['note' => $reason]);
    }

    /** The fee the seller pays on hand-back: a PickSell parcel's shipping fee, once delivery was tried. */
    public static function returnFee(Parcel $parcel): ?string
    {
        return $parcel->source === PicksellBridge::SOURCE && $parcel->attempt_count > 0 && (float) $parcel->shipping_fee > 0
            ? (string) $parcel->shipping_fee
            : null;
    }

    /** Days since the seller first did not take the return back (null: never refused). */
    public static function daysHeld(Parcel $parcel): ?int
    {
        return $parcel->return_refused_at ? (int) $parcel->return_refused_at->copy()->startOfDay()->diffInDays(today()) : null;
    }

    /** The seller never took the return back: past the abandon period, the company keeps the parcel. */
    public function abandon(Parcel $parcel, Staff $staff, ?string $note = null): void
    {
        $days = (int) config('pickrush.policies.return_abandon_days');
        if ($parcel->current_hub_id !== $staff->hub_id || $parcel->current_status !== ParcelStatus::ReturningToSender || ! $parcel->return_refused_at) {
            throw new OperationRefused("{$parcel->tracking_no} is not a return held for its seller here.");
        }
        if ($parcel->assignments()->where(fn ($q) => DispatchService::stillWithRider($q))->exists()) {
            throw new OperationRefused("{$parcel->tracking_no} is still with the rider. Scan it in at Receiving first.");
        }
        if (self::daysHeld($parcel) < $days) {
            throw new OperationRefused("{$parcel->tracking_no} can be marked abandoned after {$days} days held (now ".self::daysHeld($parcel).').');
        }

        $this->ledger->record($parcel, ParcelStatus::Abandoned, $staff->hub,
            ['note' => trim("Not taken back by the seller in {$days} days; kept by the logistics company. {$note}")]);
    }
}
