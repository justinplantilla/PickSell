<?php

namespace App\Pickrush\Services\Station;

use App\Pickrush\Models\Assignment;
use App\Pickrush\Models\Parcel;
use App\Pickrush\Models\Rider;

/** Whether a rider can take one more parcel, and if not, why. */
class RiderAvailability
{
    /**
     * @param  bool  $carriesCod  false for a pickup: the parcel's size still counts, its COD does not
     * @return string|null the reason the rider cannot take it, or null when they can
     */
    public function blocker(Rider $rider, ?Parcel $parcel = null, bool $carriesCod = true): ?string
    {
        if ($parcel && ($vehicle = $this->vehicleBlocker($rider, $parcel))) {
            return $vehicle;
        }
        if (! $rider->isActive()) {
            return "{$rider->full_name} is not active ({$rider->status}).";
        }
        if ($expired = $rider->expiredDocument()) {
            return "{$rider->full_name}'s {$expired->label()} expired on {$expired->expiry_date->format('M d, Y')}. Approve a renewed one on their rider page first.";
        }
        if (! $rider->isSignedIn()) {
            return "{$rider->full_name} is logged out of the rider app.";
        }
        if (! $rider->isOnShift()) {
            return "{$rider->full_name} is off duty.";
        }
        if ($rider->hasOpenVariance()) {
            return "{$rider->full_name} has an unresolved cash variance; the hub manager must resolve it first.";
        }
        if ($rider->cashCountToConfirm()) {
            return "{$rider->full_name} has not confirmed the station's count of their cash in the rider app.";
        }
        if ($this->openCount($rider) >= $rider->max_parcels) {
            return "{$rider->full_name} is at the daily load cap ({$rider->max_parcels} parcels).";
        }
        if ($parcel && $carriesCod && ! $parcel->is_return) {
            $cod = $this->codInHand($rider) + $this->codAssigned($rider) + (float) $parcel->cod_amount;
            if ($cod > (float) $rider->cod_limit) {
                return "{$rider->full_name} would carry ₱".number_format($cod, 2).' in COD, above the ₱'.number_format((float) $rider->cod_limit, 2).' limit.';
            }
        }

        return null;
    }

    /** The rider's vehicle is too small for the parcel's class. */
    public function vehicleBlocker(Rider $rider, Parcel $parcel): ?string
    {
        if ($rider->canCarry($parcel->parcel_class)) {
            return null;
        }
        $fits = Rider::vehiclesFor($parcel->parcel_class);

        return "{$rider->full_name} rides a {$rider->vehicleName()}; {$parcel->parcel_class} parcels need a ".($fits ?: 'bigger vehicle').'.';
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
        return (float) Assignment::where('rider_id', $rider->id)->whereIn('type', ['delivery', 'return'])->where('status', 'completed')
            ->whereNull('cod_remittance_id')->sum('cod_collected');
    }
}
