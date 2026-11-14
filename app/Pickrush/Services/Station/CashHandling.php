<?php

namespace App\Pickrush\Services\Station;

use App\Pickrush\Models\Assignment;
use App\Pickrush\Models\CodRemittance;
use App\Pickrush\Models\Hub;
use App\Pickrush\Models\HubRemittance;
use App\Pickrush\Models\Rider;
use App\Pickrush\Models\RiderBatch;
use App\Pickrush\Models\RiderNotification;
use App\Pickrush\Models\Staff;
use App\Pickrush\Services\OperationRefused;
use App\Pickrush\Services\Rider\RiderBatches;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * The cash chain. The expected amount is always a system total, never typed in: the COD delivered
 * and not yet remitted, less the rider's pay for those deliveries, which they keep out of the
 * cash. The remittance marks that pay settled; pickup and return pay is not taken from COD. Staff normally just confirm the total; a different
 * count is typed only when the cash does not match. A difference between expected and counted stays "disputed" and blocks
 * the rider's next dispatch until the hub manager resolves it. Large counts need a second person.
 *
 * Every hand-over has a second party. The staff count waits for the rider to confirm it in the rider
 * app; a rider who rejects it voids the count and the cash is counted again. The station's deposit
 * to HQ carries the slip and a reference that can be used only once.
 */
class CashHandling
{
    public function __construct(private RiderAvailability $availability, private RiderBatches $batches) {}

    /** What the rider hands over now: COD in hand less the pay they keep. */
    public function expectedFrom(Rider $rider): float
    {
        return $this->breakdown($rider)['to_remit'];
    }

    /**
     * COD in hand, the rider pay kept from it, and what is left to hand over. Only the pay for the
     * deliveries and returns whose cash is being handed over (the buyer's COD, the seller's return
     * fee) is kept (pickups bring in no cash, so their pay is not taken from it), and never more than that cash.
     *
     * @return array{cod: float, pay: float, to_remit: float, earning_ids: array<int, int>}
     */
    public function breakdown(Rider $rider, bool $lock = false): array
    {
        $cod = round($this->availability->codInHand($rider), 2);
        $deliveries = Assignment::where('rider_id', $rider->id)->whereIn('type', ['delivery', 'return'])->where('status', 'completed')
            ->whereNull('cod_remittance_id')->select('id');
        $earnings = DB::table('pr_rider_earnings')->where('rider_id', $rider->id)->whereIn('kind', ['delivery', 'return'])->whereNull('settled_at')
            ->whereIn('assignment_id', $deliveries)
            ->orderBy('earned_at')->orderBy('id')->when($lock, fn ($q) => $q->lockForUpdate())->get(['id', 'amount']);

        $pay = 0.0;
        $ids = [];
        foreach ($earnings as $earning) {
            if ($pay + (float) $earning->amount > $cod + 0.001) {
                break;
            }
            $pay += (float) $earning->amount;
            $ids[] = $earning->id;
        }
        $pay = round($pay, 2);

        return ['cod' => $cod, 'pay' => $pay, 'to_remit' => round($cod - $pay, 2), 'earning_ids' => $ids];
    }

    /**
     * $received null confirms the full expected amount. $shownExpected is the total the staff member
     * saw when confirming; if more cash came in since, the confirmation is refused so it is re-checked.
     */
    public function recordRiderRemittance(Rider $rider, ?float $received, Staff $staff, ?Staff $secondCounter = null, ?string $note = null, ?float $shownExpected = null): CodRemittance
    {
        if ($rider->station_hub_id !== $staff->hub_id) {
            throw new OperationRefused('This rider belongs to another station.');
        }

        return DB::transaction(function () use ($rider, $received, $staff, $secondCounter, $note, $shownExpected) {
            if ($rider->cashCountToConfirm()) {
                throw new OperationRefused("{$rider->full_name} has not confirmed the last count yet. They confirm or reject it in the rider app.");
            }
            $tasks = Assignment::where('rider_id', $rider->id)->whereIn('type', ['delivery', 'return'])->where('status', 'completed')
                ->whereNull('cod_remittance_id')->lockForUpdate()->get();
            $kept = $this->breakdown($rider, lock: true);
            $expected = $kept['to_remit'];
            if ($tasks->isEmpty()) {
                throw new OperationRefused("{$rider->full_name} has no cash to remit.");
            }
            if ($shownExpected !== null && round($shownExpected, 2) !== $expected) {
                throw new OperationRefused("{$rider->full_name}'s total changed to ₱".number_format($expected, 2).'. Check the cash again and confirm.');
            }
            $received ??= $expected;
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
                'rider_pay_kept' => $kept['pay'],
                'received_amount' => $received,
                'variance' => $variance,
                'status' => CodRemittance::AWAITING_RIDER,
                'received_by' => $staff->id,
                'second_counter_id' => $secondCounter?->id,
                'note' => $note,
            ]);
            Assignment::whereIn('id', $tasks->pluck('id'))->update(['cod_remittance_id' => $remittance->id]);
            DB::table('pr_rider_earnings')->whereIn('id', $kept['earning_ids'])->update(['cod_remittance_id' => $remittance->id, 'settled_at' => now()]);

            RiderNotification::send($rider, 'cash_count', 'Confirm the cash count',
                "{$staff->full_name} counted ₱".number_format($received, 2).' from you'.($variance == 0.0 ? '.' : ' (system total ₱'.number_format($expected, 2).').')
                .' Confirm or reject it on the handover screen.', ['cod_remittance_id' => $remittance->id]);

            return $remittance;
        });
    }

    /** The rider agrees with the staff count: it is reconciled, or disputed when it differs from the system total. */
    public function confirmCount(CodRemittance $remittance, Rider $rider): CodRemittance
    {
        return DB::transaction(function () use ($remittance, $rider) {
            $remittance = $this->awaitingFrom($remittance, $rider);
            $remittance->update(['status' => (float) $remittance->variance == 0.0 ? 'reconciled' : 'disputed', 'rider_responded_at' => now()]);

            // The rider's delivery batches may now be finished (cash in, parcels handed back).
            RiderBatch::where('rider_id', $rider->id)->where('type', 'delivery')->whereIn('status', RiderBatch::OPEN)->get()
                ->each(fn (RiderBatch $batch) => $this->batches->sync($batch));

            return $remittance;
        });
    }

    /**
     * The rider says the count is wrong. The count is void: the cash is back with the rider in the
     * system, their pay is no longer settled, and the station counts again with them.
     */
    public function rejectCount(CodRemittance $remittance, Rider $rider, string $reason): CodRemittance
    {
        return DB::transaction(function () use ($remittance, $rider, $reason) {
            $remittance = $this->awaitingFrom($remittance, $rider);
            Assignment::where('cod_remittance_id', $remittance->id)->update(['cod_remittance_id' => null]);
            DB::table('pr_rider_earnings')->where('cod_remittance_id', $remittance->id)->update(['cod_remittance_id' => null, 'settled_at' => null]);
            $remittance->update(['status' => CodRemittance::REJECTED, 'rider_responded_at' => now(), 'rider_note' => $reason]);

            return $remittance;
        });
    }

    private function awaitingFrom(CodRemittance $remittance, Rider $rider): CodRemittance
    {
        $remittance = CodRemittance::withoutGlobalScope('hub')->whereKey($remittance->id)->lockForUpdate()->firstOrFail();
        if ($remittance->rider_id !== $rider->id) {
            throw new OperationRefused('This count is for another rider.');
        }
        if ($remittance->status !== CodRemittance::AWAITING_RIDER) {
            throw new OperationRefused('You already answered this count.');
        }

        return $remittance;
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
        return round((float) CodRemittance::withoutGlobalScope('hub')->where('station_hub_id', $station->id)
            ->where('status', 'reconciled')->whereNull('hub_remittance_id')->sum('received_amount'), 2);
    }

    /**
     * The station's deposit to HQ: the counted cash, the deposit slip, and a reference no other
     * remittance has used. The amount is never typed: the manager confirms the system total.
     * $shownExpected is the total they saw; if more cash was counted since, it is refused so they
     * check again. A bank that received a different amount is caught when HQ enters the bank amount.
     */
    public function submitHubRemittance(Staff $manager, string $reference, UploadedFile $slip, ?string $note = null, ?float $shownExpected = null): HubRemittance
    {
        $this->mustRemit($manager);

        return $this->deposit($reference, $slip, function (string $reference, string $key, string $path) use ($manager, $note, $shownExpected) {
            $riderRemittances = CodRemittance::withoutGlobalScope('hub')->where('station_hub_id', $manager->hub_id)
                ->where('status', 'reconciled')->whereNull('hub_remittance_id')->lockForUpdate()->get();
            if ($riderRemittances->isEmpty()) {
                throw new OperationRefused('There is no reconciled cash to remit. Resolve open variances first.');
            }
            $expected = round((float) $riderRemittances->sum('received_amount'), 2);
            if ($shownExpected !== null && round($shownExpected, 2) !== $expected) {
                throw new OperationRefused('The cash to remit changed to ₱'.number_format($expected, 2).'. Check the deposit again and confirm.');
            }

            $remittance = HubRemittance::create([
                'station_hub_id' => $manager->hub_id,
                'expected_amount' => $expected,
                'remitted_amount' => $expected,
                'variance' => 0,
                'reference_no' => $reference,
                'reference_key' => $key,
                'slip_path' => $path,
                'submitted_by' => $manager->id,
                'note' => $note,
            ]);
            CodRemittance::withoutGlobalScope('hub')->whereIn('id', $riderRemittances->pluck('id'))->update(['hub_remittance_id' => $remittance->id]);

            return $remittance;
        });
    }

    /**
     * HQ disputed a remittance because the bank received less: the station deposits the shortfall.
     * The amount is the system's shortfall, never typed. HQ confirms the top-up like any deposit;
     * once the shortfall is covered the disputed remittance is settled.
     */
    public function topUp(Staff $manager, HubRemittance $disputed, string $reference, UploadedFile $slip, ?float $shownShortfall = null): HubRemittance
    {
        $this->mustRemit($manager);

        return $this->deposit($reference, $slip, function (string $reference, string $key, string $path) use ($manager, $disputed, $shownShortfall) {
            $disputed = $this->disputedAt($disputed, $manager);
            $shortfall = $disputed->shortfall();
            if ($shortfall <= 0) {
                throw new OperationRefused('HQ did not find this deposit short. Contest the dispute instead.');
            }
            if ($disputed->pendingTopUp()) {
                throw new OperationRefused('A top-up for this deposit is already waiting for HQ.');
            }
            if ($shownShortfall !== null && round($shownShortfall, 2) !== $shortfall) {
                throw new OperationRefused('The shortfall is now ₱'.number_format($shortfall, 2).'. Check it and confirm again.');
            }

            return HubRemittance::create([
                'station_hub_id' => $manager->hub_id,
                'settles_id' => $disputed->id,
                'expected_amount' => $shortfall,
                'remitted_amount' => $shortfall,
                'variance' => 0,
                'reference_no' => $reference,
                'reference_key' => $key,
                'slip_path' => $path,
                'submitted_by' => $manager->id,
                'note' => "Top-up for disputed deposit {$disputed->reference_no}",
            ]);
        });
    }

    /**
     * The station disagrees with HQ's dispute (the bank posted late, the wrong account was read):
     * its explanation and evidence go back to HQ, which checks the bank again and confirms or
     * disputes it once more.
     */
    public function contest(Staff $manager, HubRemittance $disputed, string $explanation, ?UploadedFile $evidence = null): HubRemittance
    {
        $this->mustRemit($manager);

        return DB::transaction(function () use ($manager, $disputed, $explanation, $evidence) {
            $disputed = $this->disputedAt($disputed, $manager);
            if ($disputed->pendingTopUp()) {
                throw new OperationRefused('A top-up for this deposit is waiting for HQ. Wait for HQ to check it first.');
            }
            $disputed->update([
                'status' => 'submitted',
                'station_response' => $explanation,
                'contest_path' => $evidence?->store('pickrush/deposit-slips', 'local') ?? $disputed->contest_path,
                'contested_at' => now(),
            ]);

            return $disputed;
        });
    }

    private function mustRemit(Staff $manager): void
    {
        if ($manager->role->value !== 'hub_manager' || ! $manager->hub?->isStation()) {
            throw new OperationRefused('Only the station hub manager remits to the company.');
        }
    }

    private function disputedAt(HubRemittance $disputed, Staff $manager): HubRemittance
    {
        $disputed = HubRemittance::withoutGlobalScopes()->whereKey($disputed->id)->lockForUpdate()->firstOrFail();
        if ($disputed->station_hub_id !== $manager->hub_id) {
            throw new OperationRefused('This deposit belongs to another station.');
        }
        if ($disputed->status !== 'disputed') {
            throw new OperationRefused('HQ is not disputing this deposit.');
        }

        return $disputed;
    }

    /**
     * A deposit with its slip and a reference no other remittance has used. $create makes the
     * remittance inside the transaction; a refused deposit leaves no slip behind.
     *
     * @param  callable(string, string, string): HubRemittance  $create  given the reference, its key and the slip path
     */
    private function deposit(string $reference, UploadedFile $slip, callable $create): HubRemittance
    {
        $reference = trim($reference);
        $key = HubRemittance::referenceKey($reference);
        if ($key === '') {
            throw new OperationRefused('Enter the reference number printed on the deposit slip.');
        }
        $path = $slip->store('pickrush/deposit-slips', 'local');

        try {
            return DB::transaction(function () use ($reference, $key, $path, $create) {
                if ($used = HubRemittance::withoutGlobalScopes()->where('reference_key', $key)->first()) {
                    throw new OperationRefused("Reference {$reference} was already used for a remittance on {$used->created_at->format('M j, Y')}. Each deposit has its own reference.");
                }

                return $create($reference, $key, $path);
            });
        } catch (UniqueConstraintViolationException) {
            // Another remittance took the same reference at the same moment.
            Storage::disk('local')->delete($path);
            throw new OperationRefused("Reference {$reference} was already used for another remittance. Each deposit has its own reference.");
        } catch (\Throwable $e) {
            Storage::disk('local')->delete($path);
            throw $e;
        }
    }
}
