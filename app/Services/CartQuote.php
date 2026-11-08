<?php

namespace App\Services;

use App\Models\CartItem;
use App\Models\User;
use App\Pickrush\Services\Shipping\DeliveryEstimate;
use App\Pickrush\Services\Shipping\ShippingQuote;
use App\Support\BuyerApi\BuyerPresenter;
use App\Support\DeliveryAddress;
use Illuminate\Support\Collection;

/**
 * Prices cart lines for the app with the website's checkout rules (see OrderPlacement): a
 * delivery fee per seller (one parcel each, priced by the logistics company the buyer chose for
 * it, Pickrush pre-selected), no service fee, and a voucher
 * is a product's own code. Every number the app shows before placing an order comes from here
 * and equals what the order will store. Without a buyer (the cart page) shipping is not priced.
 */
class CartQuote
{
    public function __construct(private OrderPlacement $placement) {}

    /** @param Collection<int, CartItem> $items lines with product.seller and variation loaded */
    /** $to: where it ships (an address-book entry); the buyer's profile address when null. */
    /** $carriers: the logistics company chosen per seller id; Pickrush (pre-selected) when absent. */
    public function quote(Collection $items, ?string $voucherCode = null, array $wishlistIds = [], ?User $buyer = null, ?DeliveryAddress $to = null, array $carriers = []): array
    {
        $choices = $buyer ? $this->placement->carrierChoices($buyer, $items->filter(fn (CartItem $item) => $item->product), $to, $carriers) : collect();
        $shipping = $choices->map(fn (array $choice) => $choice['quote']);

        $voucherCode = $voucherCode !== null ? trim($voucherCode) : null;
        // Several lines (variations) can draw on the same product's stock, as on the website.
        $wanted = $items->groupBy('product_id')->map(fn ($lines) => $lines->sum('quantity'));
        $wantedOption = $items->whereNotNull('variation_id')->groupBy('variation_id')->map(fn ($lines) => $lines->sum('quantity'));

        $lines = $items->map(function (CartItem $item) use ($voucherCode, $wanted, $wantedOption, $wishlistIds) {
            $product = $item->product;
            $unit = BuyerPresenter::money($product->priceFor($item->variation));
            $lineTotal = round($unit * $item->quantity, 2);
            $withVoucher = round(OrderPlacement::unitPrice($product, $voucherCode, $item->variation) * $item->quantity, 2);

            return [
                'cart_item_id'    => $item->id,
                'product'         => BuyerPresenter::product($product, $wishlistIds),
                'quantity'        => (int) $item->quantity,
                'unit_price'      => $unit,
                'line_total'      => $lineTotal,
                'discount'        => round($lineTotal - $withVoucher, 2),
                'variation_ids'   => $item->variation_id ? [$item->variation_id] : [],
                'variation_label' => $item->variation?->label,
                'available_stock' => max(0, (int) ($item->variation ? min($item->variation->stock, $product->stock) : $product->stock)),
                'issue'           => match (true) {
                    ! $product->isPurchasable()                        => 'unavailable',
                    // The product is sold in options and this line has none (or the seller deleted it).
                    ! $item->variation && $product->variations->isNotEmpty() => 'option_required',
                    $product->stock <= 0                               => 'out_of_stock',
                    $item->variation && $item->variation->stock <= 0   => 'out_of_stock',
                    $wanted[$item->product_id] > $product->stock       => 'insufficient_stock',
                    $item->variation && $wantedOption[$item->variation_id] > $item->variation->stock => 'insufficient_stock',
                    default                                            => null,
                },
                'seller'          => $product->seller,
            ];
        });

        $groups = $lines->groupBy(fn ($line) => $line['seller']?->id)->map(function (Collection $sellerLines, $sellerId) use ($shipping, $items, $choices) {
            $quote = $shipping->get($sellerId);
            $choice = $choices->get($sellerId);
            $sellerItems = $items->filter(fn (CartItem $item) => $item->product?->seller_id == $sellerId);

            return [
                'seller'       => $sellerLines->first()['seller'] ? BuyerPresenter::sellerBrief($sellerLines->first()['seller']) : null,
                'items'        => $sellerLines->map(fn ($line) => collect($line)->except('seller')->all())->values(),
                'subtotal'     => round($sellerLines->sum('line_total'), 2),
                'discount'     => round($sellerLines->sum('discount'), 2),
                'shipping_fee' => $quote?->ok() ? (float) $quote->deliveryFee : 0.0,
                'shipping'     => $quote ? self::shipping($quote, $sellerItems, $choice['company_id']) : null,
                // The logistics companies this parcel can go with (Pickrush first); 'carrier' is the chosen one.
                'carrier'      => $choice ? (string) $choice['company_id'] : null,
                'carriers'     => $choice ? self::carriers($choice, $sellerItems) : [],
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

    /**
     * The carrier options of one seller's parcel, as checkout lists them.
     *
     * @param  array{options: Collection, company_id: int}  $choice  OrderPlacement::carrierChoices
     */
    public static function carriers(array $choice, Collection $lines): array
    {
        return $choice['options']->map(fn (array $option, $companyId) => [
            'id' => (string) $companyId,
            'name' => $option['company']->name,
            'is_default' => $option['company']->is_default,
            'selected' => (int) $companyId === $choice['company_id'],
        ] + self::shipping($option['quote'], $lines, (int) $companyId))->values()->all();
    }

    /** One seller's parcel as checkout shows it: the fee (money as 2-decimal strings) or why it cannot ship. */
    public static function shipping(ShippingQuote $quote, Collection $lines, ?int $companyId = null): array
    {
        if (! $quote->ok()) {
            return ['ok' => false, 'reason' => $quote->failure->value, 'message' => OrderPlacement::shippingProblem($quote, $lines->values())];
        }

        $eta = app(DeliveryEstimate::class)->for($quote, companyId: $companyId);

        return [
            'ok' => true,
            'delivery_fee' => $quote->deliveryFee,
            'parcel_class' => $quote->parcelClass,
            'chargeable_weight_kg' => $quote->chargeableWeightKg,
            'estimated_delivery' => $eta ? ['from' => $eta['from']->toDateString(), 'to' => $eta['to']->toDateString(), 'label' => $eta['label']] : null,
        ];
    }
}
