<?php

namespace App\Pickrush\Services\Station;

use App\Pickrush\Models\Assignment;
use App\Pickrush\Models\CodRemittance;
use App\Pickrush\Models\Hub;
use App\Pickrush\Models\HubRemittance;
use App\Pickrush\Models\Rider;
use App\Pickrush\Models\RiderBatch;
use App\Pickrush\Models\Staff;
use App\Pickrush\Services\OperationRefused;
use App\Pickrush\Services\Rider\RiderBatches;
use Illuminate\Support\Facades\DB;

/**
 * The cash chain. The expected amount is always the system total of COD delivered and not yet
 * remitted, never typed in. A difference between expected and counted stays "disputed" and blocks
 * the rider's next dispatch until the hub manager resolves it. Large counts need a second person.
 */
class CashHandling
{
    public function __construct(private RiderAvailability $availability, private RiderBatches $batches) {}

    public function expectedFrom(Rider $rider): float
    {
        return round($this->availability->codInHand($rider), 2);
    }

    public function recordRiderRemittance(Rider $rider, float $received, Staff $staff, ?Staff $secondCounter = null, ?string $note = null): CodRemittance
    {
        if ($rider->station_hub_id !== $staff->hub_id) {
            throw new OperationRefused('This rider belongs to another station.');
        }

        return DB::transaction(function () use ($rider, $received, $staff, $secondCounter, $note) {
            $tasks = Assignment::where('rider_id', $rider->id)->where('type', 'delivery')->where('status', 'completed')
                ->whereNull('cod_remittance_id')->lockForUpdate()->get();
            $expected = round((float) $tasks->sum('cod_collected'), 2);
            if ($tasks->isEmpty()) {
                throw new OperationRefused("{$rider->full_name} has no cash to remit.");
            }
            $threshold = (float) config('pickrush.policies.two_person_count_above');
            if (max($expected, $received) > $threshold && (! $secondCounter || $secondCounter->is($staff) || $secondCounter->hub_id !== $staff->hub_id)) {
                throw new OperationRefused('Amounts above ₱'.number_format($threshold).' need a second staff member from this station to count.');
            }

            $variance = round($received - $expected, 2);
            $remittance = CodRemittance::create([
                'rider_id' => $rider->id,
                'station_hub_id' => $rider->station_hub_id,
                'remit_date' => today(),
                'expected_amount' => $expected,
                'received_amount' => $received,
                'variance' => $variance,
                'status' => $variance == 0.0 ? 'reconciled' : 'disputed',
                'received_by' => $staff->id,
                'second_counter_id' => $secondCounter?->id,
                'note' => $note,
            ]);
            Assignment::whereIn('id', $tasks->pluck('id'))->update(['cod_remittance_id' => $remittance->id]);

            // The rider's delivery batches may now be finished (cash in, parcels handed back).
            RiderBatch::where('rider_id', $rider->id)->where('type', 'delivery')->whereIn('status', RiderBatch::OPEN)->get()
                ->each(fn (RiderBatch $batch) => $this->batches->sync($batch));

            return $remittance;
        });
    }

    /** The hub manager settles a shortage or overage (deducted, found, written off) with a note. */
    public function resolve(CodRemittance $remittance, Staff $manager, string $resolution): void
    {
        if ($remittance->status !== 'disputed') {
            throw new OperationRefused('This remittance has no open variance.');
        }
        if ($remittance->station_hub_id !== $manager->hub_id || $manager->role->value !== 'hub_manager') {
            throw new OperationRefused('Only this station\'s hub manager can resolve a variance.');
        }
        $remittance->update(['status' => 'reconciled', 'resolved_by' => $manager->id, 'resolved_at' => now(), 'resolution_note' => $resolution]);
    }

    /** Cash the station holds for the company: counted rider remittances not yet sent on. */
    public function stationCashOnHand(Hub $station): float
    {
        return round((float) CodRemittance::withoutGlobalScopes()->where('station_hub_id', $station->id)
            ->where('status', 'reconciled')->whereNull('hub_remittance_id')->sum('received_amount'), 2);
    }

    public function submitHubRemittance(Staff $manager, float $amount, string $reference, ?string $note = null): HubRemittance
    {
        if ($manager->role->value !== 'hub_manager' || ! $manager->hub?->isStation()) {
            throw new OperationRefused('Only the station hub manager remits to the company.');
        }

        return DB::transaction(function () use ($manager, $amount, $reference, $note) {
            $riderRemittances = CodRemittance::withoutGlobalScopes()->where('station_hub_id', $manager->hub_id)
                ->where('status', 'reconciled')->whereNull('hub_remittance_id')->lockForUpdate()->get();
            if ($riderRemittances->isEmpty()) {
                throw new OperationRefused('There is no reconciled cash to remit. Resolve open variances first.');
            }
            $expected = round((float) $riderRemittances->sum('received_amount'), 2);

            $remittance = HubRemittance::create([
                'station_hub_id' => $manager->hub_id,
                'expected_amount' => $expected,
                'remitted_amount' => $amount,
                'variance' => round($amount - $expected, 2),
                'reference_no' => $reference,
                'submitted_by' => $manager->id,
                'note' => $note,
            ]);
            CodRemittance::withoutGlobalScopes()->whereIn('id', $riderRemittances->pluck('id'))->update(['hub_remittance_id' => $remittance->id]);

            return $remittance;
        });
    }
}
