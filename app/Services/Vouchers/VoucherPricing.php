<?php

namespace App\Services\Vouchers;

use App\Models\User;
use App\Models\Voucher;
use App\Models\VoucherRedemption;
use Illuminate\Support\Collection;

/**
 * Whether a voucher can be used and what it takes off. A store voucher works on that store's
 * items; a platform voucher on the whole checkout (or its shipping, for free shipping). Amounts
 * are pesos rounded to centavos.
 */
class VoucherPricing
{
    /** Why the buyer cannot use [$voucher] on [$spend] pesos of items right now, or null. */
    public function problem(Voucher $voucher, User $buyer, float $spend): ?string
    {
        if ($voucher->is_active && $voucher->isUpcoming()) {
            return 'Starts '.$voucher->starts_at->format('M j').'.';
        }
        if (! $voucher->is_active || ($voucher->ends_at && $voucher->ends_at->isPast())) {
            return 'This voucher is not available right now.';
        }
        if ($voucher->usage_limit !== null && $voucher->redemptions()->count() >= $voucher->usage_limit) {
            return 'This voucher has been fully used.';
        }
        if (VoucherRedemption::where('voucher_id', $voucher->id)->where('buyer_id', $buyer->id)->count() >= $voucher->per_buyer_limit) {
            return 'You have already used this voucher.';
        }
        if ($spend < (float) $voucher->min_spend) {
            return 'Spend ₱'.number_format((float) $voucher->min_spend - $spend, 2).' more to use this voucher.';
        }

        return null;
    }

    /** What [$voucher] takes off [$merchandise] (or off [$shipping], for free shipping). */
    public function discount(Voucher $voucher, float $merchandise, float $shipping = 0): float
    {
        $cap = $voucher->max_discount !== null ? (float) $voucher->max_discount : INF;
        $off = match ($voucher->type) {
            Voucher::FIXED => min((float) $voucher->amount, $merchandise),
            Voucher::PERCENT => min($merchandise, $merchandise * (float) $voucher->amount / 100, $cap),
            Voucher::FREE_SHIPPING => min($shipping, $cap),
            default => 0,
        };

        return round(max(0, $off), 2);
    }

    /**
     * Vouchers to offer, best saving first, each with its discount or why it cannot be used, how
     * many more times this buyer may use it, and whether it is the one to recommend (the best
     * usable saving).
     *
     * @return Collection<int, array{voucher: Voucher, discount: float, problem: ?string, uses_left: int, upcoming: bool, recommended: bool}>
     */
    public function options(Collection $vouchers, User $buyer, float $merchandise, float $shipping = 0): Collection
    {
        $used = VoucherRedemption::where('buyer_id', $buyer->id)->whereIn('voucher_id', $vouchers->pluck('id'))
            ->selectRaw('voucher_id, count(*) as uses')->groupBy('voucher_id')->pluck('uses', 'voucher_id');
        $options = $vouchers->map(fn (Voucher $voucher) => [
            'voucher' => $voucher,
            'discount' => $this->discount($voucher, $merchandise, $shipping),
            'problem' => $this->problem($voucher, $buyer, $merchandise),
            'uses_left' => max(0, (int) $voucher->per_buyer_limit - (int) ($used[$voucher->id] ?? 0)),
            'upcoming' => $voucher->isUpcoming(),
            'recommended' => false,
        ])->sortBy([fn ($a, $b) => ($a['problem'] !== null) <=> ($b['problem'] !== null), fn ($a, $b) => $b['discount'] <=> $a['discount']])->values();

        if ($options->isNotEmpty() && $options[0]['problem'] === null && $options[0]['discount'] > 0) {
            $options[0] = ['recommended' => true] + $options[0];
        }

        return $options;
    }

    /** Store vouchers on offer from [$sellerId]: live ones, and scheduled ones shown ahead (not usable yet). */
    public function storeVouchers(int $sellerId): Collection
    {
        return Voucher::offered()->where('seller_id', $sellerId)->get();
    }

    public function platformVouchers(): Collection
    {
        return Voucher::offered()->whereNull('seller_id')->get();
    }

    /**
     * Splits [$total] centavos over [$weights] in proportion, the remainder to the last, so the
     * parts always add up exactly.
     *
     * @param  array<int|string, float>  $weights
     * @return array<int|string, float> pesos
     */
    public static function allocate(float $total, array $weights): array
    {
        $totalCents = (int) round($total * 100);
        $sum = array_sum($weights);
        $parts = [];
        $given = 0;
        $keys = array_keys($weights);
        foreach ($keys as $i => $key) {
            $cents = $i === count($keys) - 1 ? $totalCents - $given : ($sum > 0 ? (int) floor($totalCents * $weights[$key] / $sum) : 0);
            $parts[$key] = $cents / 100;
            $given += $cents;
        }

        return $parts;
    }
}
