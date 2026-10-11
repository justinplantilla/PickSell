<?php

namespace App\Services;

use App\Models\DeliveryAssignment;
use App\Models\Delivery;
use App\Models\Order;
use App\Services\Orders\OrderLifecycleService;
use Illuminate\Support\Facades\DB;

class DeliveryOfferExpiryService
{
    public function __construct(
        private LogisticsRoutingService $routing,
        private OrderLifecycleService $lifecycle,
        private DeliveryDomainSynchronizer $synchronizer,
    ) {}

    public function expireDue(): int
    {
        $expiredCount = 0;
        $ids = DeliveryAssignment::query()
            ->where('status', 'offered')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->orderBy('id')
            ->pluck('id');

        foreach ($ids as $id) {
            $expired = DB::transaction(function () use ($id): bool {
                $offer = DeliveryAssignment::query()->lockForUpdate()->find($id);
                if (! $offer || $offer->status !== 'offered' || ! $offer->expires_at || $offer->expires_at->isFuture()) {
                    return false;
                }

                $delivery = $offer->delivery;
                $order = Order::query()->whereKey($delivery->order_id)->lockForUpdate()->firstOrFail();
                $offer->update(['status' => 'expired', 'responded_at' => now()]);
                $this->synchronizer->logAssignmentEvent(
                    $offer,
                    'offered',
                    'expired',
                    null,
                    'Rider offer expired without a response.',
                    'system',
                );

                if ($order->status === 'assigned_to_rider' && $order->courier_id === $offer->rider_id) {
                    $this->reofferAfterExpiry($offer, $delivery, $order);
                }

                return true;
            });

            if ($expired) {
                $expiredCount++;
            }
        }

        return $expiredCount;
    }

    private function reofferAfterExpiry(DeliveryAssignment $offer, Delivery $delivery, Order $order): void
    {
        $excludedRiders = DeliveryAssignment::query()
            ->where('delivery_id', $delivery->id)
            ->pluck('rider_id')
            ->map(fn ($id) => (int) $id)
            ->all();
        $nextRider = $this->routing->suggestedCourier($order, $excludedRiders);

        if ($nextRider) {
            $order->update(['courier_id' => $nextRider->id]);

            return;
        }

        $this->lifecycle->transition(
            $order,
            'at_sorting_center',
            null,
            'system',
            'Rider offer expired and no eligible rider remained.',
        );
    }
}
