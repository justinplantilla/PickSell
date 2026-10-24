<?php

namespace App\Pickrush\Http\Controllers\Api;

use App\Pickrush\Bridge\PicksellBridge;
use App\Pickrush\Enums\ParcelStatus;
use App\Pickrush\Enums\StaffRole;
use App\Pickrush\Models\Assignment;
use App\Pickrush\Models\RiderBatch;
use App\Pickrush\Models\Staff;
use Illuminate\Support\Collection;

/**
 * A batch as the rider app shows it.
 *
 * Pickup: one stop per seller (in the order they were assigned) with its shop pin and the waybills
 * to scan there. Delivery: one stop per recipient (buyer, or the seller for a return) with the
 * parcels, COD and proof, plus what to hand back at the station (cash, parcels not delivered).
 * Both end at the station, with its pin and who to call.
 */
final class RiderBatchView
{
    public static function of(RiderBatch $batch): array
    {
        $batch->load('station', 'assignments.parcel');   // fresh: syncing may have just changed the tasks
        $station = $batch->station;

        $base = [
            'id' => $batch->id,
            'batch_no' => $batch->batch_no,
            'type' => $batch->type,
            'status' => $batch->status,
            'offered_at' => $batch->offered_at?->toIso8601String(),
            'responded_at' => $batch->responded_at?->toIso8601String(),
            'arrival_radius_m' => (int) config('pickrush.arrival_radius_m', 150),
            'arrival_locked' => (bool) config('pickrush.enforce_arrival_radius'),   // refuse "Arrived" outside the radius
            'drop_off' => $station ? [
                'name' => $station->name,
                'address' => $station->address ?: collect([$station->municipality_name, $station->province_name])->filter()->join(', '),
                'lat' => $station->lat !== null ? (float) $station->lat : null,
                'lng' => $station->lng !== null ? (float) $station->lng : null,
                'arrived_at' => $batch->hub_arrived_at?->toIso8601String(),
                'manager' => self::manager($batch->station_hub_id),
            ] : null,
        ];

        return $base + ($batch->isDelivery() ? self::delivery($batch) : self::pickup($batch));
    }

    // Pickup ------------------------------------------------------------------------------------

    private static function pickup(RiderBatch $batch): array
    {
        // Parcels the rider was asked to collect here (taken back, declined or cancelled ones leave).
        $shown = $batch->assignments->filter(fn (Assignment $task) => in_array($task->status, ['assigned', 'completed'], true)
            || ($task->status === 'failed' && $task->type === 'pickup'))->sortBy('id')->values();
        $tasks = $shown->whereIn('status', ['assigned', 'completed']);
        $picked = $tasks->where('status', 'completed');
        $droppedOff = $picked->filter(fn (Assignment $task) => self::received($task));
        $orderNumbers = PicksellBridge::orderNumbers($shown->pluck('parcel'));

        return [
            // Progress bar in the app: assigned → pickup → dropped off.
            'stage' => match (true) {
                $picked->isNotEmpty() && $droppedOff->count() === $picked->count() && $tasks->where('status', 'assigned')->isEmpty() => 'dropped_off',
                $picked->isNotEmpty() => 'pickup',
                default => 'assigned',
            },
            'parcel_count' => $tasks->count(),
            'picked_count' => $picked->count(),
            'dropped_off_count' => $droppedOff->count(),
            'not_collected_count' => $shown->where('status', 'failed')->count(),
            'last_pickup_at' => $picked->max('completed_at')?->toIso8601String(),
            'est_earnings' => round($tasks->count() * self::rate('pickup'), 2),
            'stops' => $shown->groupBy(fn (Assignment $task) => $task->parcel->shipper_name.'|'.$task->parcel->pickup_address)
                ->values()
                ->map(function (Collection $tasks, int $i) use ($orderNumbers) {
                    $parcel = $tasks->first()->parcel;
                    $toPick = $tasks->where('status', 'assigned');

                    return [
                        'number' => $i + 1,
                        'kind' => 'pickup',
                        'name' => $parcel->shipper_name,
                        'mobile' => $parcel->shipper_mobile,
                        'seller_name' => $parcel->shipper_name,
                        'seller_mobile' => $parcel->shipper_mobile,
                        'address' => $parcel->pickup_address,
                        'lat' => $parcel->pickup_lat !== null ? (float) $parcel->pickup_lat : null,
                        'lng' => $parcel->pickup_lng !== null ? (float) $parcel->pickup_lng : null,
                        'status' => $toPick->isEmpty() ? 'picked_up' : 'pending',
                        'arrived_at' => $toPick->min('arrived_at')?->toIso8601String(),
                        'parcel_count' => $tasks->whereIn('status', ['assigned', 'completed'])->count(),
                        'picked_count' => $tasks->where('status', 'completed')->count(),
                        'not_collected_count' => $tasks->where('status', 'failed')->count(),
                        'items' => $tasks->map(fn (Assignment $task) => self::item($task, $orderNumbers, match (true) {
                            $task->status === 'failed' => 'not_collected',
                            self::received($task) => 'received',
                            $task->status === 'completed' => 'picked_up',
                            default => 'to_pick',
                        }))->values()->all(),
                    ];
                })->all(),
        ];
    }

    /** Collected and already scanned in at the station. */
    private static function received(Assignment $task): bool
    {
        return $task->status === 'completed' && $task->parcel->current_status !== ParcelStatus::PickedUp;
    }

    // Delivery ----------------------------------------------------------------------------------

    private static function delivery(RiderBatch $batch): array
    {
        $tasks = $batch->assignments->whereIn('status', ['assigned', 'out_for_delivery', 'completed', 'failed'])->sortBy('id')->values();
        $delivered = $tasks->where('status', 'completed');
        $failed = $tasks->where('status', 'failed');
        $open = $tasks->whereIn('status', ['assigned', 'out_for_delivery']);
        $collected = round((float) $delivered->sum('cod_collected'), 2);
        $toRemit = round((float) $delivered->whereNull('cod_remittance_id')->sum('cod_collected'), 2);
        $orderNumbers = PicksellBridge::orderNumbers($tasks->pluck('parcel'));

        return [
            // Progress bar in the app: assigned → pickup (loading at the station) → deliver → done.
            'stage' => match (true) {
                $batch->started_at && $open->isEmpty() => 'dropped_off',
                $delivered->isNotEmpty() || $failed->isNotEmpty() => 'deliver',
                $batch->started_at || $tasks->whereNotNull('loaded_at')->isNotEmpty() => 'pickup',
                default => 'assigned',
            },
            'started_at' => $batch->started_at?->toIso8601String(),
            'parcel_count' => $tasks->count(),
            'loaded_count' => $tasks->filter(fn (Assignment $task) => $task->loaded_at || $task->status !== 'assigned')->count(),
            'delivered_count' => $delivered->count(),
            'failed_count' => $failed->count(),
            'to_return_count' => $failed->whereNull('returned_at')->count(),
            'returned_count' => $failed->whereNotNull('returned_at')->count(),
            'cod_total' => round($tasks->where('type', 'delivery')->sum(fn (Assignment $task) => (float) $task->parcel->cod_amount), 2),
            'cod_collected' => $collected,
            'to_remit' => $toRemit,
            'remitted' => $collected > 0 && $toRemit <= 0,
            'last_delivery_at' => $tasks->whereIn('status', ['completed', 'failed'])->max('completed_at')?->toIso8601String(),
            'est_earnings' => round($tasks->count() * self::rate('delivery'), 2),
            'stops' => $tasks->groupBy(fn (Assignment $task) => self::recipient($task)['key'])
                ->values()
                ->map(function (Collection $tasks, int $i) use ($orderNumbers) {
                    $first = $tasks->first();
                    $to = self::recipient($first);
                    $statuses = $tasks->pluck('status')->unique()->values();

                    return [
                        'number' => $i + 1,
                        'kind' => $first->type,             // delivery, or return (to the seller)
                        'name' => $to['name'],
                        'mobile' => $to['mobile'],
                        'address' => $to['address'],
                        'lat' => $to['lat'],
                        'lng' => $to['lng'],
                        'status' => match (true) {
                            $statuses->intersect(['assigned', 'out_for_delivery'])->isNotEmpty() => 'pending',
                            $statuses->all() === ['completed'] => 'delivered',
                            $statuses->all() === ['failed'] => 'failed',
                            default => 'partial',
                        },
                        'arrived_at' => $tasks->whereIn('status', ['out_for_delivery'])->min('arrived_at')?->toIso8601String(),
                        'parcel_count' => $tasks->count(),
                        'cod_total' => round($tasks->where('type', 'delivery')->sum(fn (Assignment $task) => (float) $task->parcel->cod_amount), 2),
                        'earnings' => round($tasks->count() * self::rate('delivery'), 2),
                        'items' => $tasks->map(fn (Assignment $task) => self::item($task, $orderNumbers, match ($task->status) {
                            'assigned' => $task->loaded_at ? 'loaded' : 'to_load',
                            'out_for_delivery' => 'out_for_delivery',
                            'completed' => 'delivered',
                            default => $task->returned_at ? 'returned' : 'failed',
                        }))->values()->all(),
                    ];
                })->all(),
        ];
    }

    /** Who the parcel goes to: the buyer, or the seller when it is a return. */
    private static function recipient(Assignment $task): array
    {
        $p = $task->parcel;
        $toSeller = $task->type === 'return';

        return [
            'key' => $toSeller ? "r|{$p->shipper_name}|{$p->pickup_address}" : "d|{$p->consignee_name}|{$p->delivery_address}",
            'name' => $toSeller ? $p->shipper_name : $p->consignee_name,
            'mobile' => $toSeller ? $p->shipper_mobile : $p->consignee_mobile,
            'address' => $toSeller ? $p->pickup_address : $p->delivery_address,
            'lat' => $toSeller && $p->pickup_lat !== null ? (float) $p->pickup_lat : null,
            'lng' => $toSeller && $p->pickup_lng !== null ? (float) $p->pickup_lng : null,
        ];
    }

    // Shared ------------------------------------------------------------------------------------

    /** @param  array<int, string>  $orderNumbers */
    private static function item(Assignment $task, array $orderNumbers, string $status): array
    {
        $parcel = $task->parcel;

        return [
            'assignment_id' => $task->id,
            'kind' => $task->type,
            'tracking_no' => $parcel->tracking_no,
            'order_number' => $orderNumbers[$task->parcel_id] ?? null,
            'description' => $parcel->item_description,
            'weight_kg' => $parcel->weight_kg !== null ? (float) $parcel->weight_kg : null,
            'size' => $parcel->size_category,
            'cod_amount' => $task->type === 'delivery' ? (float) $parcel->cod_amount : 0.0,
            'status' => $status,
            'note' => in_array($task->status, ['failed'], true) ? $task->fail_note : null,
            'done_at' => $task->completed_at?->toIso8601String(),
        ];
    }

    private static function rate(string $type): float
    {
        return (float) config($type === 'pickup' ? 'pickrush.rider_pay.pickup_per_parcel' : 'pickrush.rider_pay.delivery_per_parcel', 0);
    }

    private static function manager(int $stationId): ?array
    {
        $manager = Staff::where('hub_id', $stationId)->where('role', StaffRole::HubManager)->where('is_active', true)
            ->where('is_sample', false)->orderBy('id')->first();

        return $manager ? ['name' => $manager->full_name, 'mobile' => $manager->mobile_no] : null;
    }
}
