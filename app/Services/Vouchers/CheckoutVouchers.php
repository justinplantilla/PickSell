<?php

namespace App\Services\Vouchers;

use App\Models\User;
use App\Models\Voucher;
use Illuminate\Support\Collection;

/**
 * The store and PickSell vouchers on offer at checkout, which ones are picked (one per store, keyed
 * by seller id, and one PickSell voucher, or a typed code) and what they save. Placing the order
 * checks them again (OrderPlacement). Used by the website's checkout page and the buyer app's quote.
 */
class CheckoutVouchers
{
    public function __construct(private VoucherPricing $pricing) {}

    /**
     * @param  Collection  $items  cart lines with product loaded (and unit_price for their subtotal)
     * @param  array{stores?: array<int|string, int|string>, platform?: int|string|null}  $picked
     * @return array{stores: Collection, platform: Collection, picked: array{stores: array<int, int>, platform: ?int}, error: ?string, storeSavings: array<int, float>, platformSaving: float, total: float}
     */
    public function pick(User $buyer, Collection $items, float $shippingTotal, array $picked = [], ?string $code = null, ?string $productCode = null): array
    {
        $subtotals = $items->groupBy(fn ($item) => $item->product->seller_id)->map(fn ($lines) => $lines->sum(fn ($item) => round(\App\Services\OrderPlacement::unitPrice($item->product, $productCode, $item->variation) * $item->quantity, 2)));
        $picked = ['stores' => array_map('intval', (array) ($picked['stores'] ?? [])), 'platform' => (int) ($picked['platform'] ?? 0) ?: null];
        $error = null;

        // A typed code picks the store or PickSell voucher it belongs to (codes are not case-sensitive).
        $code = strtoupper(trim((string) $code));
        if ($code !== '') {
            $typed = Voucher::live()->whereRaw('UPPER(code) = ?', [$code])
                ->where(fn ($q) => $q->whereNull('seller_id')->orWhereIn('seller_id', $subtotals->keys()))->first();
            if (! $typed) {
                $error = "{$code} is not a voucher for these items.";
            } elseif ($typed->isPlatform()) {
                $picked['platform'] = $typed->id;
            } else {
                $picked['stores'][$typed->seller_id] = $typed->id;
            }
        }

        $stores = $subtotals->map(fn ($subtotal, $sellerId) => $this->pricing->options($this->pricing->storeVouchers($sellerId), $buyer, $subtotal));
        $storeSavings = [];
        foreach ($stores as $sellerId => $options) {
            $choice = $options->first(fn ($o) => $o['voucher']->id === ($picked['stores'][$sellerId] ?? null));
            if ($choice && $choice['problem']) {
                $error ??= "{$choice['voucher']->code}: {$choice['problem']}";
            }
            $storeSavings[$sellerId] = $choice && ! $choice['problem'] ? $choice['discount'] : 0;
            if (! $storeSavings[$sellerId]) {
                unset($picked['stores'][$sellerId]);
            }
        }

        $platformOptions = $this->pricing->options($this->pricing->platformVouchers(), $buyer, $subtotals->sum() - array_sum($storeSavings), $shippingTotal);
        $platformChoice = $platformOptions->first(fn ($o) => $o['voucher']->id === $picked['platform']);
        if ($platformChoice && $platformChoice['problem']) {
            $error ??= "{$platformChoice['voucher']->code}: {$platformChoice['problem']}";
        }
        $platformSaving = $platformChoice && ! $platformChoice['problem'] ? $platformChoice['discount'] : 0;
        if (! $platformSaving) {
            $picked['platform'] = null;
        }

        return [
            'stores' => $stores, 'platform' => $platformOptions, 'picked' => $picked, 'error' => $error,
            'storeSavings' => $storeSavings, 'platformSaving' => $platformSaving,
            'total' => round(array_sum($storeSavings) + $platformSaving, 2),
        ];
    }
    /** The same proportional platform allocation as placement, exposed per store for both clients. */
    public function storeTotals(array $quote, array $vouchers): array
    {
        $groups = collect($quote['groups'])->keyBy(fn ($g) => $g['seller']['id']);
        $merch = $groups->map(fn ($g, $id) => round($g['subtotal'] - $g['discount'] - ($vouchers['storeSavings'][$id] ?? 0), 2));
        $platform = $vouchers['platform']->first(fn ($o) => $o['voucher']->id === $vouchers['picked']['platform']);
        $freeShipping = ($platform['voucher']->type ?? null) === Voucher::FREE_SHIPPING;
        $shares = VoucherPricing::allocate($vouchers['platformSaving'], ($freeShipping ? $groups->pluck('shipping_fee', 'seller.id') : $merch)->all());
        return $groups->map(fn ($g, $id) => [
            'subtotal' => $g['subtotal'], 'product_discount' => $g['discount'],
            'store_discount' => $vouchers['storeSavings'][$id] ?? 0,
            'store_voucher_id' => $vouchers['picked']['stores'][$id] ?? null,
            'shipping_fee' => $g['shipping_fee'],
            'platform_discount' => $freeShipping ? 0 : ($shares[$id] ?? 0),
            'shipping_discount' => $freeShipping ? ($shares[$id] ?? 0) : 0,
            'total' => max(0, round($merch[$id] + $g['shipping_fee'] - ($shares[$id] ?? 0), 2)),
        ])->all();
    }

}
