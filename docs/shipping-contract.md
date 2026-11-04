# Shipping contract v1: PickSell ↔ PickRush

PickRush is the only carrier. It owns shipping prices, parcel classes, rider pay and the
province-to-region map (its price levels were taken from Flash Express rates as a reference).
PickSell owns the commission percentages. Both run in this Laravel app on one database, so
"calls" are PHP calls through `App\Pickrush\Bridge\PicksellBridge`, the single seam between
them. If PickRush is ever split into its own service, these become HTTP endpoints under `/v1`
with the same shapes.

Money and weights are 2-decimal strings (`"75.00"`). Never floats.

## 1. Quote: `PicksellBridge::quote()` (future `POST /v1/shipping/quote`)

Request (one seller's items, one parcel):

```json
{
  "buyer_province": "Metro Manila",
  "buyer_city": "Quezon City",
  "seller_province": "Batangas",
  "items": [{ "weight_kg": "1.20", "quantity": 2 }]
}
```

`buyer_city` is used only when `buyer_province` is blank. `seller_province` gives the origin
region: the fee is priced for the (seller region → buyer region) route. If the seller's province
matches no region, or the route has no price of its own, the generic (Metro Manila-origin) price
to the buyer's region is used, and the response says so.

Success:

```json
{
  "ok": true, "origin_region": "LUZON", "origin_resolved": true, "destination_region": "NCR",
  "price_source": "ORIGIN_DESTINATION",
  "chargeable_weight_kg": "2.40", "weight_tier_kg": "3.00", "parcel_class": "STANDARD",
  "delivery_fee": "125.00", "rider_pay": "15.00", "rates_version": "rate:41"
}
```

Chargeable weight is the items' actual weight (box size is not charged). There is no handling fee.
`price_source` is `ORIGIN_DESTINATION` or `GENERIC_FALLBACK`. `rates_version` names the rate rows used.

Failure: `{ "ok": false, "reason": "<code>", "message": "<for the buyer>" }`

| reason | meaning | checkout shows |
|---|---|---|
| `UNKNOWN_PROVINCE` | the buyer's province is not mapped | ask the buyer to fix the address |
| `REQUIRES_QUOTE` | over `max_priced_weight_kg` (50 kg) or no class fits | "Heavy parcel: shipping will be quoted by PickSell" |
| `NO_RATE` | no rate in force for the buyer's region | contact support |
| `INVALID_WEIGHT` | a missing, zero or malformed weight or quantity | "can't be shipped yet" and the item names |
| `SERVICE_UNAVAILABLE` | pricing could not run | try again |

A failed quote never becomes a ₱0 or guessed fee: the order is refused.

## 2. Shipment: `PicksellBridge::book()` (future `POST /v1/shipments`)

At order placement PickSell prices each seller's parcel again on the server (ignoring anything
the client sent), splits the fee and saves an `order_shipments` row. When the seller prints the
waybill, the parcel is booked with:

```json
{
  "source": "picksell", "external_ref": "SHP-7K2Q9XW1AB",
  "cod_amount": "575.00", "item_description": "Canvas Tote × 1",
  "shipping_fee": "75.00", "weight_kg": "1.00", "parcel_class": "STANDARD",
  "origin_region": "NCR", "destination_region": "NCR", "price_source": "ORIGIN_DESTINATION",
  "rider_pay": "15.00", "platform_commission": "12.00", "logistics_share": "48.00"
}
```

plus the addresses and PSGC codes. **Idempotency key:** `external_ref` (the shipment
reference). Booking twice returns the same parcel (unique `source + external_ref`).
`cod_amount` = items still in the parcel + `delivery_fee`. Cancelling one item before pickup
lowers it; cancelling the last item cancels the parcel.

The split, in order: rider pay (class) → balance = fee − rider pay → PickSell commission =
balance × `delivery_commission_rate`, rounded half up to the centavo → logistics share =
the remainder. A balance ≤ 0 refuses the order and alerts admins.

## 3. Events: `ParcelStatusChanged` → `SyncOrderWithParcel` (future webhooks)

| event | parcel status | effect |
|---|---|---|
| `shipment.delivered` | `delivered` | every order in the parcel → delivered; `shipment_earnings` gets rider, logistics and picksell rows **once** (unique `order_shipment_id + party`); the delivery rider's ledger gets the class pay **once** (unique `parcel_id + kind`) |
| `shipment.failed` | `delivery_failed` | orders → delivery_failed; the shipment records `failed_at` and `failure_reason`; **nothing is credited** (rider ₱0) |
| (void) | `returning_to_sender`, `returned_to_sender`, `cancelled` | the shipment is marked returned or cancelled; its split is never credited |

Retry safety: events fire after commit, and every credit is guarded by a unique key, so a
repeated event changes nothing. `php artisan shipments:settle-delivered` (scheduled hourly)
credits any delivered shipment a failed sync missed.

Seller net and item commission are not part of this: they post to `financial_transactions`
when the order is **completed**, as before.

## 4. What happens after a failed delivery

Not decided. `ShipmentSettlement::failed()` marks the extension point. Today the parcel
follows PickRush's own re-attempt and return rules, and no money moves.

## 5. Route prices

Rows in `pr_shipping_rates` / `pr_shipping_overweight_rates` with an `origin_region` are route
prices; rows without one are the generic fallback (equal to the NCR-origin route). Route rows
are generated by `RouteRateGenerator` (`php artisan pickrush:generate-route-rates`) from five
price levels (`pr_shipping_levels`, `pr_shipping_level_fees`) and the symmetric 5 × 5 grid
`pr_shipping_route_levels`. HQ edits both on the Marketplace shipping page; a change ends the
affected rows and adds new dated ones, so placed orders keep their price.
