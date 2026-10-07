<?php

namespace App\Services\Finance;

use App\Auth\Permission;
use App\Models\Complaint;
use App\Models\Order;
use App\Models\Refund;
use App\Models\ReturnRequest;
use App\Models\ReturnRequestEvent;
use App\Models\User;
use App\Services\AdminNotificationService;
use App\Services\AuditLogger;
use App\Services\CommissionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RefundService
{
    public function __construct(
        private AuditLogger $audit,
        private AdminNotificationService $notifications,
        private CommissionService $commission,
        private FinancialLedgerService $ledger,
    ) {}

    public function requestForReturnRequest(ReturnRequest $returnRequest, User $actor, float $amount, string $notes): Refund
    {
        return DB::transaction(function () use ($returnRequest, $actor, $amount, $notes): Refund {
            $locked = ReturnRequest::query()->whereKey($returnRequest->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->status === 'inspected' && $locked->dispute_status !== 'open', 422, 'This return is not ready to create a refund request.');

            $order = Order::query()->whereKey($locked->order_id)->lockForUpdate()->firstOrFail();
            if ($amount <= 0 || $amount > (float) $order->amount) {
                throw ValidationException::withMessages([
                    'refund_amount' => 'The requested refund must be greater than zero and cannot exceed the order amount.',
                ]);
            }

            abort_if(
                Refund::query()->where('return_request_id', $locked->id)->where('status', '!=', 'rejected')->exists(),
                409,
                'An active refund already exists for this return request.',
            );

            $refund = Refund::create([
                'order_id' => $order->id,
                'return_request_id' => $locked->id,
                'requested_by' => $locked->buyer_id,
                'amount' => round($amount, 2),
                'status' => 'requested',
                'reason' => $notes,
            ]);
            $values = [
                'status' => 'approved_for_refund',
                'refund_amount' => round($amount, 2),
                'refund_due_at' => null,
                'admin_decision' => 'refund_requested',
                'admin_notes' => $notes,
            ];
            $changes = AuditLogger::diff($locked, $values, array_keys($values));
            $locked->update($values);

            ReturnRequestEvent::create([
                'return_request_id' => $locked->id,
                'actor_user_id' => $actor->id,
                'event_type' => 'refund_requested',
                'from_status' => 'inspected',
                'to_status' => 'approved_for_refund',
                'notes' => $notes,
                'metadata' => ['refund_id' => $refund->id, 'refund_amount' => round($amount, 2)],
            ]);
            $this->notifyReturnParticipants($locked, 'refund_requested', $notes, [
                'refund_id' => $refund->id,
                'refund_amount' => round($amount, 2),
            ]);
            $this->audit->record('return.refund_requested', $locked, $changes, [
                'from_status' => 'inspected',
                'to_status' => 'approved_for_refund',
                'refund_amount' => round($amount, 2),
            ], Permission::RETURNS_MANAGE);
            $this->notifications->notifyAdmins(
                'refund.requested',
                'Refund requested',
                "A refund was requested for order {$order->order_number}.",
                route('admin.refunds', [], false),
                $actor->hasPermission(Permission::ACCOUNT_VIEW) ? $actor->id : null,
            );

            return $refund;
        });
    }

    public function approve(Refund $refund, User $actor, string $notes): void
    {
        DB::transaction(function () use ($refund, $actor, $notes): void {
            $locked = Refund::query()->whereKey($refund->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->status === 'requested', 422, 'This refund request is no longer awaiting approval.');
            $order = Order::query()->whereKey($locked->order_id)->lockForUpdate()->firstOrFail();

            $reservedByOtherRefunds = (float) Refund::query()
                ->where('order_id', $order->id)
                ->whereKeyNot($locked->id)
                ->whereIn('status', ['requested', 'approved', 'processed'])
                ->sum('amount');
            if ((float) $locked->amount > max(0, round((float) $order->amount - $reservedByOtherRefunds, 2))) {
                throw ValidationException::withMessages([
                    'refund' => 'This refund would exceed the order amount after accounting for other active refunds.',
                ]);
            }

            $reversal = $this->commission->refundCommissionReversal($locked->amount, $order->commission, $order->amount);
            $sellerAdjustment = $this->commission->fromCents(
                $this->commission->toCents($locked->amount) - $this->commission->toCents($reversal),
            );
            $values = [
                'commission_reversal' => $reversal,
                'seller_adjustment' => $sellerAdjustment,
                'status' => 'approved',
                'approved_by' => $actor->id,
                'approved_at' => now(),
                'decision_notes' => $notes,
            ];
            $changes = AuditLogger::diff($locked, $values, array_keys($values));
            $locked->update($values);
            $this->ledger->postApprovedRefund($locked, $actor);

            if ($locked->return_request_id) {
                ReturnRequestEvent::create([
                    'return_request_id' => $locked->return_request_id,
                    'actor_user_id' => $actor->id,
                    'event_type' => 'refund_financially_approved',
                    'from_status' => $locked->returnRequest?->status,
                    'to_status' => $locked->returnRequest?->status ?? 'approved_for_refund',
                    'notes' => $notes,
                    'metadata' => [
                        'refund_id' => $locked->id,
                        'refund_amount' => (float) $locked->amount,
                        'commission_reversal' => $reversal,
                        'seller_adjustment' => $sellerAdjustment,
                    ],
                ]);
                $this->notifyReturnParticipants($locked->returnRequest, 'refund_financially_approved', $notes, [
                    'refund_id' => $locked->id,
                    'refund_amount' => (float) $locked->amount,
                ]);
            }

            $this->audit->record('refund.approved', $locked, $changes, [
                'order_id' => $order->id,
                'amount' => (float) $locked->amount,
                'commission_reversal' => $reversal,
                'seller_adjustment' => $sellerAdjustment,
            ], Permission::REFUNDS_APPROVE);
            $this->notifications->notifyAdmins(
                'financial.adjustment',
                'Financial adjustment posted',
                "A refund adjustment was posted for order {$order->order_number}.",
                route('admin.refunds', [], false),
                $actor->id,
                'critical',
            );
        });
    }

    public function reject(Refund $refund, User $actor, string $notes): void
    {
        DB::transaction(function () use ($refund, $actor, $notes): void {
            $locked = Refund::query()->whereKey($refund->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->status === 'requested', 422, 'Only a pending refund can be rejected.');

            $values = ['status' => 'rejected', 'decision_notes' => $notes];
            $changes = AuditLogger::diff($locked, $values, array_keys($values));
            $locked->update($values);

            if ($locked->return_request_id) {
                $returnRequest = ReturnRequest::query()->whereKey($locked->return_request_id)->lockForUpdate()->firstOrFail();
                if ($returnRequest->status === 'approved_for_refund') {
                    $returnRequest->update([
                        'status' => 'inspected',
                        'admin_decision' => 'refund_rejected',
                        'refund_due_at' => null,
                        'admin_notes' => $notes,
                    ]);
                }

                ReturnRequestEvent::create([
                    'return_request_id' => $returnRequest->id,
                    'actor_user_id' => $actor->id,
                    'event_type' => 'refund_rejected',
                    'from_status' => 'approved_for_refund',
                    'to_status' => $returnRequest->fresh()->status,
                    'notes' => $notes,
                    'metadata' => ['refund_id' => $locked->id],
                ]);
                $this->notifyReturnParticipants($returnRequest, 'refund_rejected', $notes, ['refund_id' => $locked->id]);
            }

            $this->audit->record('refund.rejected', $locked, $changes, [
                'order_id' => $locked->order_id,
                'amount' => (float) $locked->amount,
                'reason' => $notes,
            ], Permission::REFUNDS_MANAGE);
        });
    }

    public function assertApprovedForReturnRequest(ReturnRequest $returnRequest): void
    {
        abort_unless(
            Refund::query()->where('return_request_id', $returnRequest->id)->where('status', 'approved')->exists(),
            422,
            'The refund must be approved by Finance before it can be processed.',
        );
    }

    public function markProcessedForReturnRequest(ReturnRequest $returnRequest, User $actor): void
    {
        $refund = Refund::query()
            ->where('return_request_id', $returnRequest->id)
            ->where('status', 'approved')
            ->lockForUpdate()
            ->first();
        abort_unless($refund, 422, 'The refund must be approved by Finance before it can be processed.');

        $values = ['status' => 'processed', 'processed_at' => now()];
        $changes = AuditLogger::diff($refund, $values, array_keys($values));
        $refund->update($values);
        $this->audit->record('refund.processed', $refund, $changes, [
            'order_id' => $refund->order_id,
            'amount' => (float) $refund->amount,
        ]);
    }

    public function createComplaintReturn(Complaint $complaint, User $actor, string $notes): ReturnRequest
    {
        $order = $this->eligibleOrder($complaint);

        if (! in_array($order->status, ['delivered', 'completed'], true)) {
            throw ValidationException::withMessages([
                'resolution_type' => 'A return can only be authorized for a delivered order.',
            ]);
        }

        return $this->createReturnRequest($complaint, $order, $actor, 'awaiting_item', $notes, (float) $order->amount);
    }

    public function createComplaintRefund(Complaint $complaint, User $actor, float $amount, string $notes): ReturnRequest
    {
        $order = $this->eligibleOrder($complaint);

        if ($amount > (float) $order->amount) {
            throw ValidationException::withMessages([
                'refund_amount' => 'The refund cannot exceed the order amount.',
            ]);
        }

        $request = $this->createReturnRequest($complaint, $order, $actor, 'approved_for_refund', $notes, $amount);
        $this->createRequestedRefund($request, $amount, $notes);

        return $request;
    }

    private function eligibleOrder(Complaint $complaint): Order
    {
        if (! $complaint->order_id) {
            throw ValidationException::withMessages([
                'order_id' => 'Link an order to this complaint before selecting a return or refund.',
            ]);
        }

        $order = Order::query()->whereKey($complaint->order_id)->lockForUpdate()->firstOrFail();

        if ($order->returnRequest()->exists()) {
            throw ValidationException::withMessages([
                'order_id' => 'This order already has a return or refund request.',
            ]);
        }

        return $order;
    }

    private function createReturnRequest(
        Complaint $complaint,
        Order $order,
        User $actor,
        string $status,
        string $notes,
        float $refundAmount,
    ): ReturnRequest {
        $request = ReturnRequest::create([
            'order_id' => $order->id,
            'buyer_id' => $order->buyer_id,
            'seller_id' => $order->seller_id,
            'reason' => 'other',
            'details' => 'Complaint #'.$complaint->id.': '.$notes,
            'status' => $status,
            'dispute_status' => 'resolved',
            'admin_notes' => $notes,
            'admin_decision' => $status === 'refund_due' ? 'complaint_refund' : 'complaint_return',
            'resolved_by' => $actor->id,
            'admin_resolved_at' => now(),
            'approved_at' => $status === 'awaiting_item' ? now() : null,
            'refund_due_at' => $status === 'refund_due' ? now() : null,
            'quantity' => max((int) $order->quantity, 1),
            'refund_amount' => $refundAmount,
        ]);

        ReturnRequestEvent::create([
            'return_request_id' => $request->id,
            'actor_user_id' => $actor->id,
            'event_type' => match ($status) {
                'refund_due' => 'complaint_refund_due',
                'approved_for_refund' => 'complaint_refund_requested',
                default => 'complaint_return_authorized',
            },
            'from_status' => null,
            'to_status' => $status,
            'notes' => $notes,
            'metadata' => [
                'complaint_id' => $complaint->id,
                'refund_amount' => $refundAmount,
            ],
        ]);

        return $request;
    }

    private function createRequestedRefund(ReturnRequest $returnRequest, float $amount, string $notes): Refund
    {
        return Refund::create([
            'order_id' => $returnRequest->order_id,
            'return_request_id' => $returnRequest->id,
            'requested_by' => $returnRequest->buyer_id,
            'amount' => round($amount, 2),
            'status' => 'requested',
            'reason' => $notes.' (Complaint #'.$returnRequest->id.')',
        ]);
    }

    private function notifyReturnParticipants(ReturnRequest $returnRequest, string $eventType, string $notes, array $extra = []): void
    {
        $returnRequest->loadMissing(['buyer', 'seller', 'order']);
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
                    ...$extra,
                ], JSON_THROW_ON_ERROR),
            ]);
        }
    }
}
