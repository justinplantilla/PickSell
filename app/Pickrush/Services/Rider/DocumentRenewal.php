<?php

namespace App\Pickrush\Services\Rider;

use App\Pickrush\Enums\StaffRole;
use App\Pickrush\Models\Rider;
use App\Pickrush\Models\RiderDocument;
use App\Pickrush\Models\RiderDocumentUpdate;
use App\Pickrush\Models\RiderNotification;
use App\Pickrush\Models\Staff;
use App\Pickrush\Services\OperationRefused;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * A rider renews their driver's license or OR/CR from the rider app, any time. The new one waits
 * for the station hub manager; until it is approved the old one stays in force. A rider whose
 * license or OR/CR in force has expired gets no new tasks (RiderAvailability).
 */
class DocumentRenewal
{
    /** Days before the expiry the rider is reminded, and 0 for the day it lapses. */
    public const REMIND_DAYS = [30, 7, 1, 0];

    /**
     * @param  array{license_no?: string, expiry_date: string}  $data
     * @param  array<int, array{0: UploadedFile, 1: ?string}>  $files  each file with its side (front/back) or null
     */
    public function submit(Rider $rider, string $type, array $data, array $files): RiderDocumentUpdate
    {
        if (! in_array($type, RiderDocumentUpdate::TYPES, true)) {
            throw new OperationRefused('Only a driver\'s license or an OR/CR can be renewed here.');
        }

        return DB::transaction(function () use ($rider, $type, $data, $files) {
            // A newer one replaces what is still waiting; the rider may have fixed a mistake.
            $rider->documentUpdates()->where('doc_type', $type)->where('status', 'pending')->update(['status' => 'replaced']);

            return RiderDocumentUpdate::create([
                'rider_id' => $rider->id,
                'doc_type' => $type,
                'license_no' => $type === 'drivers_license' ? strtoupper(trim($data['license_no'])) : null,
                'expiry_date' => $data['expiry_date'],
                'files' => collect($files)->map(fn ($file) => [
                    'path' => $file[0]->store("pickrush/riders/{$rider->id}", 'local'),   // private disk
                    'side' => $file[1],
                ])->all(),
                'status' => 'pending',
            ]);
        });
    }

    /** The manager checked the new document against the rider: it replaces the old one. */
    public function approve(RiderDocumentUpdate $update, Staff $manager): void
    {
        $this->requireManager($manager);
        if (! $update->isPending()) {
            throw new OperationRefused('This update was already decided.');
        }
        if ($update->expiry_date->lt(today())) {
            throw new OperationRefused("This {$update->label()} expired on {$update->expiry_date->format('M d, Y')}. Reject it so the rider sends a current one.");
        }

        DB::transaction(function () use ($update, $manager) {
            $rider = $update->rider;
            $rider->documents()->where('doc_type', $update->doc_type)->update(['replaced_at' => now()]);
            foreach ($update->files as $file) {
                RiderDocument::create([
                    'rider_id' => $rider->id,
                    'doc_type' => $update->doc_type,
                    'side' => $file['side'],
                    'file_path' => $file['path'],
                    'expiry_date' => $update->expiry_date,
                    'uploaded_by' => null,
                    'document_update_id' => $update->id,
                ]);
            }
            if ($update->license_no) {
                $rider->update(['license_no' => $update->license_no]);
            }
            $update->update(['status' => 'approved', 'reviewed_by' => $manager->id, 'reviewed_at' => now()]);

            RiderNotification::send($rider, 'document_approved', "Your new {$update->label()} is approved",
                "It is valid until {$update->expiry_date->format('M d, Y')}.", ['update_id' => $update->id]);
        });
    }

    public function reject(RiderDocumentUpdate $update, Staff $manager, string $reason): void
    {
        $this->requireManager($manager);
        if (! $update->isPending()) {
            throw new OperationRefused('This update was already decided.');
        }
        $update->update(['status' => 'rejected', 'reason' => $reason, 'reviewed_by' => $manager->id, 'reviewed_at' => now()]);

        RiderNotification::send($update->rider, 'document_rejected', "Your new {$update->label()} was not accepted",
            "{$reason} Send it again from Account.", ['update_id' => $update->id]);
    }

    /** The manager read the real expiry off an OR/CR whose date the system estimated. */
    public function setOrcrExpiry(Rider $rider, Staff $manager, string $expiry): void
    {
        $this->requireManager($manager);
        $rider->documents()->where('doc_type', 'or_cr')->update(['expiry_date' => $expiry, 'expiry_estimated' => false]);
    }

    /**
     * Tells active riders their license or OR/CR expires in 30, 7 or 1 days, or lapsed today.
     * Run once a day; a rider who already sent a renewal is not reminded.
     *
     * @return int reminders sent
     */
    public function remind(): int
    {
        $sent = 0;
        $documents = RiderDocument::withoutGlobalScopes()->whereNull('replaced_at')
            ->whereDate('expiry_date', '>=', today())->whereDate('expiry_date', '<=', today()->addDays(max(self::REMIND_DAYS)))
            ->whereHas('rider', fn ($q) => $q->withoutGlobalScopes()->where('status', 'active'))
            ->with(['rider' => fn ($q) => $q->withoutGlobalScopes()])->get()
            ->filter(fn (RiderDocument $doc) => in_array((int) today()->diffInDays($doc->expiry_date), self::REMIND_DAYS, true))
            ->unique(fn (RiderDocument $doc) => "{$doc->rider_id}:{$doc->doc_type}");   // the license has two sides

        foreach ($documents as $doc) {
            $waiting = RiderDocumentUpdate::withoutGlobalScopes()->where('rider_id', $doc->rider_id)
                ->where('doc_type', $doc->doc_type)->where('status', 'pending')->exists();
            if ($waiting) {
                continue;
            }
            $days = (int) today()->diffInDays($doc->expiry_date);
            RiderNotification::send($doc->rider, 'document_expiring',
                $days === 0 ? "Your {$doc->label()} expires today" : "Your {$doc->label()} expires in {$days} ".($days === 1 ? 'day' : 'days'),
                $days === 0
                    ? 'From tomorrow you get no new tasks until your station approves a renewed one. Send it from Account.'
                    : 'Renew it and send the new one from Account so your station can approve it in time.',
                ['doc_type' => $doc->doc_type]);
            $sent++;
        }

        return $sent;
    }

    private function requireManager(Staff $staff): void
    {
        if (! $staff->hasRole(StaffRole::HubManager) || ! $staff->hub?->isStation()) {
            throw new OperationRefused('Only the station hub manager can do this.');
        }
    }
}
