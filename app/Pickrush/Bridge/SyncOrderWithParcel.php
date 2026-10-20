<?php

namespace App\Pickrush\Bridge;

use App\Models\Order;
use App\Notifications\BuyerOrderUpdate;
use App\Pickrush\Enums\ParcelStatus;
use App\Pickrush\Models\Parcel;
use App\Pickrush\Models\ParcelEvent;
use App\Pickrush\Services\ParcelStatusChanged;
use App\Services\AdminNotificationService;
use App\Services\Orders\OrderLifecycleService;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Keeps the Picksell order in step with its parcel. Every scan refreshes the order's tracking
 * note (so the buyer sees which hub it is at); the scans that matter to the marketplace also move
 * the order status, through the marketplace's own lifecycle rules, recorded with source
 * "pickrush". A sync problem never blocks the scan: it is reported and flagged to admins.
 */
class SyncOrderWithParcel
{
    /** Pickrush status => marketplace order status (forward journey only). */
    private const ORDER_STATUS = [
        'picked_up' => 'picked_up',
        'at_origin_hub' => 'at_sorting_center',
        'arrived_at_station' => 'sorted',
        'assigned_to_rider' => 'assigned_to_rider',
        'out_for_delivery' => 'out_for_delivery',
        'delivered' => 'delivered',
        'delivery_failed' => 'delivery_failed',
        'returned_to_sender' => 'returned',
        'cancelled' => 'cancelled',
    ];

    /**
     * Order steps Pickrush may legitimately skip on the way to a later one: a walk-in parcel is
     * never "picked up"; a same-city parcel never leaves its station, so it is never "sorted".
     */
    private const SKIPPABLE = ['picked_up', 'at_sorting_center', 'sorted'];

    /** Order statuses the buyer hears about. */
    private const BUYER_MESSAGES = [
        'picked_up' => 'Your parcel was picked up from the seller.',
        'sorted' => 'Your parcel has arrived at the station in your city.',
        'out_for_delivery' => 'Your parcel is out for delivery today.',
        'delivered' => 'Your parcel was delivered.',
        'delivery_failed' => 'We could not deliver your parcel. Pickrush will contact you to try again.',
        'returned' => 'Your parcel was returned to the seller.',
    ];

    public function __construct(private OrderLifecycleService $lifecycle) {}

    public function handle(ParcelStatusChanged $changed): void
    {
        $parcel = $changed->parcel;
        if ($parcel->source !== PicksellBridge::SOURCE) {
            return;
        }

        try {
            $this->sync($parcel, $changed->event->loadMissing('hub', 'toHub'));
        } catch (Throwable $e) {
            report($e);
            $this->flag($parcel, "Order could not follow parcel scan \"{$changed->event->status->label()}\": {$e->getMessage()}");
        }
    }

    private function sync(Parcel $parcel, ParcelEvent $event): void
    {
        $moved = DB::transaction(function () use ($parcel, $event) {
            $order = Order::whereKey((int) $parcel->external_ref)->lockForUpdate()->first();
            if (! $order) {
                return null;
            }

            $target = $this->targetStatus($parcel, $event);
            $note = $event->headline();
            if (! $target || $target === $order->status) {
                Order::whereKey($order->id)->update(['tracking_status' => $note]);

                return null;
            }

            $path = $this->path($order->status, $target);
            if ($path === null) {
                Order::whereKey($order->id)->update(['tracking_status' => $note]);
                $this->flag($parcel, "Order {$order->order_number} is ".OrderLifecycleService::label($order->status)
                    ." and cannot follow Pickrush to ".OrderLifecycleService::label($target).'.');

                return null;
            }

            foreach ($path as $step) {
                $this->lifecycle->transition($order, $step, null, 'pickrush', $event->note, [
                    'tracking_status' => $step === $target ? $note : OrderLifecycleService::label($step),
                ]);
            }

            return $order;
        });

        if ($moved && $message = self::BUYER_MESSAGES[$moved->status] ?? null) {
            $moved->buyer?->notify(new BuyerOrderUpdate($moved, $message." Tracking no. {$parcel->tracking_no}."));
        }
    }

    private function targetStatus(Parcel $parcel, ParcelEvent $event): ?string
    {
        // On the way back to the seller only the final hand-over changes the order.
        if ($parcel->is_return) {
            return $event->status === ParcelStatus::ReturnedToSender ? 'returned' : null;
        }
        // Arriving at a station only counts once it is the buyer's station.
        if ($event->status === ParcelStatus::ArrivedAtStation && $event->hub_id !== $parcel->dest_hub_id) {
            return null;
        }

        return self::ORDER_STATUS[$event->status->value] ?? null;
    }

    /** The steps from $from to $to: direct, or through statuses Pickrush may skip. */
    private function path(string $from, string $to): ?array
    {
        if (OrderLifecycleService::canTransition($from, $to)) {
            return [$to];
        }
        $queue = [[$from, []]];
        $seen = [$from => true];
        while ($queue) {
            [$status, $steps] = array_shift($queue);
            foreach (OrderLifecycleService::allowedNext($status) as $next) {
                if ($next === $to) {
                    return [...$steps, $to];
                }
                if (! isset($seen[$next]) && in_array($next, self::SKIPPABLE, true)) {
                    $seen[$next] = true;
                    $queue[] = [$next, [...$steps, $next]];
                }
            }
        }

        return null;
    }

    private function flag(Parcel $parcel, string $message): void
    {
        $order = PicksellBridge::orderFor($parcel);
        app(AdminNotificationService::class)->notifyAdmins(
            'pickrush.sync_failed',
            'Order out of step with Pickrush',
            $message,
            $order ? route('admin.orders.show', ['order' => $order], false) : route('admin.orders', [], false),
            priority: 'critical',
        );
    }
}
