<?php

namespace App\Pickrush\Services\Hq;

use App\Models\LogisticsClaim;
use App\Models\User;
use App\Pickrush\Enums\ParcelStatus;
use App\Pickrush\Models\Company;
use App\Pickrush\Models\CompanySettlement;
use App\Pickrush\Models\HubRemittance;
use App\Pickrush\Models\Parcel;
use App\Pickrush\Models\Staff;
use App\Pickrush\Services\OperationRefused;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * What a logistics company owes PickSell and how it pays. A delivered PickSell parcel is owed once
 * its cash reached the company's HQ (the station remittance holding it is confirmed or settled):
 * its COD less the rider pay (the rider kept it) and the company's share of the delivery fee.
 * Claims PickSell charged the company are added. The company's HQ finance settles everything owed
 * so far in one deposit, whenever it likes, within the deadline: the oldest cash must reach PickSell
 * within settlement_days of arriving at HQ, and settlement_grace_hours later PickSell stops offering
 * the company at checkout until it settles. A PickSell admin confirms or disputes the deposit; a
 * dispute is topped up or contested by the company, and counts toward the deadline until answered.
 */
class PicksellSettlement
{
    /** Delivered PickSell parcels whose cash is at the company's HQ and not settled yet, oldest cash first. */
    public function owedParcels(int $companyId): Collection
    {
        $cashAtHq = DB::table('pr_assignments')
            ->join('pr_cod_remittances', 'pr_cod_remittances.id', '=', 'pr_assignments.cod_remittance_id')
            ->join('pr_hub_remittances', 'pr_hub_remittances.id', '=', 'pr_cod_remittances.hub_remittance_id')
            ->whereColumn('pr_assignments.parcel_id', 'pr_parcels.id')
            ->where('pr_assignments.type', 'delivery')->where('pr_assignments.status', 'completed')
            ->whereIn('pr_hub_remittances.status', ['confirmed', HubRemittance::SETTLED]);

        return Parcel::withoutGlobalScopes()->where('company_id', $companyId)->where('source', 'picksell')
            ->where('current_status', ParcelStatus::Delivered)->whereNull('company_settlement_id')
            ->whereExists((clone $cashAtHq)->select(DB::raw(1)))
            ->select('pr_parcels.*')->addSelect(['cash_at_hq_at' => (clone $cashAtHq)->selectRaw('max(pr_hub_remittances.confirmed_at)')])
            ->orderBy('cash_at_hq_at')->orderBy('id')->get();
    }

    /** What one delivered parcel owes PickSell. */
    public static function owedFor(Parcel $parcel): float
    {
        return round((float) $parcel->cod_amount - (float) $parcel->rider_pay - (float) $parcel->logistics_share, 2);
    }

    /** Claims PickSell charged the company that no settlement has paid yet. */
    public function owedClaims(int $companyId): Collection
    {
        return LogisticsClaim::where('company_id', $companyId)->where('status', 'charged')->whereNull('company_settlement_id')->oldest()->get();
    }

    /**
     * @return array{parcels: Collection, claims: Collection, parcels_total: float, claims_total: float, total: float}
     */
    public function owed(int $companyId): array
    {
        $parcels = $this->owedParcels($companyId);
        $claims = $this->owedClaims($companyId);
        $parcelsTotal = round($parcels->sum(fn (Parcel $p) => self::owedFor($p)), 2);
        $claimsTotal = round((float) $claims->sum('amount'), 2);

        return ['parcels' => $parcels, 'claims' => $claims, 'parcels_total' => $parcelsTotal, 'claims_total' => $claimsTotal,
            'total' => round($parcelsTotal + $claimsTotal, 2)];
    }

    /**
     * When the company's money is due at PickSell.
     *
     * @return array{state: 'none'|'ok'|'overdue'|'blocked', amount: float, disputes: int, due_at: ?Carbon, block_at: ?Carbon}
     */
    public function deadline(int $companyId): array
    {
        $owed = $this->owed($companyId);
        $disputes = self::unansweredDisputes($companyId);

        $dues = collect();
        if ($first = $owed['parcels']->first()) {
            $dues->push(Carbon::parse($first->cash_at_hq_at)->addDays((int) config('pickrush.policies.settlement_days')));
        }
        foreach ($disputes as $disputed) {
            $dues->push(($disputed->confirmed_at ?? $disputed->updated_at)->copy());
        }
        if ($dues->isEmpty()) {
            return ['state' => 'none', 'amount' => max(0.0, $owed['total']), 'disputes' => 0, 'due_at' => null, 'block_at' => null];
        }

        $due = $dues->sort()->first();
        $block = $due->copy()->addHours((int) config('pickrush.policies.settlement_grace_hours'));

        return [
            'state' => now()->gte($block) ? 'blocked' : (now()->gte($due) ? 'overdue' : 'ok'),
            'amount' => round(max(0.0, $owed['total']) + $disputes->sum(fn (CompanySettlement $d) => $d->shortfall()), 2),
            'disputes' => $disputes->count(),
            'due_at' => $due,
            'block_at' => $block,
        ];
    }

    /** A company past its settlement deadline and grace hours. PickSell's default carrier is never left out of checkout. */
    public function holdsBack(Company $company): bool
    {
        return ! $company->is_default && $this->deadline($company->id)['state'] === 'blocked';
    }

    /** Disputed settlements waiting for the company: not top-ups, and no top-up waiting for PickSell. */
    public static function unansweredDisputes(int $companyId): Collection
    {
        return CompanySettlement::withoutGlobalScopes()->where('company_id', $companyId)->where('status', 'disputed')->whereNull('settles_id')
            ->whereDoesntHave('topUps', fn ($q) => $q->where('status', 'submitted'))->oldest('confirmed_at')->get();
    }

    // The company --------------------------------------------------------------------------------

    /**
     * Settle everything owed so far in one deposit. The amount is the system total, never typed;
     * $shownTotal is the total HQ finance saw, refused if more became owed since.
     */
    public function submit(Staff $finance, string $reference, UploadedFile $slip, ?string $note = null, ?float $shownTotal = null): CompanySettlement
    {
        $companyId = $this->mustSettle($finance);

        return $this->deposit($reference, $slip, function (string $reference, string $key, string $path) use ($finance, $companyId, $note, $shownTotal) {
            $owed = $this->owed($companyId);
            Parcel::withoutGlobalScopes()->whereIn('id', $owed['parcels']->pluck('id'))->lockForUpdate()->get();
            if ($owed['parcels']->isEmpty() && $owed['claims']->isEmpty()) {
                throw new OperationRefused('Nothing is owed to PickSell yet. Parcels count once their cash is confirmed at HQ.');
            }
            if ($owed['total'] <= 0) {
                throw new OperationRefused('PickSell owes you ₱'.number_format(-$owed['total'], 2).' on these parcels; there is nothing to deposit. Contact PickSell.');
            }
            if ($shownTotal !== null && round($shownTotal, 2) !== $owed['total']) {
                throw new OperationRefused('The amount owed changed to ₱'.number_format($owed['total'], 2).'. Check the deposit again and confirm.');
            }

            $settlement = CompanySettlement::create([
                'company_id' => $companyId,
                'parcel_count' => $owed['parcels']->count(),
                'claims_amount' => $owed['claims_total'],
                'amount' => $owed['total'],
                'reference_no' => $reference,
                'reference_key' => $key,
                'slip_path' => $path,
                'submitted_by' => $finance->id,
                'note' => $note,
            ]);
            Parcel::withoutGlobalScopes()->whereIn('id', $owed['parcels']->pluck('id'))->update(['company_settlement_id' => $settlement->id]);
            LogisticsClaim::whereIn('id', $owed['claims']->pluck('id'))->update(['company_settlement_id' => $settlement->id]);

            return $settlement;
        });
    }

    /** Deposit the shortfall of a settlement PickSell disputed. The amount is the system's shortfall. */
    public function topUp(Staff $finance, CompanySettlement $disputed, string $reference, UploadedFile $slip, ?float $shownShortfall = null): CompanySettlement
    {
        $companyId = $this->mustSettle($finance);

        return $this->deposit($reference, $slip, function (string $reference, string $key, string $path) use ($finance, $companyId, $disputed, $shownShortfall) {
            $disputed = $this->disputedOf($disputed, $companyId);
            $shortfall = $disputed->shortfall();
            if ($shortfall <= 0) {
                throw new OperationRefused('PickSell did not find this deposit short. Contest the dispute instead.');
            }
            if ($disputed->pendingTopUp()) {
                throw new OperationRefused('A top-up for this settlement is already waiting for PickSell.');
            }
            if ($shownShortfall !== null && round($shownShortfall, 2) !== $shortfall) {
                throw new OperationRefused('The shortfall is now ₱'.number_format($shortfall, 2).'. Check it and confirm again.');
            }

            return CompanySettlement::create([
                'company_id' => $companyId,
                'settles_id' => $disputed->id,
                'amount' => $shortfall,
                'reference_no' => $reference,
                'reference_key' => $key,
                'slip_path' => $path,
                'submitted_by' => $finance->id,
                'note' => "Top-up for disputed settlement {$disputed->reference_no}",
            ]);
        });
    }

    /** The company says PickSell's dispute is wrong; PickSell checks its bank again. */
    public function contest(Staff $finance, CompanySettlement $disputed, string $explanation, ?UploadedFile $evidence = null): CompanySettlement
    {
        $companyId = $this->mustSettle($finance);

        return DB::transaction(function () use ($companyId, $disputed, $explanation, $evidence) {
            $disputed = $this->disputedOf($disputed, $companyId);
            if ($disputed->pendingTopUp()) {
                throw new OperationRefused('A top-up for this settlement is waiting for PickSell. Wait for PickSell to check it first.');
            }
            $disputed->update([
                'status' => 'submitted',
                'company_response' => $explanation,
                'contest_path' => $evidence?->store('pickrush/settlement-slips', 'local') ?? $disputed->contest_path,
                'contested_at' => now(),
            ]);

            return $disputed;
        });
    }

    // PickSell -----------------------------------------------------------------------------------

    /**
     * A PickSell admin confirms the deposit reached PickSell's bank, or disputes it with what the
     * bank showed and why. A confirmed top-up that covers the shortfall settles its dispute.
     */
    public function confirm(CompanySettlement $settlement, User $admin, bool $matches, ?string $note = null, ?float $bankAmount = null): void
    {
        DB::transaction(function () use ($settlement, $admin, $matches, $note, $bankAmount) {
            $settlement = CompanySettlement::withoutGlobalScopes()->whereKey($settlement->id)->lockForUpdate()->firstOrFail();
            if ($settlement->status !== 'submitted') {
                throw new OperationRefused('This settlement was already '.$settlement->status.'.');
            }
            $bankAmount = $matches ? (float) $settlement->amount : $bankAmount;
            if ($bankAmount === null) {
                throw new OperationRefused('Enter the amount the bank statement shows for this deposit.');
            }
            if (! $matches && blank($note)) {
                throw new OperationRefused('Say what does not match.');
            }
            $settlement->update([
                'status' => $matches ? 'confirmed' : 'disputed',
                'bank_amount' => round($bankAmount, 2),
                'confirmed_by' => $admin->id,
                'confirmed_at' => now(),
                'note' => trim(($settlement->note ? $settlement->note."\n" : '').($note ?? '')) ?: null,
            ]);

            $disputed = $matches ? $settlement->settles : null;
            if ($disputed && $disputed->status === 'disputed' && $disputed->shortfall() <= 0) {
                $disputed->update(['status' => CompanySettlement::SETTLED]);
            }
        });
    }

    // ------------------------------------------------------------------------------------------------

    private function mustSettle(Staff $finance): int
    {
        if (! in_array($finance->role->value, ['super_admin', 'finance'], true) || ! $finance->company_id) {
            throw new OperationRefused('Only your company\'s HQ finance settles with PickSell.');
        }

        return (int) $finance->company_id;
    }

    private function disputedOf(CompanySettlement $disputed, int $companyId): CompanySettlement
    {
        $disputed = CompanySettlement::withoutGlobalScopes()->whereKey($disputed->id)->lockForUpdate()->firstOrFail();
        if ($disputed->company_id !== $companyId) {
            throw new OperationRefused('This settlement belongs to another company.');
        }
        if ($disputed->status !== 'disputed') {
            throw new OperationRefused('PickSell is not disputing this settlement.');
        }

        return $disputed;
    }

    /**
     * A deposit with its slip and a reference no other settlement has used; a refused deposit
     * leaves no slip behind.
     *
     * @param  callable(string, string, string): CompanySettlement  $create  given the reference, its key and the slip path
     */
    private function deposit(string $reference, UploadedFile $slip, callable $create): CompanySettlement
    {
        $reference = trim($reference);
        $key = HubRemittance::referenceKey($reference);
        if ($key === '') {
            throw new OperationRefused('Enter the reference number printed on the deposit slip.');
        }
        $path = $slip->store('pickrush/settlement-slips', 'local');

        try {
            return DB::transaction(function () use ($reference, $key, $path, $create) {
                if ($used = CompanySettlement::withoutGlobalScopes()->where('reference_key', $key)->first()) {
                    throw new OperationRefused("Reference {$reference} was already used for a settlement on {$used->created_at->format('M j, Y')}. Each deposit has its own reference.");
                }

                return $create($reference, $key, $path);
            });
        } catch (UniqueConstraintViolationException) {
            Storage::disk('local')->delete($path);
            throw new OperationRefused("Reference {$reference} was already used for another settlement. Each deposit has its own reference.");
        } catch (\Throwable $e) {
            Storage::disk('local')->delete($path);
            throw $e;
        }
    }
}
