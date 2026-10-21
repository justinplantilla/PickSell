<?php

namespace App\Pickrush\Services\Station;

use App\Pickrush\Enums\ParcelStatus;
use App\Pickrush\Models\Assignment;
use App\Pickrush\Models\Manifest;
use App\Pickrush\Models\Parcel;
use App\Pickrush\Models\ParcelException;
use App\Pickrush\Models\Staff;
use App\Pickrush\Services\ManifestService;
use App\Pickrush\Services\OperationRefused;
use App\Pickrush\Services\ParcelLedger;
use Illuminate\Support\Facades\DB;

/**
 * The station's receiving scan. One scan box handles the three ways a parcel walks in:
 * a seller dropping it off, a rider bringing in a pickup, and a trip from the province hub.
 */
class StationReceiving
{
    public function __construct(private ParcelLedger $ledger, private ManifestService $manifests) {}

    /** @return string what happened, for the scan screen */
    public function scan(string $trackingNo, Staff $staff, ?string $idempotencyKey = null): string
    {
        $station = $staff->hub;
        $parcel = Parcel::withoutGlobalScopes()->where('tracking_no', ManifestService::normalize($trackingNo))->first()
            ?? throw new OperationRefused('Unknown tracking number. Check the waybill.');

        // First mile: seller walk-in, or the pickup rider bringing it in.
        if (in_array($parcel->current_status, [ParcelStatus::WaitingPickup, ParcelStatus::PickedUp], true)) {
            if ($parcel->origin_hub_id !== $station->id) {
                throw new OperationRefused("{$parcel->tracking_no} should be dropped off at {$parcel->originHub?->name}, not here.");
            }
            DB::transaction(function () use ($parcel, $station, $idempotencyKey) {
                $walkIn = $parcel->current_status === ParcelStatus::WaitingPickup;
                $this->ledger->record($parcel, ParcelStatus::AtOriginHub, $station, [
                    'idempotency_key' => $idempotencyKey,
                    'note' => $walkIn ? 'Seller drop-off at the station' : null,
                ]);
                // A walk-in makes any open pickup task unnecessary.
                Assignment::where('parcel_id', $parcel->id)->where('type', 'pickup')->where('status', 'assigned')
                    ->update(['status' => 'cancelled', 'fail_note' => 'Seller dropped the parcel off']);

                // No destination station: it must not leave until the address is fixed.
                if (! $parcel->dest_hub_id) {
                    $this->ledger->record($parcel, ParcelStatus::OnHold, $station, ['note' => 'Delivery address did not match a station']);
                    ParcelException::create(['hub_id' => $station->id, 'parcel_id' => $parcel->id, 'type' => 'no_route',
                        'note' => 'The delivery address did not match a station. Confirm it with the buyer.']);
                }
            });

            if (! $parcel->dest_hub_id) {
                return "{$parcel->tracking_no} received and put on hold: the delivery address needs fixing (see Exceptions).";
            }

            return $parcel->dest_hub_id === $station->id
                ? "{$parcel->tracking_no} received. Same-city parcel: it is in the dispatch queue."
                : "{$parcel->tracking_no} received. Put it on the next trip to {$parcel->nextHub?->name}.";
        }

        // Last mile: arriving from the province hub, with or without the trip list at hand.
        $manifest = Manifest::withoutGlobalScopes()->where('to_hub_id', $station->id)->whereIn('status', ['dispatched', 'received'])
            ->whereHas('items', fn ($q) => $q->where('parcel_id', $parcel->id))->first();
        $manifest
            ? $this->manifests->receive($manifest, $parcel->tracking_no, $staff, $idempotencyKey)
            : $this->manifests->arrive($parcel, $station, $idempotencyKey);

        return "{$parcel->tracking_no} arrived".($manifest ? " (trip {$manifest->manifest_no})" : '').'. It is in the dispatch queue.';
    }
}
