<?php

namespace App\Services;

use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

/**
 * A seller's track record over the last 90 days: service rating, completed orders, how often
 * orders were cancelled and how fast parcels were handed over. Preferred Seller needs a rating of
 * 4.0+, 10+ completed orders and at most 10% cancelled. Kept for an hour per seller.
 */
class SellerPerformance
{
    public const MIN_RATING = 4.0;
    public const MIN_COMPLETED = 10;
    public const MAX_CANCEL_RATE = 10.0;
    public const WINDOW_DAYS = 90;

    /** Preferred Seller allows at most this % of orders cancelled for shipping late. */
    public const MAX_LATE_RATE = 5.0;

    /** @return array{rating: ?float, ratings: int, completed: int, orders: int, cancel_rate: ?float, ship_hours: ?float, preferred: bool, warnings: string[]} */
    public function for(User $seller): array
    {
        return Cache::remember("seller-performance:{$seller->id}", 3600, fn () => $this->compute($seller));
    }

    public function isPreferred(User $seller): bool
    {
        return $this->for($seller)['preferred'];
    }

    public function forget(User $seller): void
    {
        Cache::forget("seller-performance:{$seller->id}");
    }

    private function compute(User $seller): array
    {
        $recent = Order::where('seller_id', $seller->id)->where('created_at', '>=', now()->subDays(self::WINDOW_DAYS));
        $orders = (clone $recent)->count();
        $cancelled = (clone $recent)->where('status', 'cancelled')->count();
        $completed = (clone $recent)->where('status', 'completed')->count();
        $rated = (clone $recent)->whereNotNull('seller_rating');
        $ratings = (clone $rated)->count();
        $rating = $ratings ? round((float) (clone $rated)->avg('seller_rating'), 1) : null;
        $handedOver = (clone $recent)->whereNotNull('handed_over_at')->get(['created_at', 'handed_over_at']);
        $shipHours = $handedOver->isNotEmpty() ? round($handedOver->avg(fn ($o) => $o->created_at->diffInMinutes($o->handed_over_at) / 60), 1) : null;
        $cancelRate = $orders ? round($cancelled / $orders * 100, 1) : null;
        // Orders auto-cancelled because the seller did not hand them to the courier in time.
        $late = (clone $recent)->whereNotNull('late_cancelled_at')->count();
        $lateRate = $orders ? round($late / $orders * 100, 1) : null;

        $warnings = [];
        if ($late > 0) {
            $warnings[] = "{$late} order(s) cancelled for shipping late";
        }
        if ($cancelRate !== null && $orders >= 5 && $cancelRate > self::MAX_CANCEL_RATE) {
            $warnings[] = "Cancels {$cancelRate}% of orders";
        }
        if ($rating !== null && $ratings >= 3 && $rating < 3.5) {
            $warnings[] = "Low service rating ({$rating})";
        }
        if ($shipHours !== null && $shipHours > 72) {
            $warnings[] = 'Slow to hand over parcels ('.round($shipHours / 24, 1).' days)';
        }

        return [
            'rating' => $rating, 'ratings' => $ratings, 'completed' => $completed, 'orders' => $orders,
            'cancel_rate' => $cancelRate, 'ship_hours' => $shipHours, 'late' => $late, 'late_rate' => $lateRate,
            'preferred' => $rating !== null && $rating >= self::MIN_RATING && $completed >= self::MIN_COMPLETED
                && ($cancelRate ?? 0) <= self::MAX_CANCEL_RATE && ($lateRate ?? 0) <= self::MAX_LATE_RATE,
            'warnings' => $warnings,
        ];
    }
}
