<?php

namespace App\Pickrush\Services;

use App\Pickrush\Enums\ParcelStatus;
use App\Pickrush\Models\Parcel;
use App\Pickrush\Support\Actor;
use Illuminate\Support\Facades\DB;

/**
 * Books a new parcel: resolves the origin and destination station hubs from the PSGC
 * municipality codes and opens the event log with "waiting for pickup". An address that does
 * not resolve to a station is flagged for the "Fix address" queue instead of being dropped.
 */
class ParcelRegistrar
{
    public function __construct(
        private HubRouter $router,
        private TrackingNumber $trackingNumbers,
        private ParcelLedger $ledger,
    ) {}

    /** @param  array<string, mixed>  $data  pr_parcels columns (shipper, consignee, addresses, codes, payment) */
    public function register(array $data, ?Actor $actor = null): Parcel
    {
        return DB::transaction(function () use ($data, $actor) {
            $origin = $this->router->stationFor($data['pickup_municipality_code'] ?? null);
            $dest = $this->router->stationFor($data['delivery_municipality_code'] ?? null);

            $parcel = Parcel::create($data + [
                'tracking_no' => $this->trackingNumbers->generate(),
                'origin_hub_id' => $origin?->id,
                'dest_hub_id' => $dest?->id,
                'next_hub_id' => $origin?->id,
                'needs_address_fix' => ! $origin || ! $dest,
            ]);

            $this->ledger->record($parcel, ParcelStatus::WaitingPickup, null, ['actor' => $actor]);

            return $parcel->refresh();
        });
    }
}
