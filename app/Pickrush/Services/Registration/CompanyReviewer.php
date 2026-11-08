<?php

namespace App\Pickrush\Services\Registration;

use App\Auth\Permission;
use App\Models\User;
use App\Pickrush\Mail\CompanyApplicationDecidedMail;
use App\Pickrush\Models\AuditLog;
use App\Pickrush\Models\Company;
use App\Pickrush\Services\OperationRefused;
use App\Pickrush\Services\Shipping\RateCardCopier;
use App\Pickrush\Support\Actor;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

/**
 * A PickSell administrator's decisions on logistics companies. Approving gives the company its
 * tracking prefix and a rate card copied from Pickrush's; every decision is kept in the review history, audited on both sides and
 * emailed to the company's owner.
 */
class CompanyReviewer
{
    public function __construct(private AuditLogger $audit, private RateCardCopier $rateCards) {}

    public function approve(Company $company, User $admin): void
    {
        $this->decide($company, $admin, 'approved', ['pending'], null, fn () => [
            'status' => 'approved',
            'approved_at' => $company->approved_at ?? now(),
            'tracking_prefix' => $company->tracking_prefix ?? $this->trackingPrefix($company->name),
            'rejection_reason' => null,
        ]);
        $this->rateCards->copy($company->refresh());   // its own prices start as Pickrush's
    }

    public function reject(Company $company, User $admin, string $reason): void
    {
        $this->decide($company, $admin, 'rejected', ['pending'], $reason, fn () => ['status' => 'rejected', 'rejection_reason' => $reason]);
    }

    public function suspend(Company $company, User $admin, string $reason): void
    {
        if ($company->is_default) {
            throw new OperationRefused('The default logistics company cannot be suspended.');
        }
        $this->decide($company, $admin, 'suspended', ['approved'], $reason, fn () => ['status' => 'suspended', 'rejection_reason' => $reason]);
    }

    public function reinstate(Company $company, User $admin): void
    {
        $this->decide($company, $admin, 'reinstated', ['suspended'], null, fn () => ['status' => 'approved', 'rejection_reason' => null]);
    }

    /** Two letters for the company's tracking numbers: its initials when free, else the first free pair. */
    public function trackingPrefix(string $name): string
    {
        $taken = Company::whereNotNull('tracking_prefix')->pluck('tracking_prefix')->map(fn ($p) => strtoupper($p))->flip();
        $words = array_values(array_filter(preg_split('/[^A-Z]+/', strtoupper(Str::ascii($name)))));
        $letters = implode('', $words);

        $candidates = [];
        if (count($words) >= 2) {
            $candidates[] = $words[0][0].$words[1][0];
        }
        if (strlen($letters) >= 2) {
            $candidates[] = substr($letters, 0, 2);
            foreach (str_split(substr($letters, 1)) as $second) {
                $candidates[] = $letters[0].$second;
            }
        }
        foreach ($candidates as $candidate) {
            if (! isset($taken[$candidate])) {
                return $candidate;
            }
        }
        foreach (range('A', 'Z') as $first) {
            foreach (range('A', 'Z') as $second) {
                if (! isset($taken[$first.$second])) {
                    return $first.$second;
                }
            }
        }

        throw new OperationRefused('No tracking prefix is left to give this company.');
    }

    /** @param  list<string>  $from  statuses the decision may be taken from */
    private function decide(Company $company, User $admin, string $decision, array $from, ?string $reason, callable $changes): void
    {
        $before = $company->status;

        DB::transaction(function () use ($company, $admin, $decision, $from, $reason, $changes, $before) {
            $company = Company::lockForUpdate()->findOrFail($company->id);
            if (! in_array($company->status, $from, true)) {
                throw new OperationRefused("This company is {$company->status}; it cannot be {$decision} now.");
            }

            Actor::actingAs(Actor::picksell(), fn () => $company->update($changes()));
            $company->reviews()->create(['decision' => $decision, 'reason' => $reason, 'reviewer_id' => $admin->id]);
            AuditLog::record($company, $decision, ['status' => $before], ['status' => $company->status, 'reason' => $reason], Actor::picksell());
            $this->audit->record("logistics.company.{$decision}", $company,
                ['status' => ['from' => $before, 'to' => $company->status]],
                array_filter(['company' => $company->name, 'reason' => $reason]),
                Permission::LOGISTICS_MANAGE);
        });

        $company->refresh();
        try {
            if ($company->owner) {
                Mail::to($company->owner->email)->send(new CompanyApplicationDecidedMail($company, $decision, $reason));
            }
        } catch (Throwable $e) {
            Log::warning('Logistics company decision email could not be sent.', ['company' => $company->id, 'error' => $e->getMessage()]);
        }
    }
}
