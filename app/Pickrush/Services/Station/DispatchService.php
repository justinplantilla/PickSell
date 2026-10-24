<?php

namespace App\Pickrush\Services\Station;

use App\Pickrush\Enums\ParcelStatus;
use App\Pickrush\Models\Assignment;
use App\Pickrush\Models\Hub;
use App\Pickrush\Models\Parcel;
use App\Pickrush\Models\Rider;
use App\Pickrush\Models\Staff;
use App\Pickrush\Services\OperationRefused;
use App\Pickrush\Services\ParcelLedger;
use App\Pickrush\Services\Rider\RiderBatches;
use App\Pickrush\Support\Actor;
use Illuminate\Support\Facades\DB;

/**
 * Gives parcels to riders. Pickups (first mile) only create the task; deliveries and returns to
 * the seller also move the parcel to "assigned to rider". Every rule is checked again here, so
 * the dispatch screen is never the only control.
 */
class DispatchService
{
    public function __construct(private ParcelLedger $ledger, private RiderAvailability $availability, private RiderBatches $batches) {}

    /** What kind of rider task this parcel needs at this station right now, or null if none. */
    public static function taskFor(Parcel $parcel, Hub $station): ?string
    {
        $status = $parcel->current_status;
        if ($status === ParcelStatus::WaitingPickup && $parcel->origin_hub_id === $station->id) {
            return 'pickup';
        }
        if ($parcel->current_hub_id !== $station->id) {
            return null;
        }
        $deliverHere = $parcel->is_return ? $parcel->origin_hub_id === $station->id : $parcel->dest_hub_id === $station->id;
        if (! $deliverHere) {
            return null;
        }
        $ready = in_array($status, [ParcelStatus::ArrivedAtStation, ParcelStatus::AtOriginHub, ParcelStatus::DeliveryFailed, ParcelStatus::ReturningToSender], true);

        return $ready ? ($parcel->is_return ? 'return' : 'delivery') : null;
    }

    /**
     * Assignments of a parcel the rider could not deliver and has not handed back yet (it is still
     * on their vehicle until it is scanned in at Receiving).
     */
    public static function stillWithRider($query)
    {
        return $query->where('pr_assignments.type', 'delivery')->where('pr_assignments.status', 'failed')
            ->whereNotNull('pr_assignments.batch_id')->whereNull('pr_assignments.returned_at');
    }

    public function assign(Parcel $parcel, Rider $rider, Staff $staff): Assignment
    {
        return DB::transaction(function () use ($parcel, $rider, $staff) {
            $parcel = Parcel::withoutGlobalScopes()->whereKey($parcel->id)->lockForUpdate()->firstOrFail();
            $station = $staff->hub;
            $type = self::taskFor($parcel, $station)
                ?? throw new OperationRefused("{$parcel->tracking_no} is not waiting for a rider at {$station->name} ({$parcel->current_status->label()}).");

            if ($rider->station_hub_id !== $station->id) {
                throw new OperationRefused("{$rider->full_name} belongs to another station.");
            }
            if ($parcel->openAssignment()->exists()) {
                throw new OperationRefused("{$parcel->tracking_no} already has a rider. Reassign it instead.");
            }
            if ($parcel->assignments()->where(fn ($q) => self::stillWithRider($q))->exists()) {
                throw new OperationRefused("{$parcel->tracking_no} is still with the rider who tried to deliver it. Scan it in at Receiving when they return.");
            }
            if ($type === 'delivery' && $parcel->attempt_count >= config('pickrush.policies.max_delivery_attempts')) {
                throw new OperationRefused("{$parcel->tracking_no} has reached the delivery attempt limit; return it to the seller.");
            }
            if ($blocker = $this->availability->blocker($rider, $type === 'delivery' ? $parcel : null)) {
                throw new OperationRefused($blocker);
            }

            $assignment = Assignment::create([
                'parcel_id' => $parcel->id,
                'rider_id' => $rider->id,
                'type' => $type,
                'assigned_by' => $staff->id,
                'assigned_at' => now(),
                'status' => 'assigned',
                'attempt_no' => $type === 'delivery' ? $parcel->attempt_count + 1 : 1,
            ]);

            if ($type !== 'pickup') {
                $this->ledger->record($parcel, ParcelStatus::AssignedToRider, $station, [
                    'rider_id' => $rider->id,
                    'note' => "Assigned to {$rider->full_name}",
                    'actor' => Actor::current(),
                ]);
            }

            return $assignment;
        });
    }

    /**
     * Take a parcel back from a rider who has not left with it yet.
     * Returns the parcel to the station queue; history keeps the old task as "reassigned".
     */
    public function unassign(Assignment $assignment, Staff $staff, string $reason): void
    {
        DB::transaction(function () use ($assignment, $staff, $reason) {
            $assignment = Assignment::whereKey($assignment->id)->lockForUpdate()->firstOrFail();
            if ($assignment->status !== 'assigned') {
                throw new OperationRefused('Only a task the rider has not started can be taken back.');
            }
            if ($assignment->rider->station_hub_id !== $staff->hub_id) {
                throw new OperationRefused('This task belongs to another station.');
            }
            $assignment->update(['status' => 'reassigned', 'fail_note' => $reason]);

            if ($assignment->type !== 'pickup') {
                $this->ledger->record($assignment->parcel, ParcelStatus::ArrivedAtStation, $staff->hub, ['note' => "Unassigned: {$reason}"]);
            }
            $this->batches->taskTakenBack($assignment, $reason);
        });
    }
}
