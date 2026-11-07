<?php

namespace App\Pickrush\Services\Province;

use App\Pickrush\Enums\ParcelStatus;
use App\Pickrush\Models\Hub;
use App\Pickrush\Models\Manifest;
use App\Pickrush\Models\Parcel;
use App\Pickrush\Models\ParcelException;
use App\Pickrush\Models\Staff;
use App\Pickrush\Services\ManifestService;
use App\Pickrush\Services\OperationRefused;
use App\Pickrush\Services\ParcelLedger;
use Illuminate\Support\Facades\DB;

/**
 * The sort scan: the system reads where the parcel must go next and tells the sorter which bin.
 * A parcel scanned straight off a truck is received first. A parcel with nowhere to go (address
 * not matched, destination hub inactive) goes to the exception bin and is put on hold.
 */
class Sorting
{
    public const EXCEPTION_BIN = 'EXCEPTION';

    public function __construct(private ManifestService $manifests, private ParcelLedger $ledger) {}

    /** @return array{parcel: Parcel, bin: string, destination: ?Hub, received: bool, exception: bool} */
    public function sort(string $trackingNo, Staff $staff): array
    {
        $hub = $staff->hub;
        $parcel = Parcel::withoutGlobalScopes()->where('tracking_no', ManifestService::normalize($trackingNo))->first()
            ?? throw new OperationRefused('Unknown tracking number. Put it in the exception bin and report it as unlabeled.');

        return DB::transaction(function () use ($parcel, $hub, $staff) {
            $received = false;
            if (in_array($parcel->current_status, [ParcelStatus::InTransitToProvince, ParcelStatus::InTransitToStation], true)) {
                // Ticked off its trip like a scan on the trip page; refuses (with the mis-sort hint) if it was not coming here.
                $trip = Manifest::withoutGlobalScopes()->where('to_hub_id', $hub->id)->whereIn('status', ['dispatched', 'received'])
                    ->whereHas('items', fn ($q) => $q->where('parcel_id', $parcel->id))->first();
                $trip ? $this->manifests->receive($trip, $parcel->tracking_no, $staff) : $this->manifests->arrive($parcel, $hub);
                $parcel->refresh();
                $received = true;
            }
            if ($parcel->current_hub_id !== $hub->id || $parcel->current_status !== ParcelStatus::AtProvinceHub) {
                throw new OperationRefused("{$parcel->tracking_no} is not on hand here ({$parcel->current_status->label()}).");
            }

            $destination = $parcel->nextHub;
            $problem = match (true) {
                $parcel->needs_address_fix || ! $parcel->dest_hub_id => 'The delivery address did not match a station.',
                ! $destination => 'No next hub could be worked out.',
                ! $destination->is_active => "{$destination->name} is not active.",
                default => null,
            };

            if ($problem) {
                $this->ledger->record($parcel, ParcelStatus::OnHold, $hub, ['note' => $problem]);
                if (! ParcelException::withoutGlobalScopes()->where('parcel_id', $parcel->id)->where('status', 'open')->exists()) {
                    ParcelException::create(['hub_id' => $hub->id, 'parcel_id' => $parcel->id, 'type' => 'no_route', 'note' => $problem, 'opened_by' => $staff->id]);
                }

                return ['parcel' => $parcel, 'bin' => self::EXCEPTION_BIN, 'destination' => null, 'received' => $received, 'exception' => true];
            }

            $parcel->forceFill(['sorted_at' => now(), 'sorted_by' => $staff->id])->save();

            return ['parcel' => $parcel, 'bin' => self::binFor($destination), 'destination' => $destination, 'received' => $received, 'exception' => false];
        });
    }

    /** Bin label: the next hub's place name, so it reads at a glance on the floor. */
    public static function binFor(Hub $destination): string
    {
        return mb_strtoupper($destination->isStation()
            ? preg_replace('/ Station(,.*)?$/', '', $destination->name)
            : preg_replace('/ (Province|District) Hub$/', '', $destination->name));
    }
}
