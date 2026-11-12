<?php

namespace App\Pickrush\Services\Hq;

use App\Pickrush\Enums\ParcelStatus;
use App\Pickrush\Models\HubRemittance;
use App\Pickrush\Models\Parcel;
use App\Pickrush\Models\Staff;
use App\Pickrush\Services\OperationRefused;
use App\Pickrush\Support\ParcelHubScope;
use Carbon\CarbonInterface;

/**
 * Company finance: confirming the cash stations send, and what Pickrush earned and owes.
 * Every parcel is cash on delivery, so delivered COD belongs to the shipper less Pickrush's fee.
 */
class Finance
{
    /**
     * Confirming records that the bank received what the station declared. Disputing needs
     * $bankAmount, what the bank statement shows for the deposit reference: the station tops up
     * the difference.
     */
    public function confirm(HubRemittance $remittance, Staff $finance, bool $matches, ?string $note, ?float $bankAmount = null): void
    {
        $bankAmount = $matches ? (float) $remittance->remitted_amount : $bankAmount;
        if ($bankAmount === null) {
            throw new OperationRefused('Enter the amount the bank statement shows for this deposit.');
        }
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
            'bank_amount' => round($bankAmount, 2),
            'confirmed_by' => $finance->id,
            'confirmed_at' => now(),
            'note' => trim(($remittance->note ? $remittance->note."\n" : '').($note ?? '')) ?: null,
        ]);

        // A confirmed top-up that covers the shortfall settles the disputed remittance it was for.
        $disputed = $matches ? $remittance->settles : null;
        if ($disputed && $disputed->status === 'disputed' && $disputed->shortfall() <= 0) {
            $disputed->update(['status' => HubRemittance::SETTLED]);
        }
    }

    /** Delivered COD and fees in the period, split by shipper source (Picksell or direct). */
    public function settlement(CarbonInterface $from, CarbonInterface $to): array
    {
        $delivered = Parcel::withoutGlobalScope(ParcelHubScope::class)->where('current_status', ParcelStatus::Delivered)
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
