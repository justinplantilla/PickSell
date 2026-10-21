<?php

namespace App\Pickrush\Services\Hq;

use App\Pickrush\Enums\ParcelStatus;
use App\Pickrush\Models\HubRemittance;
use App\Pickrush\Models\Parcel;
use App\Pickrush\Models\Staff;
use App\Pickrush\Services\OperationRefused;
use Carbon\CarbonInterface;

/**
 * Company finance: confirming the cash stations send, and what Pickrush earned and owes.
 * Every parcel is cash on delivery, so delivered COD belongs to the shipper less Pickrush's fee.
 */
class Finance
{
    public function confirm(HubRemittance $remittance, Staff $finance, bool $matches, ?string $note): void
    {
        if ($remittance->status !== 'submitted') {
            throw new OperationRefused('This remittance was already '.$remittance->status.'.');
        }
        if ($remittance->submitted_by === $finance->id) {
            throw new OperationRefused('The person who submitted a remittance cannot confirm it.');
        }
        if (! $matches && blank($note)) {
            throw new OperationRefused('Say what does not match.');
        }
        $remittance->update([
            'status' => $matches ? 'confirmed' : 'disputed',
            'confirmed_by' => $finance->id,
            'confirmed_at' => now(),
            'note' => trim(($remittance->note ? $remittance->note."\n" : '').($note ?? '')) ?: null,
        ]);
    }

    /** Delivered COD and fees in the period, split by shipper source (Picksell or direct). */
    public function settlement(CarbonInterface $from, CarbonInterface $to): array
    {
        $delivered = Parcel::withoutGlobalScopes()->where('current_status', ParcelStatus::Delivered)
            ->whereBetween('delivered_at', [$from, $to])
            ->selectRaw('source, count(*) as parcels, sum(cod_amount) as cod, sum(shipping_fee) as fees')
            ->groupBy('source')->get()->keyBy('source');

        return collect(['picksell', 'direct'])->mapWithKeys(fn ($source) => [$source => [
            'parcels' => (int) ($delivered[$source]->parcels ?? 0),
            'cod' => (float) ($delivered[$source]->cod ?? 0),
            'fees' => (float) ($delivered[$source]->fees ?? 0),
            'owed' => (float) ($delivered[$source]->cod ?? 0) - (float) ($delivered[$source]->fees ?? 0),
        ]])->all();
    }
}
