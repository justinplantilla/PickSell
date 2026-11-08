<?php

namespace App\Http\Controllers\BuyerApi;

use App\Http\Controllers\Controller;
use App\Services\CartQuote;
use App\Services\OrderPlacement;
use App\Services\Vouchers\CheckoutVouchers;
use App\Support\BuyerApi\BuyerPresenter;
use App\Support\DeliveryAddress;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Checkout with the website's rules: Cash on Delivery to the buyer's profile address, or to an
 * address-book entry (address_id), priced by the product's discount and its own voucher code, plus
 * one delivery fee per seller, less the store and PickSell vouchers picked (store_vouchers,
 * platform_voucher, or a typed code on the quote). No service fee.
 */
class CheckoutController extends Controller
{
    public function __construct(private CartQuote $quote, private OrderPlacement $placement, private CheckoutVouchers $vouchers) {}

    public function paymentMethods()
    {
        return response()->json(['payment_methods' => config('buyer-api.payment_methods')]);
    }

    /** POST /buyer/checkout/quote {item_ids[], voucher_code?, address_id?, store_vouchers?: {seller_id: id}, platform_voucher?, code?, carriers?: {seller_id: company_id}} */
    public function quote(Request $request)
    {
        $data = $request->validate($this->rules($request) + ['code' => 'nullable|string|max:30']);
        $buyer = $request->user();
        $to = DeliveryAddress::forBuyer($buyer, $data['address_id'] ?? null);
        $items = $this->placement->items($buyer, $data['item_ids']);
        $quote = $this->quote->quote($items, $data['voucher_code'] ?? null, buyer: $buyer, to: $to, carriers: $data['carriers'] ?? []);
        $vouchers = $this->vouchers->pick($buyer, $items->filter(fn ($item) => $item->product), (float) $quote['totals']['shipping_total'],
            ['stores' => $data['store_vouchers'] ?? [], 'platform' => $data['platform_voucher'] ?? null], $data['code'] ?? null);

        return response()->json([
            'quote'            => $quote,
            'delivery_address' => BuyerPresenter::deliveryAddress($buyer),
            'ship_to'          => BuyerPresenter::shipTo($to),
            'vouchers'         => BuyerPresenter::checkoutVouchers($vouchers),
        ]);
    }

    /** POST /buyer/checkout {item_ids[], voucher_code?, address_id?, store_vouchers?, platform_voucher?, carriers?, payment_method?: cod} — one order per cart line. */
    public function place(Request $request)
    {
        $data = $request->validate($this->rules($request) + [
            'payment_method' => ['nullable', Rule::in(collect(config('buyer-api.payment_methods'))->pluck('code'))],
        ], ['payment_method.in' => 'Only Cash on Delivery is available.']);
        $buyer = $request->user();

        $to = DeliveryAddress::forBuyer($buyer, $data['address_id'] ?? null);
        if (! $to->isComplete()) {
            $message = $to->buyerAddressId
                ? 'Add the province, city and barangay of this address before placing an order.'
                : 'Add your province, city and barangay in your profile before placing an order.';

            return response()->json(['message' => $message, 'errors' => ['address' => [$message]]], 422);
        }

        // The vouchers are checked again here (a 422 on "vouchers" when one can no longer be used).
        $orders = $this->placement->place($buyer, $data['item_ids'], $data['voucher_code'] ?? null, $to, [],
            ['stores' => $data['store_vouchers'] ?? [], 'platform' => $data['platform_voucher'] ?? null], $data['carriers'] ?? []);
        $orders->load('seller', 'product', 'shipment');
        $shipments = $orders->pluck('shipment')->filter()->unique('id');
        $shipping = $shipments->sum(fn ($shipment) => (float) $shipment->delivery_fee);
        // PickSell vouchers come off the parcels; store vouchers are already in each order's amount.
        $pickSellOff = $shipments->sum(fn ($shipment) => (float) $shipment->platform_discount + (float) $shipment->shipping_discount);

        return response()->json([
            'message'      => 'Order placed! We’ll notify you as it moves.',
            'checkout_ref' => $orders->first()->order_number,
            'shipping_total' => BuyerPresenter::money($shipping),
            'total'        => BuyerPresenter::money(max(0, $orders->sum('amount') + $shipping - $pickSellOff)),
            'orders'       => $orders->map(fn ($o) => BuyerPresenter::orderCard($o))->values(),
        ], 201);
    }

    private function rules(Request $request): array
    {
        return [
            'item_ids'         => 'required|array|min:1',
            'item_ids.*'       => 'integer',
            'voucher_code'     => 'nullable|string|max:50',
            // Null or absent: the profile (default) address. Only the buyer's own address-book entries.
            'address_id'       => ['nullable', 'integer', Rule::exists('buyer_addresses', 'id')->where('user_id', $request->user()->id)],
            // One store voucher per store, keyed by seller id, and one PickSell voucher (as on the website).
            'store_vouchers'   => 'nullable|array',
            'store_vouchers.*' => 'nullable|integer',
            'platform_voucher' => 'nullable|integer',
            // The logistics company per seller's parcel, from the quote's carriers; Pickrush when absent.
            'carriers'         => 'nullable|array',
            'carriers.*'       => 'nullable|integer',
        ];
    }
}
