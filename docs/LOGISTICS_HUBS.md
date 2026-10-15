# Logistics Hub Network

Three-tier delivery hierarchy, modeled after J&T Express:

## Tier 1 — Superadmin / Company Portal
**Domain:** `logistics.pick-sell.shop`

The logistics company account. Creates and manages province hubs and station hubs across the network.

## Tier 2 — Province Hub (Sorting Center)
**Domain:** `hub-<subdomain>.pick-sell.shop` (e.g. `hub-cebu.pick-sell.shop`)

One per province. Handles inter-province linehaul:
- Receives trucks from origin province hub
- Sorts parcels for local station hubs
- Dispatches outbound trucks to destination province hubs

## Tier 3 — Station Hub (Distribution Point)
**Domain:** `station-<subdomain>.pick-sell.shop` (e.g. `station-mandaue.pick-sell.shop`)

One per town/municipality. Handles last-mile:
- Rider picks up parcel from seller
- Parcel is brought to station hub
- Station scans, sorts, and assigns rider for delivery to buyer

## Delivery Flow

### Intra-province (same province)
```
Seller → Rider pickup → Station Hub (origin)
       → Scan & Sort → Assign rider → Buyer
```

### Inter-province
```
Seller → Rider pickup → Station Hub (origin)
       → Scan & Sort → Dispatch linehaul truck
       → Province Hub (origin) → In transit
       → Province Hub (destination) → Receive linehaul
       → Station Hub (destination) → Assign rider → Buyer
```

## Order Status Lifecycle
| Status | Where |
|---|---|
| `ready_for_pickup` | Seller packed, waiting for rider |
| `picked_up` | Rider collected from seller |
| `at_sorting_center` | Scanned at station hub |
| `sorted` | Sorted — local delivery or linehaul |
| `in_transit` | Inter-province truck en route |
| `assigned_to_rider` | Last-mile rider assigned |
| `out_for_delivery` | Rider heading to buyer |
| `delivered` | Delivered to buyer |
| `delivery_failed` | Delivery attempt failed |
| `returned` | Returned to sender |

## Deployment

Set these in `.env`:
```
LOGISTICS_SUPERADMIN_DOMAIN=logistics.pick-sell.shop
LOGISTICS_HUB_DOMAIN=pick-sell.shop          # optional: LOGISTICS_PROVINCE_PREFIX=hub-, LOGISTICS_STATION_PREFIX=station-
SESSION_DOMAIN=.pick-sell.shop
```

Hub portals are single-level subdomains so Cloudflare's free Universal SSL (`*.pick-sell.shop`) covers them; two-level names like `cebu.hub.pick-sell.shop` would need a paid Advanced Certificate. Traffic reaches the app through the Cloudflare Tunnel (`~/.cloudflared/config.yml`), whose `*.pick-sell.shop` ingress rule and the proxied wildcard CNAME `*.pick-sell.shop` route every hub to `127.0.0.1:8000`. Restart `cloudflared tunnel run` after changing its config.

After deploying:
```
php artisan migrate        # adds the in_transit order status and one-operator-per-hub constraint
php artisan route:cache    # or route:clear — a stale route cache hides the hub portals
php artisan config:cache
```

## Accounts & Access
- Each hub has exactly one operator account (`users.assigned_hub_id`, role `logistics`).
  The company creates province hubs + their operators; a province hub creates station hubs + their operators.
- `ResolveHubContext` only admits a hub's own operator on that hub's subdomain. Operators who open the
  company portal (or log in there) are redirected to their own hub portal.
- Couriers register at `logistics.pick-sell.shop/register`. The station hub whose town matches the applicant's
  municipality reviews them under **Rider Applications** (same checklist, audit log and emails as admin reviews);
  approving makes them a rider at that station. Stations assign riders to barangays and can suspend or reinstate them.
- A station's barangays are loaded from the PSGC directory when it is created (retry with **Load barangays** on
  the Riders page). Riders still attached to an old pre-hub branch can be assigned to a station, which releases
  them from the old branch.

## Linehaul Rule
A parcel needs linehaul only when its origin and destination stations belong to **different province hubs**
(`Order::requiresLinehaul()`). Until the destination province hub receives it, no rider can be assigned.
