<?php

namespace App\Pickrush\Services;

use App\Pickrush\Enums\ParcelStatus;
use App\Pickrush\Models\Driver;
use App\Pickrush\Models\Hub;
use App\Pickrush\Models\Manifest;
use App\Pickrush\Models\ManifestParcel;
use App\Pickrush\Models\Parcel;
use App\Pickrush\Models\Route;
use App\Pickrush\Models\ParcelException;
use App\Pickrush\Models\Staff;
use App\Pickrush\Models\Vehicle;
use App\Pickrush\Services\Station\DispatchService;
use App\Pickrush\Support\Actor;
use App\Pickrush\Support\ParcelHubScope;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Trips between hubs. The sending hub builds a batch of parcels that are due at the same next
 * hub and dispatches it with a vehicle and driver; the receiving hub scans every parcel against
 * the list. The trip closes by itself once every listed parcel is scanned; closed early, the
 * unscanned ones become "missing". Unlisted parcels scanned off it are "extra".
 * The receiving hub records when the truck arrived (its button, or the first parcel scanned off
 * it); a trip not arrived by its due time is overdue.
 * Used by station hubs (to and from their province hub) and province hubs alike.
 * A trip can also be one stop of a province hub's multi-stop route (RouteService): it leaves with
 * the route, and the route ends once its last stop has arrived.
 */
class ManifestService
{
    public function __construct(private ParcelLedger $ledger, private HubRouter $router) {}

    public function create(Hub $from, Hub $to, Staff $staff): Manifest
    {
        if ($from->is($to)) {
            throw new OperationRefused('A trip must go to another hub.');
        }
        // Between provinces, trips only run to a neighboring province hub; parcels relay from there.
        if ($from->isProvince() && $to->isProvince() && ! $this->router->linked($from, $to)) {
            throw new OperationRefused("{$to->name} is not a neighboring province hub. Trips go to the next hub on the route.");
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
        return Parcel::withoutGlobalScope(ParcelHubScope::class)
            ->where('current_hub_id', $from->id)->where('next_hub_id', $to->id)
            ->whereIn('current_status', array_map(fn ($s) => $s->value, [
                ParcelStatus::AtOriginHub, ParcelStatus::AtProvinceHub, ParcelStatus::ArrivedAtStation,
                ParcelStatus::ReturningToSender, ParcelStatus::DeliveryFailed,
            ]))
            ->whereDoesntHave('assignments', fn ($q) => $q->whereIn('status', ['assigned', 'out_for_delivery']))
            ->whereDoesntHave('assignments', fn ($q) => DispatchService::stillWithRider($q))   // still on the rider's vehicle
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

    /**
     * Drop a trip that has not left. Nothing moved yet, so its parcels simply go back to "ready to
     * leave" for the next trip. The audit log keeps the record.
     */
    public function cancel(Manifest $manifest, Staff $staff): int
    {
        return DB::transaction(function () use ($manifest, $staff) {
            $manifest = Manifest::withoutGlobalScope('hub')->whereKey($manifest->id)->lockForUpdate()->firstOrFail();
            if ($manifest->status !== 'draft' || $manifest->from_hub_id !== $staff->hub_id) {
                throw new OperationRefused('Only a trip still being loaded can be cancelled, by the hub sending it.');
            }
            if ($manifest->route_id) {
                throw new OperationRefused('This trip is a stop on a route; remove the stop from the route instead.');
            }
            $count = $manifest->items()->count();
            $manifest->delete();   // its parcel list goes with it

            return $count;
        });
    }

    /**
     * The trip leaves with one of the sending hub's registered trucks and drivers: every parcel on
     * it goes in transit to the receiving hub. A truck or driver still out on a trip that has not
     * been received yet cannot take another. A truck without its OR/CR, or with an expired OR, stays.
     */
    public function dispatch(Manifest $manifest, Vehicle $vehicle, Driver $driver, Staff $staff): void
    {
        $original = $manifest;
        DB::transaction(function () use ($manifest, $vehicle, $driver, $staff, $original) {
            $manifest = Manifest::withoutGlobalScope('hub')->whereKey($manifest->id)->lockForUpdate()->firstOrFail();
            if ($manifest->route_id) {
                throw new OperationRefused('This trip is a stop on a route; it leaves when the route is dispatched.');
            }
            if ($manifest->status !== 'draft') {
                throw new OperationRefused('This trip was already dispatched.');
            }
            if ($manifest->items()->count() === 0) {
                throw new OperationRefused('Add at least one parcel before dispatching.');
            }
            $this->assertCanDrive($manifest->fromHub, $vehicle, $driver);
            $this->send($manifest, $vehicle, $driver, $staff, Manifest::tripHours($manifest->fromHub, $manifest->toHub));
            $original->setRawAttributes($manifest->getAttributes(), true);
        });
    }

    /**
     * The truck and driver can take a trip from $from: the hub's own, active, papers and license in
     * order, and not already out on the road.
     */
    public function assertCanDrive(Hub $from, Vehicle $vehicle, Driver $driver): void
    {
        if ($vehicle->hub_id !== $from->id || $driver->hub_id !== $from->id) {
            throw new OperationRefused('Only this hub\'s own trucks and drivers can take its trips.');
        }
        if (! $vehicle->is_active) {
            throw new OperationRefused("{$vehicle->plate_no} is inactive.");
        }
        if (! $driver->is_active) {
            throw new OperationRefused("{$driver->full_name} is inactive.");
        }
        if ($driver->isLicenseExpired()) {
            throw new OperationRefused("{$driver->full_name}'s license expired on {$driver->license_expiry->format('M d, Y')}. Renew it under Trucks & drivers.");
        }
        if (! $vehicle->hasPapers()) {
            throw new OperationRefused("{$vehicle->plate_no} has no OR/CR on file. Add its papers under Trucks & drivers.");
        }
        if ($vehicle->isRegistrationExpired()) {
            throw new OperationRefused("{$vehicle->plate_no}'s registration (OR) expired on {$vehicle->registration_expiry->format('M d, Y')}. Renew it under Trucks & drivers.");
        }
        $out = Manifest::fleetOnTheRoad();
        if ($out->contains('vehicle_id', $vehicle->id)) {
            throw new OperationRefused("{$vehicle->plate_no} is still out on a trip.");
        }
        if ($out->contains('driver_id', $driver->id)) {
            throw new OperationRefused("{$driver->full_name} is still out on a trip.");
        }
    }

    /**
     * Put every parcel on the trip in transit and mark it dispatched, due in $hours. The caller has
     * locked the trip and checked the truck and driver.
     */
    public function send(Manifest $manifest, Vehicle $vehicle, Driver $driver, Staff $staff, int $hours): void
    {
        $status = $manifest->toHub->isProvince() ? ParcelStatus::InTransitToProvince : ParcelStatus::InTransitToStation;
        foreach ($manifest->items()->with('parcel')->get() as $item) {
            $this->ledger->record($item->parcel, $status, $manifest->fromHub, [
                'to_hub' => $manifest->toHub,
                'meta' => ['manifest' => $manifest->manifest_no, 'vehicle' => $vehicle->plate_no, 'driver' => $driver->full_name],
                'actor' => Actor::current(),
            ]);
        }

        $manifest->update([
            'status' => 'dispatched',
            'vehicle_id' => $vehicle->id, 'vehicle_plate' => $vehicle->plate_no,
            'driver_id' => $driver->id, 'driver_name' => $driver->full_name,
            'dispatched_by' => $staff->id, 'dispatched_at' => now(),
            'due_at' => now()->addHours($hours),
        ]);
    }

    /** The receiving hub records the truck at its gate, which stops the trip from going overdue. */
    public function truckArrived(Manifest $manifest, Staff $staff): void
    {
        DB::transaction(function () use ($manifest, $staff) {
            $manifest = Manifest::withoutGlobalScope('hub')->whereKey($manifest->id)->lockForUpdate()->firstOrFail();
            if ($manifest->status !== 'dispatched' || $manifest->to_hub_id !== $staff->hub_id) {
                throw new OperationRefused('This trip is not arriving at your hub.');
            }
            if ($manifest->arrived_at) {
                throw new OperationRefused('This truck was already marked arrived.');
            }
            $manifest->update(['arrived_at' => now(), 'arrived_by' => $staff->id]);
            $this->stopArrived($manifest);
        });
    }

    /**
     * The receiving hub scans a parcel off a trip. A parcel on the list is marked scanned; one that
     * is not listed but is due here anyway is received and recorded as extra.
     */
    public function receive(Manifest $manifest, string $trackingNo, Staff $staff, ?string $idempotencyKey = null): Parcel
    {
        return DB::transaction(function () use ($manifest, $trackingNo, $staff, $idempotencyKey) {
            $manifest = Manifest::withoutGlobalScope('hub')->whereKey($manifest->id)->lockForUpdate()->firstOrFail();
            if (! in_array($manifest->status, ['dispatched', 'received'], true) || $manifest->to_hub_id !== $staff->hub_id) {
                throw new OperationRefused('This trip is not arriving at your hub.');
            }
            if ($manifest->arrived_at === null) {
                $manifest->update(['arrived_at' => now(), 'arrived_by' => $staff->id]);   // first scan off the truck
                $this->stopArrived($manifest);
            }
            $parcel = Parcel::withoutGlobalScope(ParcelHubScope::class)->where('tracking_no', self::normalize($trackingNo))->first()
                ?? throw new OperationRefused('Unknown tracking number.');
            $item = $manifest->items()->where('parcel_id', $parcel->id)->first();

            if ($item?->scan_status === 'scanned') {
                return $parcel;   // scanned twice: nothing new
            }
            $this->arrive($parcel, $manifest->toHub, $idempotencyKey, $manifest->manifest_no);

            $item
                ? $item->update(['scan_status' => 'scanned', 'scanned_at' => now(), 'scanned_by' => $staff->id])
                : ManifestParcel::create(['manifest_id' => $manifest->id, 'parcel_id' => $parcel->id, 'scan_status' => 'extra', 'scanned_at' => now(), 'scanned_by' => $staff->id]);
            if ($manifest->status === 'dispatched') {
                $manifest->update(['status' => 'received', 'received_by' => $staff->id, 'received_at' => now()]);
            }
            // Every listed parcel is in: nothing is left to check, so the trip closes itself.
            if (! $manifest->items()->where('scan_status', 'expected')->exists()) {
                $manifest->update(['status' => 'closed', 'closed_at' => now()]);
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
        return DB::transaction(function () use ($manifest, $staff) {
            $missing = $manifest->items()->where('scan_status', 'expected')->get();
            foreach ($missing as $item) {
                $item->update(['scan_status' => 'missing']);
                // The hub that cannot produce a scan answers for the parcel.
                ParcelException::create([
                    'hub_id' => $manifest->to_hub_id,
                    'against_hub_id' => $manifest->from_hub_id,
                    'parcel_id' => $item->parcel_id,
                    'manifest_id' => $manifest->id,
                    'type' => 'missing',
                    'note' => "Listed on trip {$manifest->manifest_no} but not scanned on arrival.",
                    'opened_by' => $staff->id,
                ]);
            }
            $manifest->update(['status' => 'closed', 'closed_at' => now(), 'received_by' => $manifest->received_by ?? $staff->id,
                'arrived_at' => $manifest->arrived_at ?? now(), 'arrived_by' => $manifest->arrived_by ?? $staff->id]);
            $this->stopArrived($manifest);

            return $missing->count();
        });
    }

    /** A stop on a route has arrived: once every stop has, the truck and driver are free again. */
    private function stopArrived(Manifest $manifest): void
    {
        if (! $manifest->route_id) {
            return;
        }
        $route = Route::withoutGlobalScopes()->whereKey($manifest->route_id)->lockForUpdate()->first();
        if ($route?->status === 'dispatched' && ! $route->stops()->whereNull('arrived_at')->exists()) {
            $route->update(['status' => 'completed', 'completed_at' => now()]);
        }
    }

    /** Arrival scan at a hub for a parcel in transit to it. */
    public function arrive(Parcel $parcel, Hub $hub, ?string $idempotencyKey = null, ?string $manifestNo = null): void
    {
        $inTransit = in_array($parcel->current_status, [ParcelStatus::InTransitToProvince, ParcelStatus::InTransitToStation], true);
        if (! $inTransit || $parcel->next_hub_id !== $hub->id) {
            $where = $parcel->currentHub?->name ?? $parcel->nextHub?->name ?? 'elsewhere';
            throw new OperationRefused("{$parcel->tracking_no} is not on its way to {$hub->name} ({$parcel->current_status->label()}, {$where})."
                .($inTransit ? ' If it is physically here, report it as mis-sorted.' : ''));
        }

        $this->ledger->record($parcel, $hub->isProvince() ? ParcelStatus::AtProvinceHub : ParcelStatus::ArrivedAtStation, $hub, [
            'idempotency_key' => $idempotencyKey,
            'meta' => $manifestNo ? ['manifest' => $manifestNo] : null,
        ]);

        // A parcel reported missing has turned up.
        ParcelException::withoutGlobalScope('hub')->where('parcel_id', $parcel->id)->where('type', 'missing')->where('status', 'open')
            ->update(['status' => 'resolved', 'resolution' => 'found', 'resolution_note' => "Scanned in at {$hub->name}.", 'resolved_at' => now()]);
    }

    public static function normalize(string $trackingNo): string
    {
        return strtoupper(preg_replace('/\s+/', '', $trackingNo));
    }

    private function whyNotReady(string $trackingNo, Manifest $manifest): string
    {
        $parcel = Parcel::withoutGlobalScope(ParcelHubScope::class)->where('tracking_no', self::normalize($trackingNo))->first();
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
