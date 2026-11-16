<?php

namespace App\Pickrush\Services\Station;

use App\Pickrush\Enums\ParcelStatus;
use App\Pickrush\Models\Assignment;
use App\Pickrush\Models\ParcelEvent;
use App\Pickrush\Services\OperationRefused;
use App\Pickrush\Services\ParcelLedger;
use App\Pickrush\Services\Rider\RiderEarnings;
use App\Pickrush\Support\Actor;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * What a rider does with a task: collect from the seller, leave the hub, deliver, or report a
 * failed attempt. The rider app calls these through the rider API; scans sent later from a phone
 * that was offline keep their own time and are recorded once.
 */
class RiderTaskService
{
    public function __construct(private ParcelLedger $ledger, private RiderAvailability $availability, private RiderEarnings $earnings) {}

    /** Seller hand-over to the rider (first mile). The parcel reaches the hub with the next station scan. */
    public function pickUp(Assignment $task, array $scan = []): void
    {
        if ($this->replayed($scan)) {
            return;
        }
        $this->expect($task, 'pickup', ['assigned']);
        DB::transaction(function () use ($task, $scan) {
            $this->ledger->record($task->parcel, ParcelStatus::PickedUp, $task->rider->station, $this->options($task, $scan));
            $task->update(['status' => 'completed', 'completed_at' => $scan['occurred_at'] ?? now()]);
            $this->earnings->credit($task, $task->completed_at);
        });
    }

    /** The rider scans the parcel out of the hub. */
    public function startDelivery(Assignment $task, array $scan = []): void
    {
        if ($this->replayed($scan)) {
            return;
        }
        $this->expect($task, ['delivery', 'return'], ['assigned']);
        DB::transaction(function () use ($task, $scan) {
            $this->ledger->record($task->parcel, ParcelStatus::OutForDelivery, $task->rider->station, $this->options($task, $scan));
            $task->update(['status' => 'out_for_delivery']);
        });
    }

    /**
     * Proof of delivery, or of the hand-back to the seller. The rider confirms the cash amount but
     * cannot change it (the buyer's COD, or the seller's return fee), and a hand-over that would push
     * the cash they carry over their COD limit is refused.
     */
    public function deliver(Assignment $task, float $codConfirmed, string $proofPhotoPath, array $scan = []): void
    {
        if ($this->replayed($scan)) {
            return;
        }
        $this->expect($task, ['delivery', 'return'], ['out_for_delivery']);
        $parcel = $task->parcel;
        $isReturn = $task->type === 'return';
        $cod = $isReturn ? (float) ($parcel->return_fee ?? 0) : (float) $parcel->cod_amount;

        if (abs($codConfirmed - $cod) > 0.009) {
            throw new OperationRefused(($isReturn ? 'The return fee' : 'The COD amount').' is ₱'.number_format($cod, 2).'; collect exactly that amount.');
        }
        if ($cod > 0 && $this->availability->codInHand($task->rider) + $cod > (float) $task->rider->cod_limit) {
            throw new OperationRefused('This '.($isReturn ? 'return' : 'delivery').' would put your cash on hand over your COD limit. Remit at the hub first.');
        }

        DB::transaction(function () use ($task, $proofPhotoPath, $scan, $isReturn, $cod) {
            $this->ledger->record($task->parcel, $isReturn ? ParcelStatus::ReturnedToSender : ParcelStatus::Delivered,
                $task->rider->station, $this->options($task, $scan) + ['meta' => ['proof_photo' => $proofPhotoPath]]);
            $task->update([
                'status' => 'completed',
                'proof_photo_path' => $proofPhotoPath,
                'cod_collected' => $cod,
                'completed_at' => $scan['occurred_at'] ?? now(),
            ]);
            $this->earnings->credit($task, $task->completed_at);
        });
    }

    /**
     * A failed attempt: a reason from the closed list, a note for "other", and a photo. Earns the
     * rider nothing. A return the seller did not take back goes back to the station, held for them
     * (the first refusal starts the hold).
     */
    public function fail(Assignment $task, string $reason, ?string $note, string $photoPath, array $scan = []): void
    {
        if ($this->replayed($scan)) {
            return;
        }
        $this->expect($task, ['delivery', 'return'], ['out_for_delivery']);
        $isReturn = $task->type === 'return';
        // A buyer return goes to the seller as a delivery; not taking it is a seller refusal, held like a return.
        $toSeller = $isReturn || $task->parcel->isBuyerReturn();
        $reasons = config($toSeller ? 'pickrush.return_failure_reasons' : 'pickrush.failure_reasons');
        if (! array_key_exists($reason, $reasons)) {
            throw new OperationRefused('Pick a reason from the list.');
        }
        if ($reason === 'other' && blank($note)) {
            throw new OperationRefused('Write a note when the reason is "Other".');
        }

        DB::transaction(function () use ($task, $reason, $reasons, $note, $photoPath, $scan, $isReturn, $toSeller) {
            $this->ledger->record($task->parcel, $isReturn ? ParcelStatus::ReturningToSender : ParcelStatus::DeliveryFailed, $task->rider->station, [
                'note' => ($toSeller ? 'Seller did not take the return back: ' : '').$reasons[$reason].($note ? " — {$note}" : ''),
                'meta' => ['reason' => $reason, 'photo' => $photoPath],
            ] + $this->options($task, $scan));
            if ($toSeller && ! $task->parcel->return_refused_at) {
                $task->parcel->forceFill(['return_refused_at' => $scan['occurred_at'] ?? now()])->save();
            }
            $task->update([
                'status' => 'failed', 'fail_reason' => $reason, 'fail_note' => $note,
                'proof_photo_path' => $photoPath, 'completed_at' => $scan['occurred_at'] ?? now(),
            ]);
        });
    }

    /** A scan the phone already sent (retry or offline replay): accept it again, change nothing. */
    private function replayed(array $scan): bool
    {
        return isset($scan['idempotency_key']) && ParcelEvent::where('idempotency_key', $scan['idempotency_key'])->exists();
    }

    private function expect(Assignment $task, string|array $types, array $statuses): void
    {
        if (! in_array($task->type, (array) $types, true) || ! in_array($task->status, $statuses, true)) {
            throw new OperationRefused("This {$task->type} task is {$task->status}; that step is not possible now.");
        }
    }

    /** @param array{occurred_at?: CarbonInterface, idempotency_key?: string} $scan */
    private function options(Assignment $task, array $scan): array
    {
        return [
            'rider_id' => $task->rider_id,
            'occurred_at' => $scan['occurred_at'] ?? null,
            'idempotency_key' => $scan['idempotency_key'] ?? null,
            'actor' => new Actor('rider', $task->rider_id, $task->rider->station_hub_id),
        ];
    }
}
