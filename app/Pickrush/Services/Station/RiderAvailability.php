<?php

namespace App\Pickrush\Services\Station;

use App\Pickrush\Models\Assignment;
use App\Pickrush\Models\Parcel;
use App\Pickrush\Models\Rider;

/** Whether a rider can take one more parcel, and if not, why. */
class RiderAvailability
{
    /** @return string|null the reason the rider cannot take it, or null when they can */
    public function blocker(Rider $rider, ?Parcel $parcel = null): ?string
    {
        if (! $rider->isActive()) {
            return "{$rider->full_name} is not active ({$rider->status}).";
        }
        if (! $rider->isOnShift()) {
            return "{$rider->full_name} is off duty.";
        }
        if ($rider->hasOpenVariance()) {
            return "{$rider->full_name} has an unresolved cash variance; the hub manager must resolve it first.";
        }
        if ($this->openCount($rider) >= $rider->max_parcels) {
            return "{$rider->full_name} is at the daily load cap ({$rider->max_parcels} parcels).";
        }
        if ($parcel && ! $parcel->is_return) {
            $cod = $this->codInHand($rider) + $this->codAssigned($rider) + (float) $parcel->cod_amount;
            if ($cod > (float) $rider->cod_limit) {
                return "{$rider->full_name} would carry ₱".number_format($cod, 2).' in COD, above the ₱'.number_format((float) $rider->cod_limit, 2).' limit.';
            }
        }

        return null;
    }

    public function openCount(Rider $rider): int
    {
        return Assignment::where('rider_id', $rider->id)->whereIn('status', Assignment::OPEN)->count();
    }

    /** COD of parcels the rider is carrying but has not delivered yet. */
    public function codAssigned(Rider $rider): float
    {
        return (float) Assignment::where('pr_assignments.rider_id', $rider->id)
            ->whereIn('pr_assignments.status', Assignment::OPEN)->where('pr_assignments.type', 'delivery')
            ->join('pr_parcels', 'pr_parcels.id', '=', 'pr_assignments.parcel_id')
            ->sum('pr_parcels.cod_amount');
    }

    /** Cash collected and not yet handed over to the station. */
    public function codInHand(Rider $rider): float
    {
        return (float) Assignment::where('rider_id', $rider->id)->where('type', 'delivery')->where('status', 'completed')
            ->whereNull('cod_remittance_id')->sum('cod_collected');
    }
}
