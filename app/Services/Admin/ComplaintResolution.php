<?php

namespace App\Services\Admin;

use App\Auth\Permission;
use App\Models\Complaint;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Admin complaint decisions. Callers authorize first (ComplaintPolicy). */
class ComplaintResolution
{
    public function __construct(private AuditLogger $audit) {}

    public function update(Complaint $complaint, string $status, ?string $adminNotes): void
    {
        DB::transaction(function () use ($complaint, $status, $adminNotes) {
            $values = ['status' => $status, 'admin_notes' => $adminNotes];
            $changes = AuditLogger::diff($complaint, $values, ['status', 'admin_notes']);
            $complaint->update($values);

            $participants = collect([$complaint->filer, $complaint->against])->filter()->unique('id');
            foreach ($participants as $participant) {
                $participant->notifications()->create([
                    'id' => Str::uuid(),
                    'type' => 'App\\Notifications\\ComplaintStatusUpdate',
                    'data' => json_encode([
                        'complaint_id' => $complaint->id,
                        'subject' => $complaint->subject,
                        'status' => $complaint->status,
                        'message' => 'Your complaint #'.$complaint->id.' has been updated to '.str_replace('_', ' ', $complaint->status).'.',
                    ]),
                ]);
            }

            $this->audit->record('complaint.updated', $complaint, $changes, [], Permission::COMPLAINTS_MANAGE);
        });
    }
}
