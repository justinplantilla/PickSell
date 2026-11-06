<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\OrderShipment;
use App\Models\Product;
use App\Models\ProductVariation;
use App\Models\User;
use App\Notifications\NewSellerOrder;
use App\Pickrush\Bridge\PicksellBridge;
use App\Pickrush\Services\Shipping\QuoteFailure;
use App\Pickrush\Services\Shipping\ShippingQuote;
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
 * Each seller's lines ship together as one order shipment (one Pickrush parcel). Its delivery
 * fee is priced here by Pickrush, never taken from the browser or app, and saved with its
 * split so later rate or commission changes leave the order as it was.
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
     * Delivery fee per seller for these cart lines, priced as the order will be.
     *
     * @param  Collection<int, CartItem>  $items  lines with product.seller loaded
     * @return Collection<int, ShippingQuote>  keyed by seller id
     */
    public function shippingQuotes(User $buyer, Collection $items): Collection
    {
        return $items->groupBy(fn (CartItem $item) => $item->product->seller_id)
            ->map(fn (Collection $lines) => $this->pickrush->quote($buyer, $lines->first()->product->seller,
                $lines->map(fn (CartItem $item) => ['product' => $item->product, 'quantity' => (int) $item->quantity])));
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

    /** What one cart line costs per unit, with the product voucher applied when [$voucherCode] matches. */
    public static function unitPrice(Product $product, ?string $voucherCode): float
    {
        $price = $product->effective_price;
        if (! empty($voucherCode) && $product->voucher_code === $voucherCode) {
            $price = $price * (1 - $product->voucher_discount / 100);
        }

        return $price;
    }

    /**
     * @return Collection<int, Order>
     *
     * @throws ValidationException (field item_ids) when a line is unavailable or short of stock
     */
    public function place(User $buyer, array $itemIds, ?string $voucherCode): Collection
    {
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
        $shipping = $this->priceShipping($buyer, $items, $to);

        $placed = DB::transaction(function () use ($buyer, $items, $voucherCode, $shipping) {
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

            $shipments = $shipping->map(fn (array $priced, $sellerId) => OrderShipment::create([
                'reference' => 'SHP-'.strtoupper(Str::random(10)),
                'buyer_id' => $buyer->id,
                'seller_id' => $sellerId,
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
            ]));

            return $items->map(function (CartItem $item) use ($buyer, $products, $variations, $voucherCode, $shipments) {
                $product = $products[$item->product_id];
                $variation = $item->variation_id ? $variations[$item->variation_id] : null;
                $amount = self::unitPrice($product, $voucherCode, $variation) * $item->quantity;
                $commission = $this->commission->calculate((string) round($amount, 2));

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
                    'commission' => $commission['commission'],
                    'commission_rate' => $commission['rate'],
                    'status' => 'placed',
                    'tracking_status' => 'Order placed and awaiting seller preparation',
                ]);

                $product->decrement('stock', $item->quantity);
                $variation?->decrement('stock', $item->quantity);
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
     * Price every seller's parcel and split its fee, or refuse the whole checkout.
     *
     * @return Collection<int, array{quote: ShippingQuote, split: array, seller: ?User}>  keyed by seller id
     */
    private function priceShipping(User $buyer, Collection $items): Collection
    {
        $groups = $items->groupBy(fn (CartItem $item) => $item->product->seller_id);

        return $this->shippingQuotes($buyer, $items)->map(function (ShippingQuote $quote, $sellerId) use ($groups) {
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

            return ['quote' => $quote, 'split' => $split, 'seller' => $lines->first()->product->seller];
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
