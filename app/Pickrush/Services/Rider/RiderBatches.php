<?php

namespace App\Pickrush\Services\Rider;

use App\Pickrush\Enums\ParcelStatus;
use App\Pickrush\Models\Assignment;
use App\Pickrush\Models\Rider;
use App\Pickrush\Models\RiderBatch;
use App\Pickrush\Models\RiderNotification;
use App\Pickrush\Models\Staff;
use App\Pickrush\Services\ManifestService;
use App\Pickrush\Services\OperationRefused;
use App\Pickrush\Services\ParcelLedger;
use App\Pickrush\Services\Station\RiderTaskService;
use App\Pickrush\Support\Geo;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Rider batches: the station offers a rider the tasks it assigned in one go, the rider is notified
 * in the app and accepts or declines the whole batch.
 *
 * - Pickup batch: stop by stop (arrive, scan each waybill, confirm), then bring the parcels to the
 *   station. A declined batch, and any parcel the rider could not collect, goes back to the
 *   station's pickup queue.
 * - Delivery batch (deliveries and returns to sellers): load at the station, deliver stop by stop
 *   (DeliveryRoutes), then return to the station with the cash and any parcel not delivered.
 */
class RiderBatches
{
    public function __construct(private RiderTaskService $tasks, private ParcelLedger $ledger) {}

    /** @param  Collection<int, Assignment>  $assignments  tasks just given to $rider (all pickups, or deliveries/returns) */
    public function offer(Rider $rider, Collection $assignments, Staff $staff): ?RiderBatch
    {
        if ($assignments->isEmpty()) {
            return null;
        }
        $type = $assignments->first()->type === 'pickup' ? 'pickup' : 'delivery';

        $batch = DB::transaction(function () use ($rider, $assignments, $staff, $type) {
            $batch = RiderBatch::create([
                'type' => $type,
                'rider_id' => $rider->id,
                'station_hub_id' => $rider->station_hub_id,
                'status' => 'offered',
                'assigned_by' => $staff->id,
                'offered_at' => now(),
            ]);
            Assignment::whereKey($assignments->pluck('id'))->update(['batch_id' => $batch->id]);

            return $batch->refresh();
        });

        $parcels = $assignments->count();
        $plural = fn (string $word, int $n) => "{$n} ".str($word)->plural($n);
        if ($type === 'pickup') {
            $sellers = $assignments->map(fn (Assignment $task) => $task->parcel->shipper_name)->unique()->values();
            RiderNotification::send($rider, 'pickup_offered', "New pickup batch #{$batch->batch_no}",
                $plural('stop', $sellers->count()).', '.$plural('parcel', $parcels).': '.$sellers->take(3)->join(', ').($sellers->count() > 3 ? '…' : ''),
                ['batch_id' => $batch->id]);
        } else {
            $cod = $assignments->where('type', 'delivery')->sum(fn (Assignment $task) => (float) $task->parcel->cod_amount);
            RiderNotification::send($rider, 'delivery_offered', "New delivery batch #{$batch->batch_no}",
                $plural('parcel', $parcels).' to deliver'.($cod > 0 ? ' · ₱'.number_format($cod, 2).' COD to collect' : '').'. Load them at the station.',
                ['batch_id' => $batch->id]);
        }

        return $batch;
    }

    public function accept(RiderBatch $batch, Rider $rider): RiderBatch
    {
        return DB::transaction(function () use ($batch, $rider) {
            $batch = $this->lockOwn($batch, $rider);
            $this->sync($batch);
            if ($batch->status !== 'offered') {
                throw new OperationRefused($this->closedMessage($batch));
            }
            $batch->update(['status' => 'accepted', 'responded_at' => now()]);

            return $batch;
        });
    }

    /** The rider turns the batch down; its tasks go back to the station's queue. */
    public function decline(RiderBatch $batch, Rider $rider, ?string $reason): RiderBatch
    {
        return DB::transaction(function () use ($batch, $rider, $reason) {
            $batch = $this->lockOwn($batch, $rider);
            if ($batch->status !== 'offered') {
                throw new OperationRefused($batch->status === 'accepted'
                    ? 'You already accepted this batch. Call your station if you cannot finish it.'
                    : $this->closedMessage($batch));
            }
            $note = 'Declined by rider'.($reason ? ": {$reason}" : '');
            foreach ($batch->assignments()->with('parcel')->where('status', 'assigned')->get() as $task) {
                $task->update(['status' => 'reassigned', 'fail_reason' => 'rider_declined', 'fail_note' => $note]);
                // A delivery moved the parcel to "assigned to rider"; it is back in the station's queue.
                if ($task->type !== 'pickup' && $task->parcel->current_status === ParcelStatus::AssignedToRider) {
                    $this->ledger->record($task->parcel, ParcelStatus::ArrivedAtStation, $batch->station, ['note' => $note]);
                }
            }
            $batch->update(['status' => 'declined', 'responded_at' => now(), 'decline_reason' => $reason]);

            return $batch;
        });
    }

    /**
     * The rider scanned these waybills at the seller ($trackingNos) and could not collect the
     * others they list ($notCollected, tracking no => reason). Scans of parcels already collected
     * are skipped (a retried request), and a parcel whose order was cancelled meanwhile leaves the
     * batch. Parcels not collected go back to the pickup queue with the reason.
     *
     * @param  list<string>  $trackingNos
     * @param  array<string, array{reason: string, note?: ?string}>  $notCollected
     * @return int how many parcels were collected now
     */
    public function pickUp(RiderBatch $batch, Rider $rider, array $trackingNos, array $notCollected = [], ?string $requestKey = null): int
    {
        $batch = $this->own($batch, $rider);
        $this->sync($batch);
        if ($batch->isDelivery()) {
            throw new OperationRefused('This is a delivery batch.');
        }
        if ($batch->status !== 'accepted') {
            throw new OperationRefused($batch->status === 'offered' ? 'Accept the batch before collecting parcels.' : $this->closedMessage($batch));
        }

        $scanned = array_values(array_unique(array_map(ManifestService::normalize(...), $trackingNos)));
        $missed = collect($notCollected)->mapWithKeys(fn ($why, $no) => [ManifestService::normalize((string) $no) => $why]);
        $tasks = $batch->assignments()->with('parcel', 'rider.station')->get()->keyBy(fn (Assignment $task) => $task->parcel->tracking_no);

        if ($unknown = array_diff([...$scanned, ...$missed->keys()], $tasks->keys()->all())) {
            throw new OperationRefused('Not part of this batch: '.implode(', ', $unknown).'. Check the waybill.');
        }
        if ($both = array_intersect($scanned, $missed->keys()->all())) {
            throw new OperationRefused(implode(', ', $both).' was scanned, so it cannot also be "not collected".');
        }
        $reasons = config('pickrush.pickup_failure_reasons');
        foreach ($missed as $no => $why) {
            if (! array_key_exists($why['reason'] ?? '', $reasons)) {
                throw new OperationRefused("Pick a reason why {$no} was not collected.");
            }
            if ($why['reason'] === 'other' && blank($why['note'] ?? null)) {
                throw new OperationRefused("Write a note why {$no} was not collected.");
            }
        }

        $done = 0;
        DB::transaction(function () use ($scanned, $missed, $tasks, $reasons, $requestKey, &$done) {
            foreach ($scanned as $no) {
                $task = $tasks[$no];
                if ($task->status === 'completed') {
                    continue;
                }
                if ($task->status !== 'assigned') {
                    throw new OperationRefused("{$no} is no longer yours to collect ({$task->status}).");
                }
                if ($task->parcel->current_status === ParcelStatus::Cancelled) {
                    $task->update(['status' => 'cancelled', 'fail_note' => 'Order cancelled']);

                    continue;
                }
                $this->tasks->pickUp($task, $requestKey ? ['idempotency_key' => "{$requestKey}:{$task->id}"] : []);
                $done++;
            }
            foreach ($missed as $no => $why) {
                $task = $tasks[$no];
                if ($task->status === 'assigned') {
                    $task->update([
                        'status' => 'failed',
                        'fail_reason' => $why['reason'],
                        'fail_note' => $reasons[$why['reason']].(filled($why['note'] ?? null) ? " — {$why['note']}" : ''),
                        'completed_at' => now(),
                    ]);
                }
            }
        });
        $this->sync($batch);

        return $done;
    }

    /**
     * "Arrived at stop": the rider is at this seller (pickup) or recipient (delivery). With a pin
     * (the seller's shop pin; buyers have none yet), the distance is recorded, and refused when the
     * arrival radius is enforced; without one, the spot is recorded for the station to check.
     *
     * @param  list<int>  $assignmentIds  the stop's parcels
     */
    public function arriveAtStop(RiderBatch $batch, Rider $rider, array $assignmentIds, float $lat, float $lng, ?float $accuracy = null): RiderBatch
    {
        $batch = $this->own($batch, $rider);
        $this->sync($batch);
        if ($batch->status !== 'accepted') {
            throw new OperationRefused($batch->status === 'offered' ? 'Accept the batch first.' : $this->closedMessage($batch));
        }
        $tasks = $batch->assignments()->with('parcel')->whereKey($assignmentIds)
            ->where('status', $batch->isDelivery() ? 'out_for_delivery' : 'assigned')->get();
        if ($tasks->isEmpty()) {
            throw new OperationRefused($batch->isDelivery() ? 'Nothing left to deliver at this stop.' : 'Nothing left to collect at this stop.');
        }
        $task = $tasks->first();
        $parcel = $task->parcel;
        // Collecting from a seller, or bringing a return back to them: the shop pin. Buyers: no pin yet.
        $toSeller = $task->type !== 'delivery';
        $distance = $toSeller
            ? $this->checkDistance($parcel->pickup_lat, $parcel->pickup_lng, $lat, $lng, $accuracy, $parcel->shipper_name)
            : null;

        Assignment::whereKey($tasks->pluck('id'))->whereNull('arrived_at')->update([
            'arrived_at' => now(), 'arrival_lat' => $lat, 'arrival_lng' => $lng, 'arrival_distance_m' => $distance,
        ]);

        return $batch;
    }

    /** "Arrived at hub": every stop is done and the rider is back at the station. */
    public function arriveAtHub(RiderBatch $batch, Rider $rider, float $lat, float $lng, ?float $accuracy = null): RiderBatch
    {
        $batch = $this->own($batch, $rider);
        $this->sync($batch);
        if (! in_array($batch->status, ['accepted', 'completed'], true)) {
            throw new OperationRefused($this->closedMessage($batch));
        }
        $open = $batch->isDelivery() ? ['assigned', 'out_for_delivery'] : ['assigned'];
        if ($batch->assignments()->whereIn('status', $open)->exists()) {
            throw new OperationRefused($batch->isDelivery()
                ? 'Finish every delivery stop before returning to the station.'
                : 'Finish every pickup stop before heading to the station.');
        }
        $station = $batch->station;
        $distance = $this->checkDistance($station->lat, $station->lng, $lat, $lng, $accuracy, $station->name);
        if (! $batch->hub_arrived_at) {
            $batch->update(['hub_arrived_at' => now(), 'hub_arrival_lat' => $lat, 'hub_arrival_lng' => $lng, 'hub_arrival_distance_m' => $distance]);
        }

        return $batch;
    }

    /**
     * How far the rider is from the pin, recorded with the arrival. Refused only when the
     * arrival radius is enforced (config pickrush.enforce_arrival_radius).
     *
     * @return int|null metres from the pin, or null when there is no pin
     */
    private function checkDistance(mixed $pinLat, mixed $pinLng, float $lat, float $lng, ?float $accuracy, string $place): ?int
    {
        if ($pinLat === null || $pinLng === null) {
            return null;
        }
        $metres = Geo::metres((float) $pinLat, (float) $pinLng, $lat, $lng);
        $radius = (int) config('pickrush.arrival_radius_m', 150);
        // A weak GPS fix gets a little slack, never more than 50 m.
        if (config('pickrush.enforce_arrival_radius') && $metres > $radius + min(max($accuracy ?? 0, 0), 50)) {
            throw new OperationRefused('You are about '.Geo::human($metres)." from {$place}. \"Arrived\" unlocks within {$radius} m.");
        }

        return (int) round($metres);
    }

    /** The station took one task of the batch back: tell the rider, and close the batch if it is empty. */
    public function taskTakenBack(Assignment $assignment, string $reason): void
    {
        $batch = $assignment->batch;
        if (! $batch || ! $batch->isOpen()) {
            return;
        }
        $assignment->loadMissing('parcel');
        $what = $batch->isDelivery() ? 'Delivery' : 'Pickup';
        $whose = $batch->isDelivery() ? $assignment->parcel->consignee_name : $assignment->parcel->shipper_name;
        RiderNotification::send($assignment->rider, 'task_removed', "{$what} removed from #{$batch->batch_no}",
            "{$assignment->parcel->tracking_no} ({$whose}): {$reason}", ['batch_id' => $batch->id]);
        $this->sync($batch);
    }

    /**
     * Brings the batch status up to date with its tasks. Empty → cancelled.
     * Pickup: everything collected and received at the station → completed; cancelled orders leave.
     * Delivery: every stop done, each parcel not delivered handed back at the station, and the COD
     * collected remitted at the station → completed.
     */
    public function sync(RiderBatch $batch): RiderBatch
    {
        if (! $batch->isOpen()) {
            return $batch;
        }
        $tasks = $batch->assignments()->with('parcel')->get();

        if ($batch->isDelivery()) {
            $live = $tasks->whereIn('status', ['assigned', 'out_for_delivery', 'completed', 'failed']);
            if ($live->isEmpty()) {
                $batch->update(['status' => 'cancelled', 'completed_at' => now()]);
            } elseif ($batch->status === 'accepted' && $batch->started_at
                && $live->whereIn('status', ['assigned', 'out_for_delivery'])->isEmpty()
                && $live->where('status', 'failed')->every(fn (Assignment $task) => $task->returned_at !== null)
                && $live->where('status', 'completed')->every(fn (Assignment $task) => (float) $task->cod_collected <= 0 || $task->cod_remittance_id !== null)) {
                $batch->update(['status' => 'completed', 'completed_at' => now()]);
            }

            return $batch;
        }

        foreach ($tasks as $task) {
            if ($task->status === 'assigned' && $task->parcel->current_status === ParcelStatus::Cancelled) {
                $task->update(['status' => 'cancelled', 'fail_note' => 'Order cancelled']);
            }
        }
        $live = $tasks->whereIn('status', ['assigned', 'completed']);
        if ($live->isEmpty()) {
            $batch->update(['status' => 'cancelled', 'completed_at' => now()]);
        } elseif ($batch->status === 'accepted' && $live->where('status', 'assigned')->isEmpty()
            && $live->every(fn (Assignment $task) => $task->parcel->current_status !== ParcelStatus::PickedUp)) {
            $batch->update(['status' => 'completed', 'completed_at' => now()]);
        }

        return $batch;
    }

    /** @return Collection<int, RiderBatch> the rider's open batches, offers first, statuses up to date */
    public function openFor(Rider $rider): Collection
    {
        return RiderBatch::where('rider_id', $rider->id)->whereIn('status', RiderBatch::OPEN)->oldest('offered_at')->get()
            ->map(fn (RiderBatch $batch) => $this->sync($batch))
            ->filter(fn (RiderBatch $batch) => $batch->isOpen())
            ->sortBy(fn (RiderBatch $batch) => $batch->status === 'offered' ? 0 : 1)
            ->values();
    }

    public function own(RiderBatch $batch, Rider $rider): RiderBatch
    {
        if ($batch->rider_id !== $rider->id) {
            throw new OperationRefused('This batch belongs to another rider.');
        }

        return $batch;
    }

    public function lockOwn(RiderBatch $batch, Rider $rider): RiderBatch
    {
        return $this->own(RiderBatch::whereKey($batch->id)->lockForUpdate()->firstOrFail(), $rider);
    }

    public function closedMessage(RiderBatch $batch): string
    {
        return match ($batch->status) {
            'declined' => 'You declined this batch.',
            'completed' => 'This batch is already finished.',
            'cancelled' => 'The station took this batch back.',
            default => 'This batch cannot be changed now.',
        };
    }
}
