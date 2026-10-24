<?php

namespace App\Pickrush\Services\Rider;

use App\Pickrush\Enums\ParcelStatus;
use App\Pickrush\Models\Assignment;
use App\Pickrush\Models\Rider;
use App\Pickrush\Models\RiderBatch;
use App\Pickrush\Services\ManifestService;
use App\Pickrush\Services\OperationRefused;
use App\Pickrush\Services\ParcelLedger;
use App\Pickrush\Services\Station\RiderTaskService;
use App\Pickrush\Support\Actor;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * A delivery batch on the road. At the station the rider scans every parcel onto the vehicle
 * (load), then starts the route, which moves each parcel to "out for delivery". At each stop the
 * rider scans the parcel and either hands it over (COD confirmed, proof photo) or reports a failed
 * attempt (reason, photo). Back at the station they hand in the cash and the parcels not delivered.
 */
class DeliveryRoutes
{
    public function __construct(private RiderBatches $batches, private RiderTaskService $tasks, private ParcelLedger $ledger) {}

    /** Scanning a parcel onto the vehicle at the station. Scanning it again changes nothing. */
    public function load(RiderBatch $batch, Rider $rider, string $trackingNo): Assignment
    {
        $batch = $this->ready($batch, $rider);
        if ($batch->started_at) {
            throw new OperationRefused('The route has started; loading is closed.');
        }
        $task = $this->task($batch, $trackingNo, ['assigned']);
        if (! $task->loaded_at) {
            $task->update(['loaded_at' => now()]);
        }

        return $task;
    }

    /**
     * Leave the station. Every loaded parcel goes out for delivery (the buyer is told). A parcel
     * not scanned onto the vehicle needs a reason, and goes back to the station's queue.
     */
    public function startRoute(RiderBatch $batch, Rider $rider, ?string $leaveBehindReason = null, ?string $requestKey = null): RiderBatch
    {
        $batch = $this->ready($batch, $rider);
        if ($batch->started_at) {
            return $batch;     // a retried request
        }
        $tasks = $batch->assignments()->with('parcel', 'rider.station')->where('status', 'assigned')->get();
        $loaded = $tasks->whereNotNull('loaded_at');
        $missing = $tasks->whereNull('loaded_at');
        if ($loaded->isEmpty()) {
            throw new OperationRefused('Scan the parcels onto your vehicle first.');
        }
        if ($missing->isNotEmpty() && blank($leaveBehindReason)) {
            throw new OperationRefused("{$missing->count()} ".str('parcel')->plural($missing->count()).' not scanned yet. Scan them, or say why you are leaving them.');
        }

        DB::transaction(function () use ($batch, $loaded, $missing, $leaveBehindReason, $requestKey) {
            foreach ($missing as $task) {
                $note = "Left at the station by the rider: {$leaveBehindReason}";
                $task->update(['status' => 'reassigned', 'fail_reason' => 'not_loaded', 'fail_note' => $note]);
                $this->ledger->record($task->parcel, ParcelStatus::ArrivedAtStation, $batch->station, [
                    'note' => $note, 'actor' => new Actor('rider', $task->rider_id, $batch->station_hub_id),
                ]);
            }
            foreach ($loaded as $task) {
                $this->tasks->startDelivery($task, $requestKey ? ['idempotency_key' => "{$requestKey}:out:{$task->id}"] : []);
            }
            $batch->update(['started_at' => now()]);
        });

        return $this->batches->sync($batch->refresh());
    }

    /**
     * Handing a parcel over at the door. The rider scanned it (it must be this parcel), confirms
     * the COD amount (it cannot be changed) and takes a proof photo.
     */
    public function deliver(RiderBatch $batch, Rider $rider, int $assignmentId, string $scannedTrackingNo, float $codConfirmed, UploadedFile $photo, ?string $requestKey = null): RiderBatch
    {
        $batch = $this->onRoute($batch, $rider);
        $task = $this->stopTask($batch, $assignmentId, $scannedTrackingNo);
        if ($task->status === 'completed') {
            return $batch;     // a retried request
        }
        $cod = $task->type === 'return' ? 0.0 : (float) $task->parcel->cod_amount;
        if (abs($codConfirmed - $cod) > 0.009) {
            throw new OperationRefused('The COD amount is ₱'.number_format($cod, 2).'; collect exactly that amount.');
        }
        $path = $photo->store("pickrush/proof/{$batch->id}", 'local');     // private disk
        $this->tasks->deliver($task, $codConfirmed, $path, $requestKey ? ['idempotency_key' => "{$requestKey}:done:{$task->id}"] : []);

        return $this->batches->sync($batch->refresh());
    }

    /** A failed attempt: a reason from the closed list (a note for "other") and a photo. */
    public function fail(RiderBatch $batch, Rider $rider, int $assignmentId, string $reason, ?string $note, UploadedFile $photo, ?string $requestKey = null): RiderBatch
    {
        $batch = $this->onRoute($batch, $rider);
        $task = $this->stopTask($batch, $assignmentId, null);
        if ($task->status === 'failed') {
            return $batch;     // a retried request
        }
        if ($task->type !== 'delivery') {
            throw new OperationRefused('A return to the seller cannot be marked failed here. Call your station.');
        }
        $path = $photo->store("pickrush/proof/{$batch->id}", 'local');
        $this->tasks->fail($task, $reason, $note, $path, $requestKey ? ['idempotency_key' => "{$requestKey}:fail:{$task->id}"] : []);

        return $this->batches->sync($batch->refresh());
    }

    private function ready(RiderBatch $batch, Rider $rider): RiderBatch
    {
        $batch = $this->batches->own($batch, $rider);
        $this->batches->sync($batch);
        if (! $batch->isDelivery()) {
            throw new OperationRefused('This is a pickup batch.');
        }
        if ($batch->status !== 'accepted') {
            throw new OperationRefused($batch->status === 'offered' ? 'Accept the batch first.' : $this->batches->closedMessage($batch));
        }

        return $batch;
    }

    private function onRoute(RiderBatch $batch, Rider $rider): RiderBatch
    {
        $batch = $this->ready($batch, $rider);
        if (! $batch->started_at) {
            throw new OperationRefused('Load the parcels and start your route first.');
        }

        return $batch;
    }

    /** The batch's parcel with this waybill, in one of $statuses. */
    private function task(RiderBatch $batch, string $trackingNo, array $statuses): Assignment
    {
        $no = ManifestService::normalize($trackingNo);
        $task = $batch->assignments()->with('parcel', 'rider.station')
            ->whereHas('parcel', fn ($q) => $q->withoutGlobalScopes()->where('tracking_no', $no))
            ->whereIn('status', ['assigned', 'out_for_delivery', 'completed', 'failed'])->first();
        if (! $task) {
            throw new OperationRefused("{$no} is not part of this batch. Check the waybill.");
        }
        if (! in_array($task->status, $statuses, true)) {
            throw new OperationRefused("{$no} is already {$this->label($task)}.");
        }

        return $task;
    }

    /** The stop's parcel being handled; when a scan is given, it must be that parcel's waybill. */
    private function stopTask(RiderBatch $batch, int $assignmentId, ?string $scannedTrackingNo): Assignment
    {
        $task = $batch->assignments()->with('parcel', 'rider.station')->whereKey($assignmentId)->first()
            ?? throw new OperationRefused('This parcel is not part of your batch any more. Refresh and try again.');
        if ($scannedTrackingNo !== null && ManifestService::normalize($scannedTrackingNo) !== $task->parcel->tracking_no) {
            throw new OperationRefused('Wrong parcel: you scanned '.ManifestService::normalize($scannedTrackingNo).", this is {$task->parcel->tracking_no}.");
        }
        if (! in_array($task->status, ['out_for_delivery', 'completed', 'failed'], true)) {
            throw new OperationRefused("{$task->parcel->tracking_no} is {$this->label($task)}.");
        }

        return $task;
    }

    private function label(Assignment $task): string
    {
        return match ($task->status) {
            'assigned' => $task->loaded_at ? 'loaded' : 'not loaded yet',
            'out_for_delivery' => 'out for delivery',
            'completed' => 'delivered',
            'failed' => 'marked as a failed delivery',
            default => $task->status,
        };
    }
}
