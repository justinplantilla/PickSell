<?php

namespace App\Services\Admin;

use App\Auth\Permission;
use App\Models\Complaint;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Finance\RefundService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ComplaintResolution
{
    public function __construct(
        private AuditLogger $audit,
        private RefundService $refunds,
    ) {}

    public function update(Complaint $complaint, User $actor, string $status, ?string $adminNotes): void
    {
        if (in_array($status, ['resolved', 'dismissed'], true)) {
            $this->resolve(
                $complaint,
                $actor,
                'no_financial_action',
                $adminNotes ?: ($status === 'dismissed' ? 'Complaint dismissed without financial action.' : 'Resolved without financial action.'),
                null,
                $status,
            );

            return;
        }

        DB::transaction(function () use ($complaint, $actor, $status, $adminNotes): void {
            $locked = Complaint::query()->whereKey($complaint->id)->lockForUpdate()->firstOrFail();
            $values = ['status' => $status, 'admin_notes' => $adminNotes];
            $changes = AuditLogger::diff($locked, $values, ['status', 'admin_notes']);
            $locked->update($values);

            $this->notifyParticipants($locked, 'updated to '.str_replace('_', ' ', $status).'.');
            $this->audit->record('complaint.updated', $locked, $changes, [], Permission::COMPLAINTS_MANAGE);
        });
    }

    public function resolve(
        Complaint $complaint,
        User $actor,
        string $resolutionType,
        string $resolutionNotes,
        ?float $refundAmount = null,
        string $status = 'resolved',
    ): void {
        DB::transaction(function () use ($complaint, $actor, $resolutionType, $resolutionNotes, $refundAmount, $status): void {
            $locked = Complaint::query()->whereKey($complaint->id)->lockForUpdate()->firstOrFail();
            abort_unless(in_array($locked->status, ['open', 'under_review'], true), 422, 'This complaint is already closed.');

            if ($status === 'dismissed' && $resolutionType !== 'no_financial_action') {
                abort(422, 'A dismissed complaint cannot authorize a return or refund.');
            }

            if ($resolutionType === 'return') {
                $this->refunds->createComplaintReturn($locked, $actor, $resolutionNotes);
            } elseif ($resolutionType === 'refund') {
                $this->refunds->createComplaintRefund($locked, $actor, (float) $refundAmount, $resolutionNotes);
            }

            $values = [
                'status' => $status,
                'admin_notes' => $resolutionNotes,
                'resolution_type' => $resolutionType,
                'resolution_notes' => $resolutionNotes,
                'resolved_at' => now(),
            ];
            $changes = AuditLogger::diff(
                $locked,
                $values,
                ['status', 'admin_notes', 'resolution_type', 'resolution_notes', 'resolved_at'],
            );
            $locked->update($values);

            $this->notifyParticipants($locked, 'resolved: '.str_replace('_', ' ', $resolutionType).'.');
            $this->audit->record('complaint.resolved', $locked, $changes, [
                'resolution_type' => $resolutionType,
                'refund_amount' => $resolutionType === 'refund' ? $refundAmount : null,
            ], Permission::COMPLAINTS_MANAGE);
        });
    }

    private function notifyParticipants(Complaint $complaint, string $message): void
    {
        $complaint->loadMissing(['filer', 'against', 'order.buyer', 'order.seller', 'order.courier']);
        $participants = collect([
            $complaint->filer,
            $complaint->against,
            $complaint->order?->buyer,
            $complaint->order?->seller,
            $complaint->order?->courier,
        ])->filter()->unique('id');

        foreach ($participants as $participant) {
            $participant->notifications()->create([
                'id' => (string) Str::uuid(),
                'type' => 'App\\Notifications\\ComplaintStatusUpdate',
                'data' => json_encode([
                    'complaint_id' => $complaint->id,
                    'subject' => $complaint->subject,
                    'status' => $complaint->status,
                    'resolution_type' => $complaint->resolution_type,
                    'message' => 'Your complaint #'.$complaint->id.' was '.$message,
                ], JSON_THROW_ON_ERROR),
            ]);
        }
    }
}
