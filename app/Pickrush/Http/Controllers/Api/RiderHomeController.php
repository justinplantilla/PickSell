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
use App\Pickrush\Support\ParcelHubScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/** The rider app's home: today's numbers, open batches, notifications, and going on or off shift. */
class RiderHomeController extends Controller
{
    public function dashboard(RiderBatches $batches): JsonResponse
    {
        $rider = Actor::rider();
        $rider->loadMissing('station');
        $today = RiderEarningsController::today($rider);

        return response()->json([
            'shift_status' => $rider->shift_status,
            'hub' => $rider->station ? RiderProfile::hub($rider->station) : null,
            'today' => [
                'earnings' => $today['earnings'],
                'pickups' => $today['pickups'],
                'delivered' => $today['deliveries'],
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
            'return_failure_reasons' => $list(config('pickrush.return_failure_reasons')),
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
     * The full activity history for the "View All" page: everything, pickups (collected, dropped
     * off at the station) or deliveries (out for delivery, delivered, failed, returned to the
     * seller), newest first, a page at a time. Optional filters: one line `type`, and a `from`–`to`
     * date range (inclusive, station time). Pass back `next` as `before` for the next page.
     */
    public function activityPage(Request $request): JsonResponse
    {
        $data = $request->validate([
            'kind' => 'nullable|in:all,pickup,delivery',
            'type' => 'nullable|in:'.implode(',', array_keys(self::TYPE_STATUSES)),
            'from' => 'nullable|date_format:Y-m-d',
            'to' => 'nullable|date_format:Y-m-d|after_or_equal:from',
            'before' => ['nullable', 'string', 'regex:/^\d+\|\d+$/'],
        ]);
        $kind = ($data['kind'] ?? 'all') === 'all' ? null : $data['kind'];
        $before = isset($data['before']) ? array_map('intval', explode('|', $data['before'])) : null;
        $day = fn (?string $date) => $date ? Carbon::createFromFormat('Y-m-d', $date, config('app.timezone')) : null;
        [$lines, $next] = $this->activityLines(Actor::rider(), $kind, 30, $before,
            $data['type'] ?? null, $day($data['from'] ?? null)?->startOfDay(), $day($data['to'] ?? null)?->endOfDay());

        return response()->json(['activity' => $lines, 'next' => $next]);
    }

    /**
     * Each line type and the scans behind it. dropped_off is the station receiving what the rider
     * picked up; a rider's "returning to sender" scan is a hand-back the seller did not accept.
     */
    private const TYPE_STATUSES = [
        'pickup' => [ParcelStatus::PickedUp],
        'dropped_off' => [ParcelStatus::AtOriginHub],
        'out_for_delivery' => [ParcelStatus::OutForDelivery],
        'delivered' => [ParcelStatus::Delivered],
        'failed' => [ParcelStatus::DeliveryFailed, ParcelStatus::ReturningToSender],
        'returned' => [ParcelStatus::ReturnedToSender],
    ];

    private const KIND_TYPES = [
        'pickup' => ['pickup', 'dropped_off'],
        'delivery' => ['out_for_delivery', 'delivered', 'failed', 'returned'],
    ];

    /** The dashboard's short list: the newest lines of any kind. */
    private function activity(Rider $rider): array
    {
        return $this->activityLines($rider, null, 5)[0];
    }

    /**
     * Activity lines from the rider's scans (and the station receiving what they picked up).
     * Scans of the same kind in a row at the same place on the same day collapse into one line.
     *
     * @param  'pickup'|'delivery'|null  $kind  null: both
     * @param  array{0: int, 1: int}|null  $before  [unix time, event id] of the last event already shown
     * @param  string|null  $type  only this line type (a TYPE_STATUSES key); one outside $kind finds nothing
     * @return array{0: list<array>, 1: ?string} the lines, and the cursor for the next page (null: no more)
     */
    private function activityLines(Rider $rider, ?string $kind, int $limit, ?array $before = null,
        ?string $type = null, ?Carbon $from = null, ?Carbon $to = null): array
    {
        $types = $kind ? self::KIND_TYPES[$kind] : array_keys(self::TYPE_STATUSES);
        $types = $type ? array_intersect($types, [$type]) : $types;
        $riderStatuses = collect($types)->reject(fn ($t) => $t === 'dropped_off')->flatMap(fn ($t) => self::TYPE_STATUSES[$t])->all();
        $pickedUp = Assignment::select('parcel_id')->where('rider_id', $rider->id)->where('type', 'pickup')->where('status', 'completed');

        $query = ParcelEvent::with(['parcel' => fn ($q) => $q->withoutGlobalScope(ParcelHubScope::class), 'hub'])
            ->where(function ($q) use ($rider, $riderStatuses, $types, $pickedUp) {
                $q->where(fn ($r) => $r->where('rider_id', $rider->id)->whereIn('status', $riderStatuses));
                if (in_array('dropped_off', $types, true)) {
                    $q->orWhere(fn ($r) => $r->whereIn('parcel_id', $pickedUp)->where('status', ParcelStatus::AtOriginHub));
                }
            })
            ->when($from, fn ($q) => $q->where('occurred_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('occurred_at', '<=', $to))
            ->when($before, function ($q) use ($before) {
                $at = Carbon::createFromTimestamp($before[0], config('app.timezone'));   // stored in app time, not UTC
                $q->where(fn ($r) => $r->where('occurred_at', '<', $at)->orWhere(fn ($s) => $s->where('occurred_at', $at)->where('id', '<', $before[1])));
            })
            ->latest('occurred_at')->latest('id');

        $lines = new Collection;
        $lastShown = null;
        $more = false;
        foreach ($query->limit($limit * 8)->get() as $event) {
            $parcel = $event->parcel;
            [$type, $title, $place] = match ($event->status) {
                ParcelStatus::PickedUp => ['pickup', "Picked up at {$parcel->shipper_name}", $parcel->pickup_address],
                ParcelStatus::AtOriginHub => ['dropped_off', 'Dropped off at '.($event->hub?->name ?? 'the station'), $event->hub?->municipality_name],
                ParcelStatus::OutForDelivery => ['out_for_delivery', "Out for delivery: {$parcel->tracking_no}", $parcel->delivery_address],
                ParcelStatus::Delivered => ['delivered', "{$parcel->tracking_no} delivered", $parcel->delivery_address],
                ParcelStatus::DeliveryFailed => ['failed', "Delivery failed: {$parcel->tracking_no}", $parcel->delivery_address],
                ParcelStatus::ReturnedToSender => ['returned', "Returned to {$parcel->shipper_name}", $parcel->pickup_address],
                ParcelStatus::ReturningToSender => ['failed', "Return not accepted: {$parcel->tracking_no}", $parcel->pickup_address],
            };
            $last = $lines->last();
            if ($last && $last['type'] === $type && $last['title'] === $title && in_array($type, ['pickup', 'dropped_off'], true)
                && $last['day'] === $event->occurred_at->toDateString()) {
                $last['count']++;
                $lines->put($lines->keys()->last(), $last);
                $lastShown = $event;

                continue;
            }
            if ($lines->count() === $limit) {
                $more = true;
                break;
            }
            $lines->push(['type' => $type, 'title' => $title, 'place' => $place, 'count' => 1,
                'at' => $event->occurred_at->toIso8601String(), 'day' => $event->occurred_at->toDateString()]);
            $lastShown = $event;
        }
        // Ran out of fetched events with a full page: there may be older ones.
        $more = $more || ($lines->count() === $limit && $lastShown !== null);
        $next = $more ? $lastShown->occurred_at->getTimestamp().'|'.$lastShown->id : null;

        return [$lines->map(fn (array $line) => array_diff_key($line, ['day' => true]))->values()->all(), $next];
    }
}
