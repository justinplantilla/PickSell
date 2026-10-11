<?php

namespace App\Services;

use App\Models\Delivery;
use App\Models\DeliveryAssignment;
use App\Models\DeliveryLog;
use App\Models\Order;
use App\Services\Orders\DeliveryStatusTransitionPolicy;
use Illuminate\Support\Facades\DB;

class DeliveryDomainSynchronizer
{
    public function __construct(private DeliveryStatusTransitionPolicy $transitionPolicy) {}

    public function syncCreatedOrder(Order $order): void
    {
        DB::transaction(function () use ($order): void {
            $delivery = $this->syncDelivery($order);
            $this->writeLog(
                $delivery,
                $order->statusChangedBy ?? auth()->id(),
                $this->actorRole($order->statusChangeSource ?? auth()->user()?->role),
                null,
                $order->status,
            );
        });
    }

    public function syncOrderUpdate(Order $order, ?string $previousStatus, ?int $previousRiderId): void
    {
        DB::transaction(function () use ($order, $previousStatus, $previousRiderId): void {
            $previousDeliveryStatus = Delivery::query()
                ->where('order_id', $order->id)
                ->value('status');
            $delivery = $this->syncDelivery($order);

            if ($order->status === 'assigned_to_rider'
                && $order->courier_id
                && ($order->wasChanged('status') || $order->wasChanged('courier_id'))) {
                $this->offerToRider($delivery, $order, $previousRiderId);
            }

            if ($order->status === 'at_sorting_center'
                && in_array($previousStatus, ['assigned_to_rider', 'delivery_failed'], true)
                && $previousRiderId) {
                $offer = DeliveryAssignment::query()
                    ->where('delivery_id', $delivery->id)
                    ->where('rider_id', $previousRiderId)
                    ->whereIn('status', ['offered', 'accepted'])
                    ->latest('id')
                    ->first();
                if ($offer) {
                    $previousAssignmentStatus = $offer->status;
                    $offer->update(['status' => 'cancelled', 'responded_at' => now()]);
                    $this->logAssignmentEvent(
                        $offer,
                        $previousAssignmentStatus,
                        'cancelled',
                        $order->statusChangedBy ?? auth()->id(),
                        'Rider assignment returned to the sorting center.',
                        $order->statusChangeSource,
                    );
                }
            }

            if (! $order->wasChanged('status')) {
                return;
            }

            if ($order->status === 'out_for_delivery') {
                $assignment = $this->currentOffer($delivery, $order->courier_id);
                if ($assignment && $assignment->status === 'offered') {
                    $assignment->update(['status' => 'accepted', 'responded_at' => now()]);
                    $this->logAssignmentEvent(
                        $assignment,
                        'offered',
                        'accepted',
                        $order->statusChangedBy ?? auth()->id(),
                        actorRole: $order->statusChangeSource,
                    );
                }
            }

            $this->writeLog(
                $delivery,
                $order->statusChangedBy ?? auth()->id(),
                $this->actorRole($order->statusChangeSource ?? auth()->user()?->role),
                $previousDeliveryStatus ?? $previousStatus,
                $order->status,
                $order->statusChangeReason,
                $order->deliveryLogContext,
            );

            $order->deliveryLogContext = [];
        });
    }

    public function markReturnToSender(Order $order, int $actorId, string $reason): void
    {
        DB::transaction(function () use ($order, $actorId, $reason): void {
            $delivery = Delivery::query()->where('order_id', $order->id)->lockForUpdate()->firstOrFail();
            abort_unless(
                $order->status === 'delivery_failed'
                    && $this->transitionPolicy->canTransition(
                        $delivery->status,
                        'return_to_sender',
                        'courier',
                        (int) $delivery->delivery_attempts,
                    ),
                409,
            );

            $delivery->update(['status' => 'return_to_sender']);
            $this->writeLog(
                $delivery,
                $actorId,
                'rider',
                'delivery_failed',
                'return_to_sender',
                $reason,
            );

            $activeOffer = DeliveryAssignment::query()
                ->where('delivery_id', $delivery->id)
                ->where('rider_id', $order->courier_id)
                ->whereIn('status', ['offered', 'accepted'])
                ->latest('id')
                ->first();
            if ($activeOffer) {
                $previousAssignmentStatus = $activeOffer->status;
                $activeOffer->update(['status' => 'cancelled', 'responded_at' => now()]);
                $this->logAssignmentEvent(
                    $activeOffer,
                    $previousAssignmentStatus,
                    'cancelled',
                    $actorId,
                    'Delivery is returning to sender.',
                    'courier',
                );
            }

            $order->update(['courier_id' => null]);
        });
    }

    public function logAssignmentEvent(
        DeliveryAssignment $assignment,
        ?string $fromStatus,
        string $toStatus,
        ?int $actorId,
        ?string $note = null,
        ?string $actorRole = null,
    ): void {
        $this->writeLog(
            $assignment->delivery,
            $actorId,
            $this->actorRole($actorRole ?? auth()->user()?->role),
            $fromStatus,
            $toStatus,
            $note,
        );
    }

    private function syncDelivery(Order $order): Delivery
    {
        $delivery = Delivery::query()->firstOrNew(['order_id' => $order->id]);
        $deliveryStatus = $delivery->status === 'return_to_sender' && $order->status === 'delivery_failed'
            ? 'return_to_sender'
            : $order->status;
        $statusChanged = $delivery->exists && $delivery->status !== $deliveryStatus;
        $delivery->fill([
            'tracking_number' => $delivery->tracking_number ?: 'PS-'.str_pad((string) $order->id, 12, '0', STR_PAD_LEFT),
            'status' => $deliveryStatus,
            'origin_branch_id' => $order->origin_branch_id,
            'destination_branch_id' => $order->destination_branch_id,
            'destination_barangay_id' => $order->destination_barangay_id,
            'delivery_attempts' => ((int) $delivery->delivery_attempts) + ($statusChanged && $order->status === 'delivery_failed' ? 1 : 0),
            'picked_up_at' => $order->picked_up_at,
            'sorting_at' => $order->sorting_received_at,
            'out_for_delivery_at' => $order->out_for_delivery_at,
            'delivered_at' => $order->delivered_at,
            'returned_at' => $order->status === 'returned' ? ($delivery->returned_at ?? now()) : $delivery->returned_at,
        ]);
        $delivery->save();

        return $delivery;
    }

    private function offerToRider(Delivery $delivery, Order $order, ?int $previousRiderId): void
    {
        $current = $this->currentOffer($delivery, $order->courier_id);
        if ($current && $current->status === 'accepted') {
            return;
        }

        $activeOldOffer = DeliveryAssignment::query()
            ->where('delivery_id', $delivery->id)
            ->whereIn('status', ['offered', 'accepted'])
            ->where('rider_id', '!=', $order->courier_id)
            ->get();

        foreach ($activeOldOffer as $oldOffer) {
            $oldStatus = $oldOffer->status;
            $oldOffer->update(['status' => 'cancelled', 'responded_at' => now()]);
            $this->logAssignmentEvent(
                $oldOffer,
                $oldStatus,
                'cancelled',
                $order->statusChangedBy ?? auth()->id(),
                'Rider assignment changed.',
                $order->statusChangeSource,
            );
        }

        if ($current && $current->status === 'offered') {
            return;
        }

        $assignment = DeliveryAssignment::create([
            'delivery_id' => $delivery->id,
            'rider_id' => $order->courier_id,
            'status' => 'offered',
            'offered_at' => now(),
            'expires_at' => now()->addMinutes(30),
        ]);

        $this->logAssignmentEvent(
            $assignment,
            null,
            'offered',
            $order->statusChangedBy ?? auth()->id(),
            $previousRiderId && $previousRiderId !== $order->courier_id ? 'Rider re-offered after reassignment.' : null,
        );
    }

    private function currentOffer(Delivery $delivery, ?int $riderId): ?DeliveryAssignment
    {
        if (! $riderId) {
            return null;
        }

        return DeliveryAssignment::query()
            ->where('delivery_id', $delivery->id)
            ->where('rider_id', $riderId)
            ->latest('id')
            ->first();
    }

    private function writeLog(
        Delivery $delivery,
        ?int $actorId,
        string $actorRole,
        ?string $fromStatus,
        string $toStatus,
        ?string $note = null,
        array $metadata = [],
    ): void {
        DeliveryLog::create([
            'delivery_id' => $delivery->id,
            'actor_id' => $actorId,
            'actor_role' => $actorRole,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'note' => $note,
            'proof_image_url' => $metadata['proof_image_url'] ?? null,
            'latitude' => $metadata['latitude'] ?? null,
            'longitude' => $metadata['longitude'] ?? null,
        ]);
    }

    private function actorRole(?string $role): string
    {
        $role = $role === 'courier' ? 'rider' : $role;

        return in_array($role, ['buyer', 'seller', 'logistics', 'rider', 'admin', 'system'], true)
            ? $role
            : 'system';
    }
}
