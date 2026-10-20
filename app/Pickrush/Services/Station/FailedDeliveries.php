<?php

namespace App\Pickrush\Services\Station;

use App\Pickrush\Enums\ParcelStatus;
use App\Pickrush\Models\Parcel;
use App\Pickrush\Models\Staff;
use App\Pickrush\Services\OperationRefused;
use App\Pickrush\Services\ParcelLedger;

/**
 * After a failed attempt the dispatcher either gives the parcel another rider (dispatch queue)
 * or starts the return to the seller. Once the attempt limit is reached, return is the only way.
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

        $this->ledger->record($parcel, ParcelStatus::ReturningToSender, $staff->hub, ['note' => $reason]);
    }
}
