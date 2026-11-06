<?php

namespace App\Pickrush\Services\Shipping;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * When a priced parcel should arrive: a day range for its route level (config
 * pickrush.shipping.delivery_days), counted from today. A route whose seller region is unknown
 * (generic prices) uses the destination's farthest level, so the promise is never too short.
 */
class DeliveryEstimate
{
    /** @return array{level: int, from: CarbonImmutable, to: CarbonImmutable, label: string}|null */
    public function for(ShippingQuote $quote, ?CarbonImmutable $today = null): ?array
    {
        if (! $quote->ok() || ! $quote->destinationRegion) {
            return null;
        }
        $levels = DB::table('pr_shipping_route_levels')->where('destination_region', $quote->destinationRegion);
        $level = $quote->originRegion
            ? (clone $levels)->where('origin_region', $quote->originRegion)->value('level')
            : null;
        $level ??= (int) ((clone $levels)->max('level') ?? 2);

        $days = config('pickrush.shipping.delivery_days');
        [$min, $max] = $days[$level] ?? end($days);
        $today ??= CarbonImmutable::today();

        return ['level' => (int) $level, 'from' => $today->addDays($min), 'to' => $today->addDays($max), 'label' => self::label($today->addDays($min), $today->addDays($max))];
    }

    /** "Nov 9–11", or "Nov 30 – Dec 3" across months. */
    public static function label(CarbonImmutable $from, CarbonImmutable $to): string
    {
        return $from->isSameMonth($to) ? $from->format('M j').'–'.$to->format('j') : $from->format('M j').' – '.$to->format('M j');
    }
}
