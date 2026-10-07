<?php

namespace App\Services\Admin;

use App\Auth\Permission;
use App\Models\ReturnRequest;
use App\Models\ReturnRequestEvent;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Finance\RefundService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ReturnService
{
    public function __construct(
        private AuditLogger $audit,
        private RefundService $refunds,
    ) {}

    public function approve(ReturnRequest $returnRequest, User $actor, string $notes): void
    {
        $this->transition($returnRequest, $actor, 'requested', 'awaiting_item', 'admin_approved', $notes, [
            'approved_at' => now(),
            'admin_decision' => 'admin_approved',
        ]);
    }

    public function reject(ReturnRequest $returnRequest, User $actor, string $notes): void
    {
        $this->transition($returnRequest, $actor, 'requested', 'rejected', 'admin_rejected', $notes, [
            'rejected_at' => now(),
            'admin_decision' => 'admin_rejected',
            'dispute_status' => 'resolved',
            'resolved_by' => $actor->id,
            'admin_resolved_at' => now(),
        ]);
    }

    public function inspect(ReturnRequest $returnRequest, User $actor, string $notes): void
    {
        $this->transition($returnRequest, $actor, 'received', 'inspected', 'admin_inspected', $notes, [
            'reviewed_by' => $actor->id,
            'reviewed_at' => now(),
        ]);
    }

    public function approveRefund(ReturnRequest $returnRequest, User $actor, float $amount, string $notes): void
    {
        $this->refunds->requestForReturnRequest($returnRequest, $actor, $amount, $notes);
    }

    private function transition(
        ReturnRequest $returnRequest,
        User $actor,
        string $expectedStatus,
        string $nextStatus,
        string $eventType,
        string $notes,
        array $extraValues = [],
    ): void {
        DB::transaction(function () use ($returnRequest, $actor, $expectedStatus, $nextStatus, $eventType, $notes, $extraValues): void {
            $locked = ReturnRequest::query()->whereKey($returnRequest->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== $expectedStatus || $locked->dispute_status === 'open') {
                abort(422, 'This return request is no longer in the expected state.');
            }

            $values = [
                'status' => $nextStatus,
                'admin_notes' => $notes,
                ...$extraValues,
            ];
            $changes = AuditLogger::diff($locked, $values, array_keys($values));
            $locked->update($values);

            ReturnRequestEvent::create([
                'return_request_id' => $locked->id,
                'actor_user_id' => $actor->id,
                'event_type' => $eventType,
                'from_status' => $expectedStatus,
                'to_status' => $nextStatus,
                'notes' => $notes,
                'metadata' => isset($values['refund_amount']) ? ['refund_amount' => $values['refund_amount']] : null,
            ]);

            $this->notifyParticipants($locked, $eventType, $notes);
            $this->audit->record('return.'.$eventType, $locked, $changes, [
                'from_status' => $expectedStatus,
                'to_status' => $nextStatus,
                'refund_amount' => $values['refund_amount'] ?? null,
            ], Permission::RETURNS_MANAGE);
        });
    }

    private function notifyParticipants(ReturnRequest $returnRequest, string $eventType, string $notes): void
    {
        foreach (collect([$returnRequest->buyer, $returnRequest->seller])->filter()->unique('id') as $participant) {
            $participant->notifications()->create([
                'id' => (string) Str::uuid(),
                'type' => 'App\\Notifications\\ReturnRequestUpdate',
                'data' => json_encode([
                    'return_request_id' => $returnRequest->id,
                    'order_number' => $returnRequest->order?->order_number,
                    'status' => $returnRequest->status,
                    'event' => $eventType,
                    'message' => 'Return request for order '.($returnRequest->order?->order_number ?? '#'.$returnRequest->order_id)
                        .' was '.str_replace('_', ' ', $eventType).'.',
                    'notes' => $notes,
                ], JSON_THROW_ON_ERROR),
            ]);
        }
    }
}
