<?php

namespace App\Pickrush\Services\Station;

use App\Pickrush\Enums\StaffRole;
use App\Pickrush\Models\Rider;
use App\Pickrush\Models\RiderDocument;
use App\Pickrush\Models\Staff;
use App\Pickrush\Services\OperationRefused;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Rider accounts are created at the station only, after an in-person interview. A second staff
 * member verifies identity and documents (never the person who created the account); the hub
 * manager then approves. New riders start on probation with lower limits.
 */
class RiderOnboarding
{
    /** Documents needed before verification; OR/CR only for motor vehicles. */
    public static function requiredDocuments(Rider $rider): array
    {
        $required = ['gov_id', 'drivers_license', 'selfie_with_id'];
        if ($rider->vehicle_type && $rider->vehicle_type !== 'bicycle') {
            $required[] = 'or_cr';
        }

        return $required;
    }

    public function create(array $data, Staff $manager): Rider
    {
        $this->requireManager($manager);
        $limits = config('pickrush.policies.rider_limits.probation');

        return Rider::create($data + [
            'station_hub_id' => $manager->hub_id,
            'status' => 'pending',
            'shift_status' => 'off_duty',
            'cod_limit' => $limits['cod_limit'],
            'max_parcels' => $limits['max_parcels'],
            'on_probation' => true,
            'created_by' => $manager->id,
        ]);
    }

    public function addDocument(Rider $rider, string $type, UploadedFile $file, ?string $expiryDate, Staff $staff): RiderDocument
    {
        $this->requireManager($staff);
        if (! array_key_exists($type, config('pickrush.rider_documents'))) {
            throw new OperationRefused('Unknown document type.');
        }

        return RiderDocument::create([
            'rider_id' => $rider->id,
            'doc_type' => $type,
            'file_path' => $file->store("pickrush/riders/{$rider->id}", 'local'),   // private disk
            'expiry_date' => $expiryDate,
            'uploaded_by' => $staff->id,
        ]);
    }

    /** Second person checks identity, licence validity and that the OR/CR plate matches. */
    public function verify(Rider $rider, Staff $staff): void
    {
        if ($rider->status !== 'pending') {
            throw new OperationRefused('Only a pending account can be verified.');
        }
        if ($rider->created_by === $staff->id) {
            throw new OperationRefused('The person who created the account cannot verify it. Ask another staff member.');
        }
        $missing = array_diff(self::requiredDocuments($rider), $rider->documents()->pluck('doc_type')->all());
        if ($missing) {
            $labels = array_map(fn ($type) => config("pickrush.rider_documents.{$type}"), $missing);
            throw new OperationRefused('Missing documents: '.implode(', ', $labels).'.');
        }
        if ($rider->documents()->whereNotNull('expiry_date')->where('expiry_date', '<', today())->exists()) {
            throw new OperationRefused('A document has expired. Upload a current one first.');
        }

        $rider->update(['status' => 'under_review', 'verified_by' => $staff->id, 'verified_at' => now()]);
    }

    public function approve(Rider $rider, Staff $manager): void
    {
        $this->requireManager($manager);
        if ($rider->status !== 'under_review') {
            throw new OperationRefused('Approve after a second staff member has verified the documents.');
        }

        // Activation (SMS code and PIN on the rider app) is handled by the rider API.
        $rider->update(['status' => 'active', 'approved_by' => $manager->id, 'approved_at' => now(), 'status_reason' => null]);
    }

    public function reject(Rider $rider, Staff $manager, string $reason): void
    {
        $this->requireManager($manager);
        if (! in_array($rider->status, ['pending', 'under_review'], true)) {
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
        $rider->update(['status' => 'deactivated', 'shift_status' => 'off_duty', 'status_reason' => $reason, 'device_id' => null]);
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
