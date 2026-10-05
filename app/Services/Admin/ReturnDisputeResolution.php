<?php

namespace App\Services\Admin;

use App\Auth\Permission;
use App\Models\ReturnRequest;
use App\Models\ReturnRequestEvent;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;

/** Admin decision on an escalated return dispute. Callers authorize first (ReturnRequestPolicy). */
class ReturnDisputeResolution
{
    public function __construct(private AuditLogger $audit) {}

    public function resolve(ReturnRequest $returnRequest, string $decision, string $adminNotes): void
    {
        DB::transaction(function () use ($returnRequest, $decision, $adminNotes) {
            // Re-check under a row lock so two admins cannot resolve the same dispute concurrently.
            $locked = ReturnRequest::whereKey($returnRequest->id)->lockForUpdate()->first();
            abort_unless($locked && $locked->status === 'rejected' && $locked->dispute_status === 'open', 422, 'This dispute is no longer open.');

            $updates = [
                'dispute_status' => 'resolved',
                'admin_decision' => $decision,
                'admin_notes' => $adminNotes,
                'resolved_by' => auth()->id(),
                'admin_resolved_at' => now(),
            ];
            if ($decision === 'approve_return') {
                $updates['status'] = 'awaiting_item';
                $updates['approved_at'] = now();
            }

            $changes = AuditLogger::diff($returnRequest, $updates, ['status', 'dispute_status', 'admin_decision']);
            $returnRequest->update($updates);
            ReturnRequestEvent::create([
                'return_request_id' => $returnRequest->id,
                'actor_user_id' => auth()->id(),
                'event_type' => 'admin_dispute_resolved',
                'from_status' => 'rejected',
                'to_status' => $updates['status'] ?? 'rejected',
                'notes' => $adminNotes,
                'metadata' => ['decision' => $decision],
            ]);
            $this->audit->record('return.dispute_resolved', $returnRequest, $changes, ['decision' => $decision, 'notes' => $adminNotes], Permission::RETURNS_MANAGE);
        });
    }
}
