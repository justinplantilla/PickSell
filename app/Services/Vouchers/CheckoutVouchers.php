<?php

namespace App\Services\Vouchers;

use App\Models\User;
use App\Models\Voucher;
use App\Services\OrderPlacement;
use Illuminate\Support\Collection;

/**
 * The store and PickSell vouchers on offer at checkout, which ones are picked (one store or product
 * voucher per store, keyed by seller id, and one PickSell voucher, or a typed code) and what they save. Placing the order
 * checks them again (OrderPlacement). Used by the website's checkout page and the buyer app's quote.
 */
class CheckoutVouchers
{
    public function __construct(private VoucherPricing $pricing) {}

    /**
     * Sorts out the picks before anything is priced: a typed code picks the store, product or PickSell
     * voucher it is (store and PickSell codes are not case-sensitive, product codes are), and each store
     * keeps either its store voucher or one product voucher, never both (the typed code, then the store voucher, wins).
     *
     * @param  Collection  $items  cart lines with product loaded
     * @param  array{stores?: array<int|string, int|string>, platform?: int|string|null}  $picked
     * @param  array<int|string, string|null>  $products  the product voucher code picked per seller id
     * @param  ?string  $productCode  a single product voucher code (from a product page), for every store it fits
     * @param  int|string|null  $codeFor  where the code was typed: 'platform' (PickSell vouchers), a seller id (that store's sheet) or null (any)
     * @return array{picked: array{stores: array<int, int>, platform: ?int}, products: array<int, string>, error: ?string}
     */
    public function resolve(Collection $items, array $picked = [], ?string $code = null, array $products = [], ?string $productCode = null, int|string|null $codeFor = null): array
    {
        $sellerIds = $items->pluck('product.seller_id')->unique()->values();
        $picked = ['stores' => array_map('intval', array_filter((array) ($picked['stores'] ?? []))), 'platform' => (int) ($picked['platform'] ?? 0) ?: null];
        $products = OrderPlacement::productCodes($items, $productCode, $products);
        $error = null;

        $code = trim((string) $code);
        if ($code !== '') {
            $typed = Voucher::live()->whereRaw('UPPER(code) = ?', [strtoupper($code)])
                ->where(fn ($q) => $q->whereNull('seller_id')->orWhereIn('seller_id', $sellerIds))->first();
            $product = OrderPlacement::productCodes($items, null, $sellerIds->mapWithKeys(fn ($id) => [$id => $code])->all());
            if ($codeFor === 'platform' && ! $typed?->isPlatform()) {
                $error = "{$code} is not a PickSell voucher. Enter store and product codes on the store's vouchers.";
            } elseif (is_numeric($codeFor) && ($typed ? $typed->seller_id != $codeFor : ! isset($product[(int) $codeFor]))) {
                $error = "{$code} is not a voucher for this store.";
            } elseif (is_numeric($codeFor) && ! $typed) {
                $products[(int) $codeFor] = $product[(int) $codeFor];
                unset($picked['stores'][(int) $codeFor]);
            } elseif ($typed?->isPlatform()) {
                $picked['platform'] = $typed->id;
            } elseif ($typed) {
                $picked['stores'][$typed->seller_id] = $typed->id;
                unset($products[$typed->seller_id]);
            } elseif ($product) {
                foreach ($product as $sellerId => $productCode) {
                    $products[$sellerId] = $productCode;
                    unset($picked['stores'][$sellerId]);
                }
            } else {
                $error = "{$code} is not a voucher for these items.";
            }
        }
        $products = array_diff_key($products, $picked['stores']);

        return ['picked' => $picked, 'products' => $products, 'error' => $error];
    }

    /**
     * @param  Collection  $items  cart lines with product loaded (and unit_price for their subtotal)
     * @param  array{picked: array, products: array<int, string>, error: ?string}  $resolved  from resolve()
     * @return array{stores: Collection, platform: Collection, picked: array{stores: array<int, int>, platform: ?int}, products: array<int, string>, productOptions: Collection, error: ?string, storeSavings: array<int, float>, platformSaving: float, total: float}
     */
    public function pick(User $buyer, Collection $items, float $shippingTotal, array $resolved): array
    {
        ['picked' => $picked, 'products' => $products, 'error' => $error] = $resolved;
        $subtotals = $items->groupBy(fn ($item) => $item->product->seller_id)->map(fn ($lines) => $lines->sum(fn ($item) => round(OrderPlacement::unitPrice($item->product, $products, $item->variation) * $item->quantity, 2)));

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
            'stores' => $stores, 'platform' => $platformOptions, 'picked' => $picked, 'products' => $products, 'productOptions' => self::productOptions($items), 'error' => $error,
            'storeSavings' => $storeSavings, 'platformSaving' => $platformSaving,
            'total' => round(array_sum($storeSavings) + $platformSaving, 2),
        ];
    }

    /**
     * The product vouchers a store's items carry, as the store's voucher sheet lists them next to its store vouchers.
     *
     * @return Collection<int, array{code: string, percent: float, product: string, discount: float}>  keyed by seller id
     */
    public static function productOptions(Collection $items): Collection
    {
        return $items->filter(fn ($item) => $item->product?->voucher_code && $item->product->voucher_discount > 0)
            ->groupBy(fn ($item) => $item->product->seller_id)
            ->map(fn ($lines) => $lines->groupBy(fn ($item) => $item->product->voucher_code)->map(fn ($same, $code) => [
                'code' => (string) $code,
                'percent' => (float) $same->first()->product->voucher_discount,
                'product' => $same->pluck('product.name')->unique()->join(', '),
                'discount' => round($same->sum(fn ($item) => ($item->product->priceFor($item->variation) - OrderPlacement::unitPrice($item->product, (string) $code, $item->variation)) * $item->quantity), 2),
            ])->values());
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
