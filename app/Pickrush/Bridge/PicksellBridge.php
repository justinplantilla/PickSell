<?php

namespace App\Pickrush\Bridge;

use App\Models\BuyerAddress;
use App\Models\Order;
use App\Models\OrderShipment;
use App\Models\User;
use App\Pickrush\Enums\ParcelStatus;
use App\Pickrush\Models\Company;
use App\Pickrush\Models\Parcel;
use App\Pickrush\Models\Rider;
use App\Pickrush\Models\RiderReport;
use App\Pickrush\Services\HubRouter;
use App\Pickrush\Services\ParcelLedger;
use App\Pickrush\Services\ParcelRegistrar;
use App\Pickrush\Services\Shipping\QuoteFailure;
use App\Pickrush\Services\Shipping\RegionResolver;
use App\Pickrush\Services\Shipping\ShippingCalculator;
use App\Pickrush\Services\Shipping\ShippingQuote;
use App\Pickrush\Support\Actor;
use App\Services\CommissionService;
use App\Services\Geo\AddressResolver;
use App\Support\DeliveryAddress;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Throwable;

/**
 * The single seam between the Picksell marketplace and Pickrush, shaped like a carrier API:
 * the marketplace books a parcel for an order and reads its tracking; Pickrush pushes status
 * back through SyncOrderWithParcel. Nothing else in the marketplace touches pr_* tables.
 *
 * A parcel's external_ref is its order shipment's reference (SHP-...), shared by one seller's
 * orders from one checkout; orders placed before shipments existed use their own id.
 * The contract is written up in docs/shipping-contract.md.
 */
class PicksellBridge
{
    public const SOURCE = 'picksell';

    public function __construct(
        private ParcelRegistrar $registrar,
        private ParcelLedger $ledger,
        private AddressResolver $addresses,
        private ShippingCalculator $shipping,
        private HubRouter $router,
    ) {}

    /**
     * The delivery fee for one seller's items to the buyer's address (or [$to], one they chose),
     * from one logistics company's rates (Pickrush when none is given).
     *
     * @param  iterable<array{product: \App\Models\Product, quantity: int}>  $lines
     */
    public function quote(?User $buyer, ?User $seller, iterable $lines, ?DeliveryAddress $to = null, ?int $companyId = null): ShippingQuote
    {
        try {
            return $this->shipping->quote(self::weights($lines), $to ? $to->province : $buyer?->province, $seller?->province,
                buyerCity: $to ? $to->municipality : $buyer?->municipality, companyId: $companyId);
        } catch (Throwable $e) {
            report($e);

            return ShippingQuote::failed(QuoteFailure::Unavailable);
        }
    }

    /**
     * The logistics companies the buyer can choose for one seller's parcel, each priced from its
     * own rates, the default company (Pickrush) first. Pickrush is always listed, as before buyers
     * could choose; another approved company only when it has active stations at the seller's and
     * the buyer's city with a route between them, and its price could be worked out.
     *
     * @param  iterable<array{product: \App\Models\Product, quantity: int}>  $lines
     * @return Collection<int, array{company: Company, quote: ShippingQuote}>  keyed by company id
     */
    public function carriers(?User $buyer, ?User $seller, iterable $lines, ?DeliveryAddress $to = null): Collection
    {
        $lines = collect($lines);
        $origin = $this->codes($seller)['municipality_code'];
        $destination = $to
            ? ($to->municipalityCode ?? $this->addresses->resolve($to->province, $to->municipality, $to->barangay)['municipality_code'])
            : $this->codes($buyer)['municipality_code'];

        return Company::approved()->orderByDesc('is_default')->orderBy('name')->get()
            ->filter(fn (Company $company) => $company->is_default || $this->serves($company->id, $origin, $destination))
            ->mapWithKeys(fn (Company $company) => [$company->id => [
                'company' => $company,
                'quote' => $this->quote($buyer, $seller, $lines, $to, $company->id),
            ]])
            ->filter(fn (array $option) => $option['company']->is_default || $option['quote']->ok());
    }

    /** Whether a company can carry a parcel between two cities: active stations at both and a route. */
    private function serves(int $companyId, ?string $origin, ?string $destination): bool
    {
        $from = $this->router->stationFor($origin, $companyId);
        $to = $this->router->stationFor($destination, $companyId);

        return $from?->is_active && $to?->is_active && $this->router->path($from, $to) !== null;
    }

    /** @return list<array{weight_kg: mixed, quantity: int}> */
    private static function weights(iterable $lines): array
    {
        return collect($lines)->map(fn (array $line) => [
            'weight_kg' => $line['product']->weight_kg,
            'quantity' => (int) $line['quantity'],
        ])->values()->all();
    }

    /**
     * Generate the waybill: books the parcel as "label created", so the seller can print it and
     * keep packing. Riders are not alerted until requestPickup(). Idempotent: one parcel per order.
     */
    public function book(Order $order): Parcel
    {
        if ($existing = self::parcelFor($order)) {
            return $existing;
        }

        $seller = $order->seller;
        $buyer = $order->buyer;
        $pickup = $this->codes($seller);
        $shipment = $order->shipment;
        // Shipments since the address book keep a copy of the address chosen at checkout.
        $to = $shipment?->delivery_province ? $shipment : null;
        $delivery = $to
            ? ($to->delivery_municipality_code ? ['municipality_code' => $to->delivery_municipality_code, 'barangay_code' => $to->delivery_barangay_code]
                : $this->addresses->resolve($to->delivery_province, $to->delivery_municipality, $to->delivery_barangay))
            : $this->codes($buyer);
        $orders = $shipment ? $shipment->activeOrders()->orderBy('id')->get() : collect([$order]);

        return $this->registrar->register([
            // The carrier the buyer chose at checkout; orders from before then were Pickrush's.
            'company_id' => $shipment?->logistics_company_id,
            'source' => self::SOURCE,
            'external_ref' => self::ref($order),
            'shipper_name' => $seller?->business_name ?: $seller?->full_name ?: 'Picksell seller',
            'shipper_mobile' => $seller?->contact_no,
            'pickup_address' => self::address($seller),
            'pickup_barangay_code' => $pickup['barangay_code'],
            'pickup_municipality_code' => $pickup['municipality_code'],
            'pickup_lat' => $seller?->pickup_lat,
            'pickup_lng' => $seller?->pickup_lng,
            'consignee_name' => $to?->recipient_name ?? $buyer?->full_name ?? 'Picksell buyer',
            'consignee_mobile' => $to ? $to->recipient_phone : $buyer?->contact_no,
            'delivery_address' => $to ? $to->delivery_address : self::address($buyer),
            'delivery_barangay_code' => $delivery['barangay_code'],
            'delivery_municipality_code' => $delivery['municipality_code'],
            // Kept current on the shipment when the buyer moves that address's pin (buyerPinned, addressPinned).
            'delivery_lat' => $to ? $to->delivery_lat : $buyer?->delivery_lat,
            'delivery_lng' => $to ? $to->delivery_lng : $buyer?->delivery_lng,
            'item_description' => self::contents($orders),
            'cod_amount' => self::codAmount($orders, $shipment),
        ] + ($shipment ? [
            // The fee and its split as quoted at checkout; Pickrush's own rate card is not used.
            'shipping_fee' => $shipment->delivery_fee,
            'weight_kg' => $shipment->chargeable_weight_kg,
            'parcel_class' => $shipment->parcel_class,
            'destination_region' => $shipment->destination_region,
            'origin_region' => $shipment->origin_region,
            'price_source' => $shipment->price_source,
            'rider_pay' => $shipment->rider_pay,
            'platform_commission' => $shipment->platform_commission,
            'logistics_share' => $shipment->logistics_share,
        ] : []), Actor::picksell(), ParcelStatus::LabelCreated);
    }

    /** The seller handed the packed parcel over: it joins the station's pickup queue. Idempotent. */
    public function requestPickup(Order $order): Parcel
    {
        $parcel = self::parcelFor($order);
        abort_unless($parcel, 409, 'Generate the waybill before handing the order over.');

        if ($parcel->current_status === ParcelStatus::LabelCreated) {
            $this->ledger->record($parcel, ParcelStatus::WaitingPickup, null, ['actor' => Actor::picksell()]);
        }

        return $parcel;
    }

    /**
     * Called when the marketplace cancels an order. Only a parcel still waiting for pickup can be
     * cancelled; once Pickrush has it, the order must go through a return instead.
     */
    public function cancel(Order $order, string $reason): void
    {
        $parcel = self::parcelFor($order);
        $parcelOpen = $parcel && $parcel->current_status !== ParcelStatus::Cancelled;
        if ($parcelOpen) {
            abort_unless(in_array($parcel->current_status, [ParcelStatus::LabelCreated, ParcelStatus::WaitingPickup], true), 409,
                "Pickrush already has parcel {$parcel->tracking_no} ({$parcel->current_status->label()}); it cannot be cancelled, only returned.");
        }

        // One item out of a shared parcel: the parcel still goes, carrying (and collecting for) the rest.
        $shipment = $order->shipment;
        if ($shipment) {
            $remaining = $shipment->activeOrders()->whereKeyNot($order->id)->orderBy('id')->get();
            if ($remaining->isNotEmpty()) {
                if ($parcelOpen) {
                    $parcel->update(['cod_amount' => self::codAmount($remaining, $shipment), 'item_description' => self::contents($remaining)]);
                }

                return;
            }
            $shipment->update(['status' => 'cancelled']);
        }

        if ($parcelOpen) {
            $this->ledger->record($parcel, ParcelStatus::Cancelled, null, ['note' => $reason, 'actor' => Actor::picksell()]);
        }
    }

    /**
     * The seller moved their shop pin: parcels still waiting for a rider follow it, so the rider
     * app checks arrival against the new spot.
     */
    public static function sellerPinned(User $seller): int
    {
        $refs = self::refsWhere('seller_id', $seller->id);

        return Parcel::withoutGlobalScopes()->where('source', self::SOURCE)->whereIn('external_ref', $refs)
            ->whereIn('current_status', [ParcelStatus::LabelCreated, ParcelStatus::WaitingPickup])
            ->update(['pickup_lat' => $seller->pickup_lat, 'pickup_lng' => $seller->pickup_lng]);
    }

    /** The buyer moved their default address's pin: parcels not yet delivered there follow it, so the rider navigates to the new spot. */
    public static function buyerPinned(User $buyer): int
    {
        // Parcels going to an address-book entry keep that entry's pin.
        $toDefault = OrderShipment::where('buyer_id', $buyer->id)->whereNull('buyer_address_id');
        $refs = Order::where('buyer_id', $buyer->id)->whereNull('order_shipment_id')->pluck('id')->map(fn ($id) => (string) $id)
            ->merge((clone $toDefault)->pluck('reference'));

        return self::movePin($toDefault, $refs, $buyer->delivery_lat, $buyer->delivery_lng);
    }

    /** The buyer moved an address-book entry's pin: the same for parcels going there. */
    public static function addressPinned(BuyerAddress $address): int
    {
        $toAddress = OrderShipment::where('buyer_address_id', $address->id);

        return self::movePin($toAddress, (clone $toAddress)->pluck('reference'), $address->delivery_lat, $address->delivery_lng);
    }

    private static function movePin($shipments, Collection $refs, $lat, $lng): int
    {
        $shipments->whereNotIn('status', ['delivered', ...OrderShipment::VOID])->update(['delivery_lat' => $lat, 'delivery_lng' => $lng]);

        return Parcel::withoutGlobalScopes()->where('source', self::SOURCE)->whereIn('external_ref', $refs)
            ->where('is_return', false)
            ->whereNotIn('current_status', [ParcelStatus::Delivered, ParcelStatus::ReturnedToSender, ParcelStatus::Cancelled])
            ->update(['delivery_lat' => $lat, 'delivery_lng' => $lng]);
    }

    /**
     * Marketplace order numbers for the rider app (parcel id => order number).
     *
     * @param  iterable<Parcel>  $parcels
     * @return array<int, string>
     */
    public static function orderNumbers(iterable $parcels): array
    {
        $parcels = collect($parcels)->where('source', self::SOURCE);
        $refs = $parcels->pluck('external_ref');
        $byShipment = Order::query()->join('order_shipments', 'order_shipments.id', '=', 'orders.order_shipment_id')
            ->whereIn('order_shipments.reference', $refs->filter(fn ($ref) => self::isShipmentRef($ref))->values())
            ->orderBy('orders.id')->get(['orders.order_number', 'order_shipments.reference'])
            ->groupBy('reference')->map(fn ($rows) => $rows->pluck('order_number')->join(', '));
        $byOrder = Order::whereIn('id', $refs->reject(fn ($ref) => self::isShipmentRef($ref))->map(fn ($ref) => (int) $ref)->values())
            ->pluck('order_number', 'id');

        return $parcels->mapWithKeys(fn (Parcel $parcel) => [$parcel->id => self::isShipmentRef($parcel->external_ref)
            ? ($byShipment[$parcel->external_ref] ?? null)
            : ($byOrder[(int) $parcel->external_ref] ?? null)])
            ->filter()->all();
    }

    public static function parcelFor(Order $order): ?Parcel
    {
        return Parcel::withoutGlobalScopes()->where('source', self::SOURCE)->where('external_ref', self::ref($order))->first();
    }

    /** The rider who completed the delivery of the order's parcel, if it was delivered by one. */
    public static function deliveryRiderId(Order $order): ?int
    {
        return self::parcelFor($order)?->assignments()
            ->where('type', 'delivery')->where('status', 'completed')
            ->value('rider_id');
    }

    /**
     * A buyer rated a rider's delivery: when the rider's last 10 ratings (at least 5) average under
     * pickrush.low_rating_threshold, the rider's station gets an alert on its dashboard (one open at a time).
     */
    public static function deliveryRated(int $riderId): void
    {
        $recent = Order::where('delivery_rider_id', $riderId)->whereNotNull('delivery_rating')->latest('updated_at')->take(10)->pluck('delivery_rating');
        $rider = Rider::withoutGlobalScopes()->find($riderId);
        if (! $rider || $recent->count() < 5 || $recent->avg() >= (float) config('pickrush.low_rating_threshold')) {
            return;
        }
        $open = RiderReport::where('rider_id', $riderId)->where('type', 'low_rating')->whereNull('resolved_at')->exists();
        if (! $open) {
            RiderReport::create(['rider_id' => $riderId, 'station_hub_id' => $rider->station_hub_id, 'type' => 'low_rating',
                'note' => 'Buyers rated the last '.$recent->count().' deliveries '.number_format($recent->avg(), 1).' of 5 on average. Check in with the rider.']);
        }
    }

    /** Buyers' delivery ratings of a rider: average (1 decimal) and how many. */
    public static function riderRating(Rider $rider): array
    {
        $rated = Order::where('delivery_rider_id', $rider->id)->whereNotNull('delivery_rating');

        return ['average' => round((float) (clone $rated)->avg('delivery_rating'), 1), 'count' => $rated->count()];
    }

    public static function hasParcel(Order $order): bool
    {
        return Parcel::withoutGlobalScopes()->where('source', self::SOURCE)->where('external_ref', self::ref($order))->exists();
    }

    public static function orderFor(Parcel $parcel): ?Order
    {
        return self::ordersFor($parcel)->first();
    }

    /** @return Collection<int, Order> every order travelling in the parcel, cancelled ones included */
    public static function ordersFor(Parcel $parcel): Collection
    {
        if ($parcel->source !== self::SOURCE) {
            return collect();
        }
        if (self::isShipmentRef($parcel->external_ref)) {
            return Order::whereIn('order_shipment_id', OrderShipment::where('reference', $parcel->external_ref)->select('id'))->orderBy('id')->get();
        }

        return Order::whereKey((int) $parcel->external_ref)->get();
    }

    /**
     * Approved sellers whose province matches no shipping region: their parcels are priced
     * with the generic (Metro Manila-origin) rates until they fix their address.
     *
     * @return Collection<int, User>
     */
    public static function sellersWithoutShippingRegion(): Collection
    {
        $regions = app(RegionResolver::class);

        return User::where('role', 'seller')->where('status', 'approved')->orderBy('id')
            ->get(['id', 'first_name', 'last_name', 'business_name', 'province', 'municipality'])
            ->reject(fn (User $seller) => $regions->resolve($seller->province))->values();
    }

    /** Whether a seller's province matches a shipping region (route prices apply). */
    public static function hasShippingRegion(?User $seller): bool
    {
        return app(RegionResolver::class)->resolve($seller?->province) !== null;
    }

    public static function shipmentFor(Parcel $parcel): ?OrderShipment
    {
        return $parcel->source === self::SOURCE && self::isShipmentRef($parcel->external_ref)
            ? OrderShipment::where('reference', $parcel->external_ref)->first()
            : null;
    }

    /** The parcel's external_ref for an order: its shipment's reference, or (older orders) its id. */
    public static function ref(Order $order): string
    {
        return $order->order_shipment_id ? $order->shipment->reference : (string) $order->id;
    }

    /**
     * Tracking for marketplace pages: one entry per hub scan, newest first.
     *
     * @return array{tracking_no: string, status: string, events: list<array{headline: string, at: \Carbon\CarbonInterface}>}|null
     */
    public static function tracking(Order $order): ?array
    {
        $parcel = self::parcelFor($order);
        if (! $parcel) {
            return null;
        }
        $parcel->load('events.hub', 'events.toHub');

        return [
            'tracking_no' => $parcel->tracking_no,
            'status' => $parcel->current_status->label(),
            'events' => $parcel->events->reverse()->values()
                ->map(fn ($event) => ['headline' => $event->headline(), 'at' => $event->occurred_at])->all(),
        ];
    }

    /** @return Collection<int, Parcel> parcels keyed by order id, for list pages */
    public static function parcelsFor(iterable $orders): Collection
    {
        $orders = EloquentCollection::make($orders);
        $orders->loadMissing('shipment');
        $refs = $orders->mapWithKeys(fn (Order $order) => [$order->id => self::ref($order)]);
        $parcels = Parcel::withoutGlobalScopes()->where('source', self::SOURCE)->whereIn('external_ref', $refs->unique()->values())
            ->get()->keyBy('external_ref');

        return $refs->map(fn (string $ref) => $parcels->get($ref))->filter();
    }

    /** External refs of every marketplace parcel for a buyer's or seller's orders. */
    private static function refsWhere(string $column, int $userId): Collection
    {
        return Order::where($column, $userId)->whereNull('order_shipment_id')->pluck('id')->map(fn ($id) => (string) $id)
            ->merge(OrderShipment::where($column, $userId)->pluck('reference'));
    }

    private static function isShipmentRef(?string $ref): bool
    {
        return str_starts_with((string) $ref, 'SHP-');
    }

    /** What is in the parcel, for the label and the rider. */
    private static function contents(Collection $orders): string
    {
        return $orders->map(fn (Order $order) => $order->product_name.($order->variation_label ? " ({$order->variation_label})" : '')." × {$order->quantity}")->join('; ');
    }

    /**
     * Cash to collect: the items still in the parcel plus the delivery fee (orders before shipments
     * had none), less what a PickSell voucher covers. Store vouchers are already in the order amounts.
     */
    private static function codAmount(Collection $orders, ?OrderShipment $shipment): string
    {
        $money = app(CommissionService::class);
        $cents = $orders->sum(fn (Order $order) => $money->toCents((string) $order->amount));
        if ($shipment) {
            $cents += $money->toCents((string) $shipment->delivery_fee)
                - $money->toCents((string) ($shipment->platform_discount ?? 0)) - $money->toCents((string) ($shipment->shipping_discount ?? 0));
        }

        return $money->fromCents(max(0, $cents));
    }

    /** Codes saved on the account, or resolved now for accounts created before codes were stored. */
    private function codes(?User $user): array
    {
        if ($user?->municipality_code) {
            return ['municipality_code' => $user->municipality_code, 'barangay_code' => $user->barangay_code];
        }

        return $user ? $this->addresses->resolve($user->province, $user->municipality, $user->barangay)
            : ['municipality_code' => null, 'barangay_code' => null];
    }

    private static function address(?User $user): ?string
    {
        if (! $user) {
            return null;
        }

        return collect([$user->house_no, $user->street, $user->barangay, $user->municipality, $user->province])
            ->filter(fn ($part) => filled($part))->join(', ') ?: null;
    }
}
