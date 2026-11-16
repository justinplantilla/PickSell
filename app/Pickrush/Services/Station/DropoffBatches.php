<?php

namespace App\Pickrush\Services\Station;

use App\Models\User;
use App\Notifications\SellerDropoffReceipt;
use App\Pickrush\Enums\ParcelStatus;
use App\Pickrush\Models\DropoffBatch;
use App\Pickrush\Models\Parcel;
use App\Pickrush\Models\Staff;
use App\Pickrush\Services\OperationRefused;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Bulk drop-off. The seller's parcels for one station go on one manifest (DO…); the station scans
 * it, scans each parcel through the normal receiving scan, then confirms. Parcels left out stay
 * waiting for drop-off until their deadline; the seller's receipt lists both.
 */
class DropoffBatches
{
    /**
     * @param  iterable<Parcel>  $parcels  parcels the seller just set for drop-off
     * @return Collection<int, DropoffBatch> one batch per station
     */
    public function create(User $seller, iterable $parcels): Collection
    {
        return collect($parcels)
            ->filter(fn (Parcel $p) => $p->current_status === ParcelStatus::AwaitingDropoff && $p->origin_hub_id && ! $p->dropoff_batch_id)
            ->groupBy('origin_hub_id')
            ->map(fn (Collection $group, $hubId) => DB::transaction(function () use ($seller, $group, $hubId) {
                $batch = DropoffBatch::create([
                    'batch_no' => 'DO'.now()->format('ymd').strtoupper(Str::random(6)),
                    'origin_hub_id' => $hubId,
                    'seller_id' => $seller->id,
                    'parcel_count' => $group->count(),
                ]);
                Parcel::withoutGlobalScopes()->whereKey($group->pluck('id'))->update(['dropoff_batch_id' => $batch->id]);

                return $batch;
            }))
            ->values();
    }

    /** A scanned DO… manifest of an open batch for this station, if that is what was scanned. */
    public static function scanned(string $code, int $stationId): ?DropoffBatch
    {
        $code = strtoupper(preg_replace('/\s+/', '', $code));

        return str_starts_with($code, 'DO')
            ? DropoffBatch::where('batch_no', $code)->where('origin_hub_id', $stationId)->first()
            : null;
    }

    /** The counter is done: the seller gets a receipt of what came in and what did not. */
    public function confirm(DropoffBatch $batch, Staff $staff): DropoffBatch
    {
        $batch = DB::transaction(function () use ($batch, $staff) {
            $locked = DropoffBatch::whereKey($batch->id)->lockForUpdate()->firstOrFail();
            if ($locked->origin_hub_id !== $staff->hub_id) {
                throw new OperationRefused('This drop-off belongs to another station.');
            }
            if ($locked->status === 'received') {
                throw new OperationRefused("Drop-off {$locked->batch_no} is already confirmed.");
            }
            $parcels = $locked->parcels()->get();
            if (! $parcels->contains(fn (Parcel $p) => DropoffBatch::arrived($p))) {
                throw new OperationRefused('Scan at least one parcel before confirming.');
            }
            $locked->update(['status' => 'received', 'received_by' => $staff->id, 'received_at' => now()]);

            return $locked;
        });

        try {
            $batch->seller?->notify(new SellerDropoffReceipt($batch->load('parcels', 'originHub', 'receiver')));
        } catch (Throwable $e) {
            report($e);
        }

        return $batch;
    }
}
