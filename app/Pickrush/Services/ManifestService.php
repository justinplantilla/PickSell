<?php

namespace App\Pickrush\Services;

use App\Pickrush\Enums\ParcelStatus;
use App\Pickrush\Models\Hub;
use App\Pickrush\Models\Manifest;
use App\Pickrush\Models\ManifestParcel;
use App\Pickrush\Models\Parcel;
use App\Pickrush\Models\Staff;
use App\Pickrush\Support\Actor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Trips between hubs. The sending hub builds a batch of parcels that are due at the same next
 * hub and dispatches it with a vehicle and driver; the receiving hub scans every parcel against
 * the list. Unscanned parcels become "missing" when the trip is closed, unlisted ones "extra".
 * Used by station hubs (to and from their province hub) and province hubs alike.
 */
class ManifestService
{
    public function __construct(private ParcelLedger $ledger) {}

    public function create(Hub $from, Hub $to, Staff $staff): Manifest
    {
        if ($from->is($to)) {
            throw new OperationRefused('A trip must go to another hub.');
        }

        return Manifest::create([
            'manifest_no' => 'MF'.now()->format('ymd').strtoupper(Str::random(6)),
            'from_hub_id' => $from->id,
            'to_hub_id' => $to->id,
            'status' => 'draft',
            'created_by' => $staff->id,
        ]);
    }

    /** Parcels at $from that are due next at $to and not already on an open trip. */
    public function readyFor(Hub $from, Hub $to)
    {
        return Parcel::withoutGlobalScopes()
            ->where('current_hub_id', $from->id)->where('next_hub_id', $to->id)
            ->whereIn('current_status', array_map(fn ($s) => $s->value, [
                ParcelStatus::AtOriginHub, ParcelStatus::AtProvinceHub, ParcelStatus::ArrivedAtStation,
                ParcelStatus::ReturningToSender, ParcelStatus::DeliveryFailed,
            ]))
            ->whereDoesntHave('assignments', fn ($q) => $q->whereIn('status', ['assigned', 'out_for_delivery']))
            ->whereNotIn('id', ManifestParcel::whereHas('manifest', fn ($q) => $q->whereIn('status', ['draft', 'dispatched']))->select('parcel_id'));
    }

    public function add(Manifest $manifest, string $trackingNo): Parcel
    {
        $manifest->refresh();
        if ($manifest->status !== 'draft') {
            throw new OperationRefused('This trip has left; start a new one.');
        }
        $parcel = $this->readyFor($manifest->fromHub, $manifest->toHub)->where('tracking_no', self::normalize($trackingNo))->first()
            ?? throw new OperationRefused($this->whyNotReady($trackingNo, $manifest));

        ManifestParcel::create(['manifest_id' => $manifest->id, 'parcel_id' => $parcel->id]);
        $manifest->update(['parcel_count' => $manifest->items()->count()]);

        return $parcel;
    }

    /** Add every parcel that is due on this trip in one go. */
    public function addAllReady(Manifest $manifest): int
    {
        if ($manifest->status !== 'draft') {
            throw new OperationRefused('This trip has left; start a new one.');
        }
        $ids = $this->readyFor($manifest->fromHub, $manifest->toHub)->pluck('id');
        foreach ($ids as $id) {
            ManifestParcel::create(['manifest_id' => $manifest->id, 'parcel_id' => $id]);
        }
        $manifest->update(['parcel_count' => $manifest->items()->count()]);

        return $ids->count();
    }

    public function remove(Manifest $manifest, ManifestParcel $item): void
    {
        if ($manifest->status !== 'draft' || $item->manifest_id !== $manifest->id) {
            throw new OperationRefused('Only parcels on a trip that has not left can be removed.');
        }
        $item->delete();
        $manifest->update(['parcel_count' => $manifest->items()->count()]);
    }

    /** The trip leaves: every parcel on it goes in transit to the receiving hub. */
    public function dispatch(Manifest $manifest, string $vehiclePlate, string $driverName, Staff $staff): void
    {
        $original = $manifest;
        DB::transaction(function () use ($manifest, $vehiclePlate, $driverName, $staff, $original) {
            $manifest = Manifest::withoutGlobalScopes()->whereKey($manifest->id)->lockForUpdate()->firstOrFail();
            if ($manifest->status !== 'draft') {
                throw new OperationRefused('This trip was already dispatched.');
            }
            if ($manifest->items()->count() === 0) {
                throw new OperationRefused('Add at least one parcel before dispatching.');
            }

            $status = $manifest->toHub->isProvince() ? ParcelStatus::InTransitToProvince : ParcelStatus::InTransitToStation;
            foreach ($manifest->items()->with('parcel')->get() as $item) {
                $this->ledger->record($item->parcel, $status, $manifest->fromHub, [
                    'to_hub' => $manifest->toHub,
                    'meta' => ['manifest' => $manifest->manifest_no, 'vehicle' => $vehiclePlate],
                    'actor' => Actor::current(),
                ]);
            }

            $manifest->update([
                'status' => 'dispatched', 'vehicle_plate' => strtoupper($vehiclePlate), 'driver_name' => $driverName,
                'dispatched_by' => $staff->id, 'dispatched_at' => now(),
            ]);
            $original->setRawAttributes($manifest->getAttributes(), true);
        });
    }

    /**
     * The receiving hub scans a parcel off a trip. A parcel on the list is marked scanned; one that
     * is not listed but is due here anyway is received and recorded as extra.
     */
    public function receive(Manifest $manifest, string $trackingNo, Staff $staff, ?string $idempotencyKey = null): Parcel
    {
        return DB::transaction(function () use ($manifest, $trackingNo, $staff, $idempotencyKey) {
            $manifest = Manifest::withoutGlobalScopes()->whereKey($manifest->id)->lockForUpdate()->firstOrFail();
            if (! in_array($manifest->status, ['dispatched', 'received'], true) || $manifest->to_hub_id !== $staff->hub_id) {
                throw new OperationRefused('This trip is not arriving at your hub.');
            }
            $parcel = Parcel::withoutGlobalScopes()->where('tracking_no', self::normalize($trackingNo))->first()
                ?? throw new OperationRefused('Unknown tracking number.');
            $item = $manifest->items()->where('parcel_id', $parcel->id)->first();

            if ($item?->scan_status === 'scanned') {
                return $parcel;   // scanned twice: nothing new
            }
            $this->arrive($parcel, $manifest->toHub, $idempotencyKey, $manifest->manifest_no);

            $item
                ? $item->update(['scan_status' => 'scanned', 'scanned_at' => now()])
                : ManifestParcel::create(['manifest_id' => $manifest->id, 'parcel_id' => $parcel->id, 'scan_status' => 'extra', 'scanned_at' => now()]);
            if ($manifest->status === 'dispatched') {
                $manifest->update(['status' => 'received', 'received_by' => $staff->id, 'received_at' => now()]);
            }

            return $parcel;
        });
    }

    /** Close the check: anything still unscanned is recorded as missing for follow-up. */
    public function close(Manifest $manifest, Staff $staff): int
    {
        $manifest->refresh();
        if (! in_array($manifest->status, ['dispatched', 'received'], true) || $manifest->to_hub_id !== $staff->hub_id) {
            throw new OperationRefused('Only the receiving hub can close this trip.');
        }
        $missing = $manifest->items()->where('scan_status', 'expected')->update(['scan_status' => 'missing']);
        $manifest->update(['status' => 'closed', 'closed_at' => now(), 'received_by' => $manifest->received_by ?? $staff->id]);

        return $missing;
    }

    /** Arrival scan at a hub for a parcel in transit to it. */
    public function arrive(Parcel $parcel, Hub $hub, ?string $idempotencyKey = null, ?string $manifestNo = null): void
    {
        $inTransit = in_array($parcel->current_status, [ParcelStatus::InTransitToProvince, ParcelStatus::InTransitToStation], true);
        if (! $inTransit || $parcel->next_hub_id !== $hub->id) {
            $where = $parcel->currentHub?->name ?? $parcel->nextHub?->name ?? 'elsewhere';
            throw new OperationRefused("{$parcel->tracking_no} is not on its way to {$hub->name} ({$parcel->current_status->label()}, {$where}).");
        }

        $this->ledger->record($parcel, $hub->isProvince() ? ParcelStatus::AtProvinceHub : ParcelStatus::ArrivedAtStation, $hub, [
            'idempotency_key' => $idempotencyKey,
            'meta' => $manifestNo ? ['manifest' => $manifestNo] : null,
        ]);
    }

    public static function normalize(string $trackingNo): string
    {
        return strtoupper(preg_replace('/\s+/', '', $trackingNo));
    }

    private function whyNotReady(string $trackingNo, Manifest $manifest): string
    {
        $parcel = Parcel::withoutGlobalScopes()->where('tracking_no', self::normalize($trackingNo))->first();
        if (! $parcel) {
            return 'Unknown tracking number.';
        }
        if ($parcel->current_hub_id !== $manifest->from_hub_id) {
            return "{$parcel->tracking_no} is not at {$manifest->fromHub->name}.";
        }
        if ($parcel->next_hub_id !== $manifest->to_hub_id) {
            return "{$parcel->tracking_no} is not due at {$manifest->toHub->name}".($parcel->nextHub ? "; it goes to {$parcel->nextHub->name}." : '.');
        }

        return "{$parcel->tracking_no} is already on a trip or with a rider.";
    }
}
