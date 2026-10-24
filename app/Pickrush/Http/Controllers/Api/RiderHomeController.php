<?php

namespace App\Pickrush\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Pickrush\Enums\ParcelStatus;
use App\Pickrush\Models\Assignment;
use App\Pickrush\Models\ParcelEvent;
use App\Pickrush\Models\Rider;
use App\Pickrush\Models\RiderBatch;
use App\Pickrush\Models\RiderNotification;
use App\Pickrush\Services\Rider\RiderBatches;
use App\Pickrush\Support\Actor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/** The rider app's home: today's numbers, open batches, notifications, and going on or off shift. */
class RiderHomeController extends Controller
{
    public function dashboard(RiderBatches $batches): JsonResponse
    {
        $rider = Actor::rider();
        $rider->loadMissing('station');
        $today = Assignment::where('rider_id', $rider->id)->where('status', 'completed')->where('completed_at', '>=', today());
        $pickups = (clone $today)->where('type', 'pickup')->count();
        $delivered = (clone $today)->whereIn('type', ['delivery', 'return'])->count();
        $pay = config('pickrush.rider_pay');

        return response()->json([
            'shift_status' => $rider->shift_status,
            'hub' => $rider->station ? RiderProfile::hub($rider->station) : null,
            'today' => [
                'earnings' => round($pickups * (float) ($pay['pickup_per_parcel'] ?? 0) + $delivered * (float) ($pay['delivery_per_parcel'] ?? 0), 2),
                'pickups' => $pickups,
                'delivered' => $delivered,
            ],
            'batches' => $batches->openFor($rider)->map(fn ($batch) => RiderBatchView::of($batch))->values(),
            'unread_notifications' => RiderNotification::where('rider_id', $rider->id)->whereNull('read_at')->count(),
            'activity' => $this->activity($rider),
        ]);
    }

    /** Cheap check the app polls while open: unread count, the newest notification, open offers. */
    public function pulse(): JsonResponse
    {
        $rider = Actor::rider();
        $latest = RiderNotification::where('rider_id', $rider->id)->latest('id')->first();

        return response()->json([
            'unread_notifications' => RiderNotification::where('rider_id', $rider->id)->whereNull('read_at')->count(),
            'latest_notification' => $latest ? self::notification($latest) : null,
            'offered_batch_ids' => RiderBatch::where('rider_id', $rider->id)->where('status', 'offered')->pluck('id'),
            'offered_delivery_ids' => RiderBatch::where('rider_id', $rider->id)->where('status', 'offered')->where('type', 'delivery')->pluck('id'),
        ]);
    }

    public function notifications(): JsonResponse
    {
        $items = RiderNotification::where('rider_id', Actor::rider()->id)->latest('id')->limit(50)->get();

        return response()->json(['notifications' => $items->map(fn ($n) => self::notification($n))->values()]);
    }

    /** Mark the given notifications read, or all of them when no ids are sent. */
    public function readNotifications(Request $request): JsonResponse
    {
        $data = $request->validate(['ids' => 'nullable|array', 'ids.*' => 'integer']);
        RiderNotification::where('rider_id', Actor::rider()->id)->whereNull('read_at')
            ->when($data['ids'] ?? null, fn ($q, $ids) => $q->whereKey($ids))
            ->update(['read_at' => now()]);

        return response()->json(['message' => 'Marked as read.']);
    }

    /** Closed lists the app shows (reasons a parcel was not collected, problems to report). */
    public function options(): JsonResponse
    {
        $list = fn (array $items) => collect($items)->map(fn ($label, $key) => ['key' => $key, 'label' => $label])->values();

        return response()->json([
            'pickup_failure_reasons' => $list(config('pickrush.pickup_failure_reasons')),
            'rider_problems' => $list(config('pickrush.rider_problems')),
            'delivery_failure_reasons' => $list(config('pickrush.failure_reasons')),
            'arrival_radius_m' => (int) config('pickrush.arrival_radius_m', 150),
        ]);
    }

    /** Going online / offline. Only a rider on shift can be given new tasks. */
    public function shift(Request $request): JsonResponse
    {
        $data = $request->validate(['shift_status' => 'required|in:on_shift,off_duty']);
        $rider = Actor::rider();
        $rider->update($data);

        return response()->json([
            'message' => $data['shift_status'] === 'on_shift' ? 'You are online. New pickups can be assigned to you.' : 'You are offline. No new tasks will be assigned.',
            'shift_status' => $rider->shift_status,
        ]);
    }

    private static function notification(RiderNotification $n): array
    {
        return [
            'id' => $n->id,
            'type' => $n->type,
            'title' => $n->title,
            'body' => $n->body,
            'data' => $n->data ?? (object) [],
            'read' => $n->read_at !== null,
            'created_at' => $n->created_at->toIso8601String(),
        ];
    }

    /**
     * Recent activity from the rider's scans (and the station receiving what they picked up).
     * Scans of the same kind in a row at the same place collapse into one line.
     */
    private function activity(Rider $rider): array
    {
        $pickedUp = Assignment::where('rider_id', $rider->id)->where('type', 'pickup')->where('status', 'completed')
            ->latest('completed_at')->limit(100)->pluck('parcel_id');
        $shown = [ParcelStatus::PickedUp, ParcelStatus::OutForDelivery, ParcelStatus::Delivered, ParcelStatus::DeliveryFailed, ParcelStatus::ReturnedToSender];

        $events = ParcelEvent::with(['parcel' => fn ($q) => $q->withoutGlobalScopes(), 'hub'])
            ->where(fn ($q) => $q->where(fn ($r) => $r->where('rider_id', $rider->id)->whereIn('status', $shown))
                ->orWhere(fn ($r) => $r->whereIn('parcel_id', $pickedUp)->where('status', ParcelStatus::AtOriginHub)))
            ->latest('occurred_at')->latest('id')->limit(60)->get();

        $lines = new Collection;
        foreach ($events as $event) {
            $parcel = $event->parcel;
            [$type, $title, $place] = match ($event->status) {
                ParcelStatus::PickedUp => ['pickup', "Picked up at {$parcel->shipper_name}", $parcel->pickup_address],
                ParcelStatus::AtOriginHub => ['dropped_off', 'Dropped off at '.($event->hub?->name ?? 'the station'), $event->hub?->municipality_name],
                ParcelStatus::OutForDelivery => ['out_for_delivery', "Out for delivery: {$parcel->tracking_no}", $parcel->delivery_address],
                ParcelStatus::Delivered => ['delivered', "{$parcel->tracking_no} delivered", $parcel->delivery_address],
                ParcelStatus::DeliveryFailed => ['failed', "Delivery failed: {$parcel->tracking_no}", $parcel->delivery_address],
                ParcelStatus::ReturnedToSender => ['returned', "Returned to {$parcel->shipper_name}", $parcel->pickup_address],
            };
            $last = $lines->last();
            if ($last && $last['type'] === $type && $last['title'] === $title && in_array($type, ['pickup', 'dropped_off'], true)) {
                $last['count']++;
                $lines->put($lines->keys()->last(), $last);

                continue;
            }
            if ($lines->count() === 10) {
                break;
            }
            $lines->push(['type' => $type, 'title' => $title, 'place' => $place, 'count' => 1, 'at' => $event->occurred_at->toIso8601String()]);
        }

        return $lines->values()->all();
    }
}
