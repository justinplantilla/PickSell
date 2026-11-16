<?php

namespace App\Pickrush\Http\Controllers\Station;

use App\Pickrush\Models\DropoffBatch;
use App\Pickrush\Services\Station\DropoffBatches;
use App\Pickrush\Services\Station\StationReceiving;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * A seller's bulk drop-off at the counter: the manifest shows what to expect, each parcel goes
 * through the normal receiving scan, and confirming sends the seller the receipt.
 */
class DropoffController extends StationController
{
    public function show(DropoffBatch $batch)
    {
        abort_unless($batch->origin_hub_id === $this->station()->id, 404);
        $batch->load('seller', 'receiver', 'parcels');

        return view('pickrush.station.dropoff', [
            'batch' => $batch,
            'received' => $batch->parcels->filter(fn ($p) => DropoffBatch::arrived($p))->count(),
            'scanKey' => (string) Str::uuid(),
        ]);
    }

    /** A parcel from outside the batch is still received if it belongs here, with a note. */
    public function scan(Request $request, DropoffBatch $batch, StationReceiving $receiving)
    {
        abort_unless($batch->origin_hub_id === $this->station()->id, 404);
        $data = $request->validate(['tracking_no' => 'required|string|max:30', 'scan_key' => 'nullable|string|max:80']);
        $inBatch = $batch->parcels()->where('tracking_no', strtoupper(preg_replace('/\s+/', '', $data['tracking_no'])))->exists();

        return $this->attempt(
            fn () => $receiving->scan($data['tracking_no'], $this->staff(), self::scanKey($data)),
            fn (string $message) => $inBatch ? $message : "{$message} Note: it is not on drop-off {$batch->batch_no}.",
        );
    }

    public function confirm(DropoffBatch $batch, DropoffBatches $batches)
    {
        abort_unless($batch->origin_hub_id === $this->station()->id, 404);

        return $this->attempt(
            fn () => $batches->confirm($batch, $this->staff()),
            fn (DropoffBatch $done) => "Drop-off {$done->batch_no} confirmed. The seller has the receipt.",
        );
    }
}
