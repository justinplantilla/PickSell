<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\OrderShipment;
use App\Models\Product;
use App\Models\ProductVariation;
use App\Models\User;
use App\Models\Voucher;
use App\Models\VoucherRedemption;
use App\Notifications\LowStockAlert;
use App\Notifications\NewSellerOrder;
use App\Pickrush\Bridge\PicksellBridge;
use App\Pickrush\Services\Shipping\QuoteFailure;
use App\Pickrush\Services\Shipping\ShippingQuote;
use App\Services\Vouchers\VoucherPricing;
use App\Support\DeliveryAddress;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Places the buyer's selected cart lines as orders exactly like the website's checkout
 * (BuyerController::placeOrder): one order per cart line, price = product price minus its
 * discount and, if the code matches, the product's own voucher; Cash on Delivery to the buyer's
 * profile address. Keep the two in step.
 *
 * Each seller's lines ship together as one order shipment (one parcel) with the logistics company
 * the buyer chose for it (Pickrush pre-selected). Its delivery fee is priced here from that
 * company's rates, never taken from the browser or app, and saved with its split so later rate
 * or commission changes leave the order as it was.
 */
class OrderPlacement
{
    public function __construct(
        private CommissionService $commission,
        private PicksellBridge $pickrush,
        private DeliveryFeeSplit $deliverySplit,
        private AdminNotificationService $admins,
    ) {}

    /**
     * Delivery fee per seller for these cart lines, priced as the order will be, by the carrier the
     * buyer chose for that seller's parcel (Pickrush unless they picked another).
     *
     * @param  Collection<int, CartItem>  $items  lines with product.seller loaded
     * @param  array<int|string, int|string>  $carriers  logistics company id per seller id
     * @return Collection<int, ShippingQuote>  keyed by seller id
     */
    public function shippingQuotes(User $buyer, Collection $items, ?DeliveryAddress $to = null, array $carriers = []): Collection
    {
        return $this->carrierChoices($buyer, $items, $to, $carriers)->map(fn (array $choice) => $choice['quote']);
    }

    /**
     * Each seller's parcel: the logistics companies the buyer can choose from (Pickrush first) and
     * the one chosen. A choice that is not on offer, or none, falls back to the first (Pickrush).
     *
     * @param  array<int|string, int|string>  $carriers  logistics company id per seller id
     * @return Collection<int, array{options: Collection, company_id: int, quote: ShippingQuote}>  keyed by seller id
     */
    public function carrierChoices(User $buyer, Collection $items, ?DeliveryAddress $to = null, array $carriers = []): Collection
    {
        return $items->groupBy(fn (CartItem $item) => $item->product->seller_id)->map(function (Collection $lines, $sellerId) use ($buyer, $to, $carriers) {
            $options = $this->pickrush->carriers($buyer, $lines->first()->product->seller,
                $lines->map(fn (CartItem $item) => ['product' => $item->product, 'quantity' => (int) $item->quantity]), $to);
            $wanted = (int) ($carriers[$sellerId] ?? 0);
            $companyId = $options->has($wanted) ? $wanted : (int) $options->keys()->first();

            return ['options' => $options, 'company_id' => $companyId, 'quote' => $options[$companyId]['quote']];
        });
    }

    /** What the buyer is told when a seller's items cannot be priced. */
    public static function shippingProblem(ShippingQuote $quote, Collection $lines): string
    {
        $seller = $lines->first()?->product?->seller;
        $shop = $seller?->business_name ?: $seller?->full_name ?: 'this seller';

        return match ($quote->failure) {
            QuoteFailure::InvalidWeight => 'Can’t be shipped yet (the seller has not set its weight): '
                .$lines->reject(fn ($item) => $item->product?->hasShippingWeight())->map(fn ($item) => $item->product?->name)->filter()->unique()->join(', ')
                .'. Remove it from your cart or ask the seller.',
            QuoteFailure::RequiresQuote => "Items from {$shop}: ".$quote->failure->message(),
            default => $quote->failure->message(),
        };
    }

    /** The buyer's own cart lines among [$ids]. */
    public function items(User $buyer, array $ids): Collection
    {
        $cart = Cart::firstOrCreate(['buyer_id' => $buyer->id]);

        return CartItem::where('cart_id', $cart->id)->whereIn('id', $ids)->with('product.seller', 'product.variations', 'variation')->get();
    }

    /** What one cart line costs per unit (at its option's price), with the product voucher applied when [$voucherCode] matches. */
    public static function unitPrice(Product $product, ?string $voucherCode, ?ProductVariation $variation = null): float
    {
        $price = $product->priceFor($variation);
        if (! empty($voucherCode) && $product->voucher_code === $voucherCode) {
            $price = $price * (1 - $product->voucher_discount / 100);
        }

        return $price;
    }

    /**
     * @param  DeliveryAddress|null  $to  where to ship; the buyer's profile (default) address when null
     * @param  array<int|string, string|null>  $notes  the buyer's note to each seller, keyed by seller id
     * @param  array{stores?: array<int|string, int>, platform?: ?int}  $vouchers  voucher ids: one per store (keyed by seller id) and one platform voucher
     * @param  array<int|string, int|string>  $carriers  the logistics company chosen per seller id (Pickrush when absent)
     * @return Collection<int, Order>
     *
     * @throws ValidationException (field item_ids, address or vouchers) when the checkout cannot go ahead
     */
    public function place(User $buyer, array $itemIds, ?string $voucherCode, ?DeliveryAddress $to = null, array $notes = [], array $vouchers = [], array $carriers = []): Collection
    {
        $to ??= DeliveryAddress::fromProfile($buyer);
        if (! $to->isComplete()) {
            throw ValidationException::withMessages(['address' => 'Add the province, city and barangay of your delivery address before placing an order.']);
        }
        $items = $this->items($buyer, $itemIds);
        if ($items->isEmpty()) {
            throw ValidationException::withMessages(['item_ids' => 'No items selected.']);
        }

        // Checked again under lock below; here so pricing never runs on a removed product.
        $unavailable = $items->reject(fn ($item) => $item->product?->isPurchasable());
        if ($unavailable->isNotEmpty()) {
            throw ValidationException::withMessages(['item_ids' => self::unavailableMessage($unavailable)]);
        }
        $unchosen = self::missingOption($items);
        if ($unchosen->isNotEmpty()) {
            throw ValidationException::withMessages(['item_ids' => self::missingOptionMessage($unchosen)]);
        }
        $shipping = $this->priceShipping($buyer, $items, $to, $carriers);

        $placed = DB::transaction(function () use ($buyer, $items, $voucherCode, $shipping, $to, $notes, $vouchers) {
            // Lock the products so moderation, other buyers and stock changes cannot interleave.
            $products = Product::whereIn('id', $items->pluck('product_id'))->with('seller')->lockForUpdate()->get()->keyBy('id');
            $unavailable = $items->filter(fn ($item) => ! $products->get($item->product_id)?->isPurchasable());
            if ($unavailable->isNotEmpty()) {
                throw ValidationException::withMessages(['item_ids' => self::unavailableMessage($unavailable)]);
            }
            // Several cart lines (e.g. variations) can draw on the same product's stock.
            $shortStock = $items->groupBy('product_id')
                ->filter(fn ($lines, $productId) => $products[$productId]->stock < $lines->sum('quantity'));
            if ($shortStock->isNotEmpty()) {
                throw ValidationException::withMessages(['item_ids' => 'Not enough stock for: '.$shortStock->keys()->map(fn ($productId) => $products[$productId]->name)->join(', ').'.']);
            }
            // Each option has its own stock too; lines whose option was deleted are caught above.
            $variations = ProductVariation::whereIn('id', $items->pluck('variation_id')->filter())->lockForUpdate()->get()->keyBy('id');
            $shortOption = $items->whereNotNull('variation_id')->groupBy('variation_id')
                ->filter(fn ($lines, $variationId) => ($variations->get($variationId)?->stock ?? 0) < $lines->sum('quantity'));
            if ($shortOption->isNotEmpty()) {
                throw ValidationException::withMessages(['item_ids' => 'Not enough stock for: '.$shortOption->map(fn ($lines, $variationId) => $products[$lines->first()->product_id]->name
                    .($variations->has($variationId) ? ' ('.$variations[$variationId]->label.')' : ''))->join(', ').'.']);
            }

            // Each line's price (with its product voucher), then the checkout vouchers on top.
            $lineAmounts = $items->mapWithKeys(fn (CartItem $item) => [$item->id => round(self::unitPrice(
                $products[$item->product_id], $voucherCode, $item->variation_id ? $variations[$item->variation_id] : null) * $item->quantity, 2)]);
            $discounts = $this->voucherDiscounts($buyer, $items, $lineAmounts, $shipping, $vouchers);

            $shipments = $shipping->map(fn (array $priced, $sellerId) => OrderShipment::create([
                'reference' => 'SHP-'.strtoupper(Str::random(10)),
                'buyer_id' => $buyer->id,
                'seller_id' => $sellerId,
                'logistics_company_id' => $priced['company_id'],
                'origin_province' => $priced['seller']?->province,
                'origin_region' => $priced['quote']->originRegion,
                'destination_region' => $priced['quote']->destinationRegion,
                'price_source' => $priced['quote']->priceSource,
                'parcel_class' => $priced['quote']->parcelClass,
                'chargeable_weight_kg' => $priced['quote']->chargeableWeightKg,
                'delivery_fee' => $priced['split']['delivery_fee'],
                'rider_pay' => $priced['split']['rider_pay'],
                'platform_commission' => $priced['split']['platform_commission'],
                'logistics_share' => $priced['split']['logistics_share'],
                'delivery_commission_rate' => $priced['split']['rate'],
                'rates_version' => $priced['quote']->ratesVersion,
                'buyer_note' => filled($notes[$sellerId] ?? null) ? trim($notes[$sellerId]) : null,
                'store_voucher_id' => $discounts['stores'][$sellerId]['voucher']?->id,
                'store_discount' => $discounts['stores'][$sellerId]['amount'],
                'platform_voucher_id' => $discounts['platform']?->id,
                'platform_discount' => $discounts['platformShares'][$sellerId] ?? 0,
                'shipping_discount' => $discounts['shippingShares'][$sellerId] ?? 0,
            ] + $to->shipmentColumns()));
            $this->redeem($buyer, $discounts, $shipments);

            return $items->map(function (CartItem $item) use ($buyer, $products, $variations, $lineAmounts, $discounts, $shipments) {
                $product = $products[$item->product_id];
                $variation = $item->variation_id ? $variations[$item->variation_id] : null;
                // The store voucher is the seller's: the order is saved at the lower amount, so commission follows.
                $storeDiscount = $discounts['lineShares'][$item->id] ?? 0;
                $amount = round($lineAmounts[$item->id] - $storeDiscount, 2);
                $commission = $this->commission->calculate((string) $amount);

                $order = Order::create([
                    'order_number' => 'ORD-'.strtoupper(Str::random(8)),
                    'product_id' => $product->id,
                    'variation_id' => $variation?->id,
                    'buyer_id' => $buyer->id,
                    'seller_id' => $product->seller_id,
                    'order_shipment_id' => $shipments[$product->seller_id]->id,
                    'product_name' => $product->name,
                    'variation_label' => $variation?->label,
                    'quantity' => $item->quantity,
                    'amount' => $amount,
                    'store_discount' => $storeDiscount,
                    'commission' => $commission['commission'],
                    'commission_rate' => $commission['rate'],
                    'status' => 'placed',
                    'tracking_status' => 'Order placed and awaiting seller preparation',
                ]);

                $product->decrement('stock', $item->quantity);
                $variation?->decrement('stock', $item->quantity);
                $this->alertLowStock($product, $variation, (int) $item->quantity);
                $item->delete();

                return $order;
            });
        });

        foreach ($placed as $order) {
            $order->seller?->notify(new NewSellerOrder($order));
        }

        return $placed;
    }

    /**
     * Checks the chosen vouchers again (under the checkout's lock) and works out what each takes off:
     * a store voucher on that store's lines, spread over them; a platform voucher over the stores'
     * parcels, on the items or (free shipping) on the delivery fees.
     */
    private function voucherDiscounts(User $buyer, Collection $items, Collection $lineAmounts, Collection $shipping, array $chosen): array
    {
        $pricing = app(VoucherPricing::class);
        $refuse = fn (string $message) => throw ValidationException::withMessages(['vouchers' => $message]);
        $bySeller = $items->groupBy(fn (CartItem $item) => $item->product->seller_id);

        $stores = [];
        $lineShares = [];
        foreach ($bySeller as $sellerId => $lines) {
            $subtotal = $lines->sum(fn (CartItem $item) => $lineAmounts[$item->id]);
            $voucher = isset($chosen['stores'][$sellerId])
                ? (Voucher::whereKey($chosen['stores'][$sellerId])->where('seller_id', $sellerId)->lockForUpdate()->first() ?? $refuse('That store voucher is not available.'))
                : null;
            if ($voucher && ($problem = $pricing->problem($voucher, $buyer, $subtotal))) {
                $refuse("{$voucher->code}: {$problem}");
            }
            $amount = $voucher ? $pricing->discount($voucher, $subtotal) : 0;
            $stores[$sellerId] = ['voucher' => $voucher, 'amount' => $amount];
            $lineShares += VoucherPricing::allocate($amount, $lines->mapWithKeys(fn (CartItem $item) => [$item->id => $lineAmounts[$item->id]])->all());
        }

        $platform = ! empty($chosen['platform'])
            ? (Voucher::whereKey($chosen['platform'])->whereNull('seller_id')->lockForUpdate()->first() ?? $refuse('That PickSell voucher is not available.'))
            : null;
        $merchBySeller = collect($stores)->map(fn ($store, $sellerId) => $bySeller[$sellerId]->sum(fn (CartItem $item) => $lineAmounts[$item->id]) - $store['amount']);
        $feeBySeller = $shipping->map(fn (array $priced) => (float) $priced['split']['delivery_fee']);
        $platformShares = [];
        $shippingShares = [];
        if ($platform) {
            if ($problem = $pricing->problem($platform, $buyer, $merchBySeller->sum())) {
                $refuse("{$platform->code}: {$problem}");
            }
            $off = $pricing->discount($platform, $merchBySeller->sum(), $feeBySeller->sum());
            if ($platform->type === Voucher::FREE_SHIPPING) {
                $shippingShares = VoucherPricing::allocate($off, $feeBySeller->all());
            } else {
                $platformShares = VoucherPricing::allocate($off, $merchBySeller->all());
            }
        }

        return compact('stores', 'lineShares', 'platform', 'platformShares', 'shippingShares');
    }

    /** Tell the seller when this order took the product or option down to (or past) the low-stock line. */
    private function alertLowStock(Product $product, ?ProductVariation $variation, int $quantity): void
    {
        $crossed = fn (int $after) => $after <= Product::LOW_STOCK_THRESHOLD && $after + $quantity > Product::LOW_STOCK_THRESHOLD;
        if ($variation && $crossed((int) $variation->stock)) {
            $product->seller?->notify(new LowStockAlert($product, $variation, (int) $variation->stock));
        } elseif (! $variation && $crossed((int) $product->stock)) {
            $product->seller?->notify(new LowStockAlert($product, null, (int) $product->stock));
        }
    }

    /** One redemption per voucher used in this checkout, so usage limits count each checkout once. */
    private function redeem(User $buyer, array $discounts, Collection $shipments): void
    {
        foreach ($discounts['stores'] as $sellerId => $store) {
            if ($store['voucher']) {
                VoucherRedemption::create(['voucher_id' => $store['voucher']->id, 'buyer_id' => $buyer->id, 'order_shipment_id' => $shipments[$sellerId]->id, 'amount' => $store['amount']]);
            }
        }
        if ($discounts['platform']) {
            VoucherRedemption::create(['voucher_id' => $discounts['platform']->id, 'buyer_id' => $buyer->id, 'order_shipment_id' => $shipments->first()->id,
                'amount' => array_sum($discounts['platformShares']) + array_sum($discounts['shippingShares'])]);
        }
    }

    /**
     * Price every seller's parcel and split its fee, or refuse the whole checkout.
     *
     * @return Collection<int, array{quote: ShippingQuote, split: array, seller: ?User, company_id: int}>  keyed by seller id
     */
    private function priceShipping(User $buyer, Collection $items, DeliveryAddress $to, array $carriers = []): Collection
    {
        $groups = $items->groupBy(fn (CartItem $item) => $item->product->seller_id);

        return $this->carrierChoices($buyer, $items, $to, $carriers)->map(function (array $choice, $sellerId) use ($groups) {
            $quote = $choice['quote'];
            $lines = $groups[$sellerId];
            if (! $quote->ok()) {
                throw ValidationException::withMessages(['item_ids' => self::shippingProblem($quote, $lines)]);
            }
            try {
                $split = $this->deliverySplit->split($quote->deliveryFee, $quote->riderPay);
            } catch (DomainException $e) {
                // A rate or parcel class is set up wrong (the fee does not cover the rider): fix the setup, never ship at a loss.
                $this->admins->notifyAdmins('shipping.split_refused', 'Order refused: delivery fee too low',
                    "{$e->getMessage()} ({$quote->destinationRegion}, {$quote->parcelClass}, {$quote->ratesVersion}). Check the Pickrush rates and parcel classes.",
                    route('admin.orders', [], false), priority: 'critical');
                throw ValidationException::withMessages(['item_ids' => 'We could not calculate shipping for these items. Our team has been alerted; please try again later.']);
            }

            return ['quote' => $quote, 'split' => $split, 'seller' => $lines->first()->product->seller, 'company_id' => $choice['company_id']];
        });
    }

    /** Lines of a product sold in options whose line has none (never chosen, or the seller deleted it). */
    public static function missingOption(Collection $items): Collection
    {
        return $items->filter(fn (CartItem $item) => $item->product && ! $item->variation_id && $item->product->variations->isNotEmpty());
    }

    public static function missingOptionMessage(Collection $items): string
    {
        return 'Choose an option for: '.$items->map(fn ($item) => $item->product->name)->unique()->join(', ')
            .'. Remove '.($items->count() === 1 ? 'it' : 'them').' from your cart and add '.($items->count() === 1 ? 'it' : 'them').' again with an option.';
    }

    public static function unavailableMessage(Collection $items): string
    {
        return 'No longer available: '.$items->map(fn ($item) => $item->product
            ? $item->product->name.($item->product->sellerOnVacation() ? ' (seller on vacation)' : '')
            : 'a removed product')->join(', ')
            .'. Remove '.($items->count() === 1 ? 'it' : 'them').' from your cart to continue.';
    }
}
