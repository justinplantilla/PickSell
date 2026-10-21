<?php

namespace App\Pickrush\Services\Station;

use App\Pickrush\Enums\StaffRole;
use App\Pickrush\Models\Rider;
use App\Pickrush\Models\Staff;
use App\Pickrush\Services\OperationRefused;
use Illuminate\Support\Facades\DB;

/**
 * Riders apply in the rider app (RiderApplication) and land in their chosen station as pending. The
 * station hub manager reviews the documents, interviews the rider if needed, then approves or
 * rejects. New riders start on probation with lower limits.
 */
class RiderOnboarding
{
    /** The driver's license doubles as the government ID; every rider vehicle needs its OR/CR. */
    public static function requiredDocuments(Rider $rider): array
    {
        return ['drivers_license', 'or_cr'];
    }

    /** The manager checks identity, licence validity and that the OR/CR plate matches, then activates. */
    public function approve(Rider $rider, Staff $manager): void
    {
        $this->requireManager($manager);
        if (! $rider->isApplication()) {
            throw new OperationRefused('Only a pending application can be approved.');
        }
        $missing = array_diff(self::requiredDocuments($rider), $rider->documents()->pluck('doc_type')->all());
        if ($missing) {
            $labels = array_map(fn ($type) => config("pickrush.rider_documents.{$type}"), $missing);
            throw new OperationRefused('Missing documents: '.implode(', ', $labels).'.');
        }
        if ($rider->documents()->whereNotNull('expiry_date')->where('expiry_date', '<', today())->exists()) {
            throw new OperationRefused('A document has expired. The rider must resubmit a current one.');
        }

        $rider->update([
            'status' => 'active', 'status_reason' => null,
            'verified_by' => $manager->id, 'verified_at' => now(),
            'approved_by' => $manager->id, 'approved_at' => now(),
        ]);
    }

    public function reject(Rider $rider, Staff $manager, string $reason): void
    {
        $this->requireManager($manager);
        if (! $rider->isApplication()) {
            throw new OperationRefused('Only an application can be rejected.');
        }
        $rider->update(['status' => 'rejected', 'status_reason' => $reason]);
    }

    public function suspend(Rider $rider, Staff $manager, string $reason): void
    {
        $this->requireManager($manager);
        if ($rider->status !== 'active') {
            throw new OperationRefused('Only an active rider can be suspended.');
        }
        DB::transaction(function () use ($rider, $reason) {
            $rider->update(['status' => 'suspended', 'shift_status' => 'off_duty', 'status_reason' => $reason]);
        });
    }

    public function reactivate(Rider $rider, Staff $manager): void
    {
        $this->requireManager($manager);
        if ($rider->status !== 'suspended') {
            throw new OperationRefused('Only a suspended rider can be reactivated.');
        }
        $rider->update(['status' => 'active', 'status_reason' => null]);
    }

    public function deactivate(Rider $rider, Staff $manager, string $reason): void
    {
        $this->requireManager($manager);
        if ($rider->openAssignments()->exists()) {
            throw new OperationRefused('Take back the rider\'s open tasks first.');
        }
        DB::transaction(function () use ($rider, $reason) {
            $rider->update(['status' => 'deactivated', 'shift_status' => 'off_duty', 'status_reason' => $reason, 'device_id' => null]);
            $rider->tokens()->delete();     // signs the rider out of the app
        });
    }

    public function updateLimits(Rider $rider, Staff $manager, float $codLimit, int $maxParcels, bool $onProbation): void
    {
        $this->requireManager($manager);
        $rider->update(['cod_limit' => $codLimit, 'max_parcels' => $maxParcels, 'on_probation' => $onProbation]);
    }

    private function requireManager(Staff $staff): void
    {
        if (! $staff->hasRole(StaffRole::HubManager) || ! $staff->hub?->isStation()) {
            throw new OperationRefused('Only the station hub manager can do this.');
        }
    }
}
