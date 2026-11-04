<?php

namespace App\Services;

use App\Models\CartItem;
use App\Models\User;
use App\Pickrush\Services\Shipping\ShippingQuote;
use App\Support\BuyerApi\BuyerPresenter;
use Illuminate\Support\Collection;

/**
 * Prices cart lines for the app with the website's checkout rules (see OrderPlacement): a
 * delivery fee per seller (one parcel each, priced by Pickrush), no service fee, and a voucher
 * is a product's own code. Every number the app shows before placing an order comes from here
 * and equals what the order will store. Without a buyer (the cart page) shipping is not priced.
 */
class CartQuote
{
    public function __construct(private OrderPlacement $placement) {}

    /** @param Collection<int, CartItem> $items lines with product.seller and variation loaded */
    public function quote(Collection $items, ?string $voucherCode = null, array $wishlistIds = [], ?User $buyer = null): array
    {
        $shipping = $buyer ? $this->placement->shippingQuotes($buyer, $items->filter(fn (CartItem $item) => $item->product)) : collect();

        $voucherCode = $voucherCode !== null ? trim($voucherCode) : null;
        // Several lines (variations) can draw on the same product's stock, as on the website.
        $wanted = $items->groupBy('product_id')->map(fn ($lines) => $lines->sum('quantity'));

        $lines = $items->map(function (CartItem $item) use ($voucherCode, $wanted, $wishlistIds) {
            $product = $item->product;
            $unit = BuyerPresenter::money($product->effective_price);
            $lineTotal = round($unit * $item->quantity, 2);
            $withVoucher = round(OrderPlacement::unitPrice($product, $voucherCode) * $item->quantity, 2);

            return [
                'cart_item_id'    => $item->id,
                'product'         => BuyerPresenter::product($product, $wishlistIds),
                'quantity'        => (int) $item->quantity,
                'unit_price'      => $unit,
                'line_total'      => $lineTotal,
                'discount'        => round($lineTotal - $withVoucher, 2),
                'variation_ids'   => $item->variation_id ? [$item->variation_id] : [],
                'variation_label' => $item->variation ? ucfirst($item->variation->type).': '.$item->variation->value : null,
                'available_stock' => max(0, (int) $product->stock),
                'issue'           => match (true) {
                    ! $product->isPurchasable()                        => 'unavailable',
                    $product->stock <= 0                               => 'out_of_stock',
                    $wanted[$item->product_id] > $product->stock       => 'insufficient_stock',
                    default                                            => null,
                },
                'seller'          => $product->seller,
            ];
        });

        $groups = $lines->groupBy(fn ($line) => $line['seller']?->id)->map(function (Collection $sellerLines, $sellerId) use ($shipping, $items) {
            $quote = $shipping->get($sellerId);

            return [
                'seller'       => $sellerLines->first()['seller'] ? BuyerPresenter::sellerBrief($sellerLines->first()['seller']) : null,
                'items'        => $sellerLines->map(fn ($line) => collect($line)->except('seller')->all())->values(),
                'subtotal'     => round($sellerLines->sum('line_total'), 2),
                'discount'     => round($sellerLines->sum('discount'), 2),
                'shipping_fee' => $quote?->ok() ? (float) $quote->deliveryFee : 0.0,
                'shipping'     => $quote ? self::shipping($quote, $items->filter(fn (CartItem $item) => $item->product?->seller_id == $sellerId)) : null,
            ];
        })->values();

        $subtotal = round($lines->sum('line_total'), 2);
        $discount = round($lines->sum('discount'), 2);
        $shippingTotal = round($shipping->filter(fn (ShippingQuote $quote) => $quote->ok())->sum(fn (ShippingQuote $quote) => (float) $quote->deliveryFee), 2);
        $shippingProblem = $shipping->contains(fn (ShippingQuote $quote) => ! $quote->ok());
        $matched = $discount > 0;

        return [
            'groups'          => $groups,
            'shipping_option' => null,
            'voucher'         => $matched ? ['code' => $voucherCode, 'title' => 'Product voucher', 'discount' => $discount] : null,
            'voucher_error'   => $voucherCode && ! $matched ? 'That voucher doesn’t apply to the selected items. Product vouchers are case-sensitive.' : null,
            'has_issues'      => $shippingProblem || $lines->contains(fn ($line) => $line['issue'] !== null),
            'item_count'      => (int) $lines->sum('quantity'),
            'totals'          => [
                'merchandise_subtotal' => $subtotal,
                'shipping_total'       => $shippingTotal,
                'discount_total'       => $discount,
                'service_fee'          => 0.0,
                'total'                => round($subtotal - $discount + $shippingTotal, 2),
            ],
        ];
    }

    /** One seller's parcel as checkout shows it: the fee (money as 2-decimal strings) or why it cannot ship. */
    public static function shipping(ShippingQuote $quote, Collection $lines): array
    {
        if (! $quote->ok()) {
            return ['ok' => false, 'reason' => $quote->failure->value, 'message' => OrderPlacement::shippingProblem($quote, $lines->values())];
        }

        return [
            'ok' => true,
            'delivery_fee' => $quote->deliveryFee,
            'parcel_class' => $quote->parcelClass,
            'chargeable_weight_kg' => $quote->chargeableWeightKg,
        ];
    }
}
