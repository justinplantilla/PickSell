<?php

namespace App\Pickrush\Services\Shipping;

use App\Models\Order;
use App\Pickrush\Models\Company;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * When a priced parcel should arrive: a day range for its route level (config
 * pickrush.shipping.delivery_days), counted from today. A route whose seller region is unknown
 * (generic prices) uses the destination's farthest level, so the promise is never too short.
 */
class DeliveryEstimate
{
    /**
     * @param  int|null  $companyId  the carrier whose route grid applies (default: Pickrush)
     * @return array{level: int, from: CarbonImmutable, to: CarbonImmutable, label: string}|null
     */
    public function for(ShippingQuote $quote, ?CarbonImmutable $today = null, ?int $companyId = null): ?array
    {
        if (! $quote->ok() || ! $quote->destinationRegion) {
            return null;
        }
        $level = self::level($quote->originRegion, $quote->destinationRegion, $companyId);
        [$min, $max] = self::days($level);
        $today ??= CarbonImmutable::today();

        return ['level' => $level, 'from' => $today->addDays($min), 'to' => $today->addDays($max), 'label' => self::label($today->addDays($min), $today->addDays($max))];
    }

    /** Order steps that still have an arrival to estimate. */
    public const OPEN_STEPS = ['placed', 'pending', 'confirmed', 'preparing', 'processing', 'ready_for_pickup', 'picked_up', 'shipped',
        'at_sorting_center', 'sorted', 'assigned_to_rider', 'out_for_delivery', 'delivery_failed'];

    /**
     * The ETA of a placed order, as the buyer's tracking shows it: the checkout window for its
     * route counted from the day it was placed; "today" once a rider is bringing it; and, past the
     * window and still not delivered, a fresh one-day estimate flagged late. Null once it arrived,
     * was cancelled or went back.
     *
     * @return array{from: string, to: string, label: string, status: string, message: string}|null
     */
    public function forOrder(Order $order, ?CarbonImmutable $today = null): ?array
    {
        $shipment = $order->shipment;
        if (! $shipment?->destination_region || ! in_array($order->status, self::OPEN_STEPS, true)) {
            return null;
        }
        $today ??= CarbonImmutable::today();
        $placed = CarbonImmutable::parse($order->created_at)->startOfDay();
        [$min, $max] = self::days(self::level($shipment->origin_region, $shipment->destination_region, $shipment->logistics_company_id));
        $from = $placed->addDays($min);
        $to = $placed->addDays($max);

        if ($order->status === 'out_for_delivery') {
            return self::window($today, $today, 'today', 'Arriving today — your rider is on the way.');
        }
        if ($to->lt($today)) {
            // Past the promise: at least a day more (it has not reached the rider yet).
            $next = $order->status === 'assigned_to_rider' ? $today : $today->addDay();

            return self::window($next, $next, 'late', 'Running late. It was expected by '.$to->format('M j').'.');
        }
        if ($from->lt($today)) {
            $from = $today;   // the window's start has passed: show only what is left of it
        }

        return self::window($from, $to, 'on_time', $from->eq($to) && $from->eq($today) ? 'Arriving today.' : 'Estimated from the shipping route.');
    }

    private static function window(CarbonImmutable $from, CarbonImmutable $to, string $status, string $message): array
    {
        return ['from' => $from->toDateString(), 'to' => $to->toDateString(),
            'label' => $from->eq($to) ? $from->format('M j') : self::label($from, $to), 'status' => $status, 'message' => $message];
    }

    /** The route level between two regions on a carrier's grid; unknown origin uses the farthest level. */
    private static function level(?string $origin, string $destination, ?int $companyId): int
    {
        $levels = DB::table('pr_shipping_route_levels')->where('company_id', $companyId ?? Company::defaultId())
            ->where('destination_region', $destination);
        $level = $origin ? (clone $levels)->where('origin_region', $origin)->value('level') : null;

        return (int) ($level ?? (clone $levels)->max('level') ?? 2);
    }

    /** @return array{0: int, 1: int} min and max days for a route level */
    private static function days(int $level): array
    {
        $days = config('pickrush.shipping.delivery_days');

        return $days[$level] ?? end($days);
    }

    /** "Nov 9–11", or "Nov 30 – Dec 3" across months. */
    public static function label(CarbonImmutable $from, CarbonImmutable $to): string
    {
        return $from->isSameMonth($to) ? $from->format('M j').'–'.$to->format('j') : $from->format('M j').' – '.$to->format('M j');
    }
}
