<?php

namespace App\Services\Returns;

use App\Auth\Permission;
use App\Models\Order;
use App\Models\ReturnRequest;
use App\Models\ReturnRequestEvent;
use App\Models\User;
use App\Notifications\BuyerOrderUpdate;
use App\Notifications\SellerReturnUpdate;
use App\Pickrush\Bridge\PicksellBridge;
use App\Pickrush\Enums\ParcelStatus;
use App\Pickrush\Models\Parcel;
use App\Pickrush\Models\ParcelEvent;
use App\Services\AdminNotificationService;
use App\Services\AuditLogger;
use App\Services\Finance\RefundService;
use App\Services\Orders\OrderReceipt;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * The buyer's return/refund, from request to refund:
 *  1. Within the return window of a Delivered order (not yet confirmed received), the buyer asks
 *     with a seller-fault reason, proof and the GCash account for the refund. The order stays
 *     Delivered and the seller's payment on hold until the return is settled.
 *  2. The seller approves (a Pickrush return waybill is booked: pickup at the buyer, delivery to the
 *     seller, who pays the return fee on hand-over) or rejects (PickSell decides the dispute).
 *  3. Pickrush delivers it to the seller (received); a seller who does not take it has it held at
 *     the station under the failed-delivery rules, and PickSell can refund the buyer anyway.
 *  4. The seller checks the item: OK asks Finance for the refund; not OK goes to PickSell.
 *  5. Finance approves the refund (the order completes and the refund is taken out of the held
 *     payment) and sends it through GCash; with the transfer reference the return is Refunded.
 * A return settled without a refund completes the order (afterReturn), releasing the payment.
 */
class ReturnFlow
{
    public function __construct(
        private PicksellBridge $bridge,
        private RefundService $refunds,
        private OrderReceipt $receipt,
        private AdminNotificationService $admins,
    ) {}

    /** The buyer's request. @param list<UploadedFile> $files */
    public function request(Order $order, User $buyer, array $data, array $files): ReturnRequest
    {
        abort_if($order->buyer_id !== $buyer->id, 404, 'Order not found.');
        abort_unless($order->status === 'delivered', 422, $order->status === 'completed'
            ? 'This order was marked received, so it can no longer be returned. Contact Support if something is wrong with it.'
            : 'Returns can only be requested for delivered orders.');
        abort_unless($order->returnWindowOpen(), 422, 'Returns can only be requested within '.config('pickrush.policies.return_window_days').' days of delivery.');
        abort_if($order->returnRequest()->exists(), 409, 'A return request already exists for this order.');

        $quantity = (int) ($data['quantity'] ?? $order->quantity);
        $returnRequest = DB::transaction(function () use ($order, $data, $quantity, $files) {
            $returnRequest = ReturnRequest::create([
                'order_id' => $order->id,
                'buyer_id' => $order->buyer_id,
                'seller_id' => $order->seller_id,
                'reason' => $data['reason'],
                'details' => $data['details'],
                'quantity' => $quantity,
                // The item price of what comes back; the delivery fee went to the riders.
                'refund_amount' => round(((float) $order->amount / max((int) $order->quantity, 1)) * $quantity, 2),
                'attachments' => collect($files)->map(fn (UploadedFile $file) => $file->store('return-requests', 'public'))->all(),
                'refund_gcash_number' => $data['gcash_number'],
                'refund_gcash_name' => $data['gcash_name'],
                'status' => 'requested',
            ]);
            $this->event($returnRequest, $order->buyer_id, 'request_submitted', null, 'requested', $data['details'],
                ['reason' => $data['reason'], 'quantity' => $quantity]);

            return $returnRequest;
        });

        $this->tellSeller($returnRequest, "The buyer asked to return order {$order->order_number}. Review it in Returns: approve to book the Pickrush pickup, or reject.");
        $this->admins->notifyAdmins('return.requested', 'Return/refund requested',
            "A buyer requested a return for order {$order->order_number}.", route('admin.returns.show', $returnRequest, false));

        return $returnRequest;
    }

    /** The seller agrees: the return waybill is booked and Pickrush collects it from the buyer. */
    public function approve(ReturnRequest $returnRequest, ?User $actor, string $note, string $eventType = 'seller_approved', string $from = 'requested'): ReturnRequest
    {
        $returnRequest = DB::transaction(function () use ($returnRequest, $actor, $note, $eventType, $from) {
            $locked = $this->lock($returnRequest, [$from]);
            $locked->update(['status' => 'awaiting_item', 'approved_at' => now(), 'tracking_status' => 'not_started']
                + ($eventType === 'seller_approved' ? ['seller_note' => $note] : []));
            $this->event($locked, $actor?->id, $eventType, $from, 'awaiting_item', $note);

            return $locked;
        });

        $this->bookReturn($returnRequest);

        return $returnRequest->refresh();
    }

    /** Books (or re-books, after a failure) the Pickrush return waybill of an approved return. */
    public function bookReturn(ReturnRequest $returnRequest): ?Parcel
    {
        try {
            $parcel = $this->bridge->bookReturn($returnRequest);
        } catch (Throwable $e) {
            report($e);
            $this->admins->notifyAdmins('return.booking_failed', 'Return pickup not booked',
                "The Pickrush return for order {$returnRequest->order?->order_number} could not be booked: {$e->getMessage()}",
                route('admin.returns.show', $returnRequest, false), priority: 'critical');

            return null;
        }

        $returnRequest->update([
            'carrier' => $parcel->company?->name ?? 'Pickrush',
            'tracking_number' => $parcel->tracking_no,
            'tracking_status' => 'label_generated',
            'tracking_updated_at' => now(),
            'return_fee' => $parcel->cod_amount,
        ]);
        $fee = '₱'.number_format((float) $parcel->cod_amount, 2);
        $this->tellBuyer($returnRequest, "Your return was approved. A Pickrush rider will pick it up from you (tracking no. {$parcel->tracking_no}). Pack the item and write the tracking number on it.");
        $this->tellSeller($returnRequest, "Return for order {$returnRequest->order?->order_number} is booked (tracking no. {$parcel->tracking_no}). Pay the return fee of {$fee} to the rider when it reaches you, then check the item.");

        return $parcel;
    }

    /** The seller refuses the request: PickSell reviews it as a dispute. */
    public function reject(ReturnRequest $returnRequest, User $seller, string $note): void
    {
        DB::transaction(function () use ($returnRequest, $note, $seller) {
            $locked = $this->lock($returnRequest, ['requested']);
            $locked->update(['status' => 'rejected', 'dispute_status' => 'open', 'seller_note' => $note, 'rejected_at' => now()]);
            $this->event($locked, $seller->id, 'seller_rejected', 'requested', 'rejected', $note);
        });
        $this->tellBuyer($returnRequest, 'The seller did not accept your return request. PickSell will review it and decide.');
        $this->admins->notifyAdmins('return.disputed', 'Return dispute to decide',
            "The seller rejected the return for order {$returnRequest->order?->order_number}.", route('admin.returns.show', $returnRequest, false));
    }

    /** Pickrush moved the return waybill (SyncOrderWithParcel). */
    public function parcelScanned(Parcel $parcel, ParcelEvent $event): void
    {
        $returnRequest = PicksellBridge::returnRequestFor($parcel);
        if (! $returnRequest || ! $parcel->isBuyerReturn()) {
            return;   // an item sent back to the buyer only needs its own tracking
        }

        $tracking = match ($event->status) {
            ParcelStatus::PickedUp, ParcelStatus::AtOriginHub => 'in_transit',
            ParcelStatus::DeliveryFailed => 'held_for_seller',
            ParcelStatus::Delivered => 'delivered_to_seller',
            ParcelStatus::Abandoned => 'abandoned',
            ParcelStatus::Lost => 'lost',
            ParcelStatus::Cancelled => 'cancelled',
            default => null,
        };
        if (! $tracking || $tracking === $returnRequest->tracking_status) {
            return;
        }

        if ($tracking === 'delivered_to_seller' && $returnRequest->status === 'awaiting_item') {
            DB::transaction(function () use ($returnRequest, $event) {
                $locked = $this->lock($returnRequest, ['awaiting_item']);
                $locked->update(['status' => 'received', 'received_at' => $event->occurred_at, 'tracking_status' => 'delivered_to_seller', 'tracking_updated_at' => now()]);
                $this->event($locked, null, 'item_received', 'awaiting_item', 'received', 'Pickrush delivered the returned item to the seller.',
                    ['tracking_number' => $locked->tracking_number]);
            });
            $this->tellSeller($returnRequest, "The returned item for order {$returnRequest->order?->order_number} was delivered to you. Check it in Returns so the buyer's refund can go ahead.");
            $this->tellBuyer($returnRequest, 'Your returned item reached the seller. They will check it, then PickSell sends your refund through GCash.');

            return;
        }

        $returnRequest->update(['tracking_status' => $tracking, 'tracking_updated_at' => now()]);
        $this->event($returnRequest, null, 'tracking_updated', $returnRequest->status, $returnRequest->status,
            'Return tracking: '.str_replace('_', ' ', $tracking).'.', ['tracking_number' => $returnRequest->tracking_number, 'note' => $event->note]);

        if (in_array($tracking, ['held_for_seller', 'abandoned', 'lost'], true)) {
            $this->admins->notifyAdmins('return.not_received', 'Return not taken by the seller',
                match ($tracking) {
                    'held_for_seller' => "The seller did not take the returned item for order {$returnRequest->order?->order_number} (tracking no. {$parcel->tracking_no}). It is held at the station. Decide the buyer's refund.",
                    'abandoned' => "The returned item for order {$returnRequest->order?->order_number} was abandoned at the station after the hold. Decide the buyer's refund.",
                    default => "The returned item for order {$returnRequest->order?->order_number} was reported lost by the logistics company. Decide the buyer's refund.",
                },
                route('admin.returns.show', $returnRequest, false), priority: 'critical');
        }
        if ($tracking === 'held_for_seller') {
            $this->tellSeller($returnRequest, "You did not take the returned item for order {$returnRequest->order?->order_number}. It is held at the station for "
                .config('pickrush.policies.return_hold_days').' days: contact the station to have it brought again. Returns not taken back are reported to PickSell and, after '
                .config('pickrush.policies.return_abandon_days').' days, kept by the logistics company.');
        }
        if ($tracking === 'in_transit') {
            $this->tellBuyer($returnRequest, "Pickrush picked up your return (tracking no. {$returnRequest->tracking_number}).");
        }
    }

    /** The seller checked the returned item. OK: Finance is asked for the refund. Not OK: PickSell decides. */
    public function inspect(ReturnRequest $returnRequest, User $seller, bool $passed, string $note): void
    {
        DB::transaction(function () use ($returnRequest, $seller, $passed, $note) {
            $locked = $this->lock($returnRequest, ['received']);
            $locked->update([
                'inspection_result' => $passed ? 'passed' : 'failed',
                'inspection_note' => $note,
                'inspected_at' => now(),
                'status' => $passed ? 'inspected' : 'received',
                'dispute_status' => $passed ? $locked->dispute_status : 'open',
                'reviewed_by' => $seller->id,
                'reviewed_at' => now(),
            ]);
            $this->event($locked, $seller->id, $passed ? 'seller_inspected' : 'inspection_failed', 'received', $passed ? 'inspected' : 'received', $note);
            if ($passed) {
                $this->refunds->requestForReturnRequest($locked, $seller, (float) $locked->refund_amount, 'Seller checked the returned item: '.$note);
            }
        });

        if ($passed) {
            $this->tellBuyer($returnRequest, 'The seller checked your returned item. PickSell is processing your refund through GCash.');
        } else {
            $this->tellBuyer($returnRequest, 'The seller reported a problem with the returned item. PickSell will review it and decide.');
            $this->admins->notifyAdmins('return.disputed', 'Return dispute to decide',
                "The seller reported a problem with the returned item for order {$returnRequest->order?->order_number}.", route('admin.returns.show', $returnRequest, false));
        }
    }

    /**
     * PickSell decides a dispute. On a rejected request: approve_return (book the return) or
     * uphold_rejection (the order completes). On a failed inspection: refund_buyer, or side_with_seller
     * (the item goes back to the buyer, who pays its fee, and the order completes).
     */
    public function resolveDispute(ReturnRequest $returnRequest, User $admin, string $decision, string $notes): void
    {
        $after = DB::transaction(function () use ($returnRequest, $admin, $decision, $notes) {
            $locked = ReturnRequest::whereKey($returnRequest->id)->lockForUpdate()->first();
            abort_unless($locked && $locked->dispute_status === 'open' && in_array($locked->status, ['rejected', 'received'], true), 422, 'This dispute is no longer open.');
            $allowed = $locked->status === 'rejected' ? ['approve_return', 'uphold_rejection', 'refund_without_return'] : ['refund_buyer', 'side_with_seller'];
            abort_unless(in_array($decision, $allowed, true), 422, 'That decision does not fit this dispute.');

            $from = $locked->status;
            $updates = ['dispute_status' => 'resolved', 'admin_decision' => $decision, 'admin_notes' => $notes,
                'resolved_by' => $admin->id, 'admin_resolved_at' => now()];
            $to = match ($decision) {
                'refund_buyer' => 'inspected',
                'side_with_seller' => 'rejected',
                'refund_without_return' => 'inspected',
                default => $from,
            };
            $changes = AuditLogger::diff($locked, $updates + ['status' => $to], ['status', 'dispute_status', 'admin_decision']);
            $locked->update($updates + ['status' => $to]);
            $this->event($locked, $admin->id, 'admin_dispute_resolved', $from, $to, $notes, ['decision' => $decision]);
            app(AuditLogger::class)->record('return.dispute_resolved', $locked, $changes, ['decision' => $decision, 'notes' => $notes], Permission::RETURNS_MANAGE);

            if (in_array($decision, ['refund_buyer', 'refund_without_return'], true)) {
                $this->refunds->requestForReturnRequest($locked, $admin, (float) $locked->refund_amount, 'PickSell decided the dispute: '.$notes);
            }

            return $locked;
        });

        match ($decision) {
            // Back to "requested" for the normal approval, which books the waybill.
            'approve_return' => $this->approveAfterDispute($after, $admin, $notes),
            'uphold_rejection' => $this->settleWithoutRefund($after, $admin, 'The seller\'s rejection was upheld by PickSell. The order is now completed.'),
            'side_with_seller' => $this->sendBackToBuyer($after, $admin),
            default => $this->tellBuyer($after, 'PickSell decided your return in your favour. Your refund is being processed through GCash.'),
        };
    }

    /**
     * PickSell refunds without waiting for the item: the seller did not take the return, the item
     * was lost or abandoned, or PickSell chose not to need it back (cheap items).
     */
    public function refundWithoutItem(ReturnRequest $returnRequest, User $admin, string $notes): void
    {
        DB::transaction(function () use ($returnRequest, $admin, $notes) {
            $locked = $this->lock($returnRequest, ['requested', 'awaiting_item', 'received']);
            $from = $locked->status;
            $locked->update(['status' => 'inspected', 'admin_decision' => 'refund_without_item', 'admin_notes' => $notes,
                'resolved_by' => $admin->id, 'admin_resolved_at' => now()]);
            $this->event($locked, $admin->id, 'admin_refund_without_item', $from, 'inspected', $notes);
            $this->refunds->requestForReturnRequest($locked, $admin, (float) $locked->refund_amount, 'Refund without the item: '.$notes);
        });
        $this->bridge->cancelReturn($returnRequest, 'PickSell refunded the buyer without the item.');
        $this->tellBuyer($returnRequest, 'PickSell approved your refund. It is being processed through GCash.');
    }

    private function approveAfterDispute(ReturnRequest $returnRequest, User $admin, string $notes): void
    {
        $this->approve($returnRequest, $admin, $notes, 'admin_approved', 'rejected');
    }

    /** The return ended without a refund: the order completes and the seller's payment is released. */
    private function settleWithoutRefund(ReturnRequest $returnRequest, User $admin, string $message): void
    {
        $this->receipt->afterReturn($returnRequest->order, $admin, 'Return closed without a refund');
        $this->tellBuyer($returnRequest, $message);
    }

    private function sendBackToBuyer(ReturnRequest $returnRequest, User $admin): void
    {
        $this->receipt->afterReturn($returnRequest->order, $admin, 'Return closed without a refund');
        try {
            $parcel = $this->bridge->bookBackToBuyer($returnRequest);
            $this->event($returnRequest, $admin->id, 'sent_back_to_buyer', 'rejected', 'rejected',
                "The item goes back to the buyer (tracking no. {$parcel->tracking_no}).", ['tracking_number' => $parcel->tracking_no]);
            $this->tellBuyer($returnRequest, 'PickSell sided with the seller after checking the returned item, so there is no refund. The item is being sent back to you (tracking no. '
                ."{$parcel->tracking_no}); pay the delivery fee of ₱".number_format((float) $parcel->cod_amount, 2).' to the rider.');
            $this->tellSeller($returnRequest, "PickSell sided with you on the return for order {$returnRequest->order?->order_number}. Hand the item to the Pickrush rider (tracking no. {$parcel->tracking_no}) to send it back to the buyer.");
        } catch (Throwable $e) {
            report($e);
            $this->admins->notifyAdmins('return.booking_failed', 'Send-back not booked',
                "Sending the item for order {$returnRequest->order?->order_number} back to the buyer could not be booked: {$e->getMessage()}",
                route('admin.returns.show', $returnRequest, false), priority: 'critical');
        }
    }

    private function lock(ReturnRequest $returnRequest, array $statuses): ReturnRequest
    {
        $locked = ReturnRequest::whereKey($returnRequest->id)->lockForUpdate()->firstOrFail();
        // An open dispute is PickSell's to decide (resolveDispute) before anything else happens.
        abort_unless(in_array($locked->status, $statuses, true) && $locked->dispute_status !== 'open', 422,
            'This return request is no longer in the expected state.');

        return $locked;
    }

    private function event(ReturnRequest $returnRequest, ?int $actorId, string $type, ?string $from, string $to, ?string $notes, ?array $metadata = null): void
    {
        ReturnRequestEvent::create([
            'return_request_id' => $returnRequest->id,
            'actor_user_id' => $actorId,
            'event_type' => $type,
            'from_status' => $from,
            'to_status' => $to,
            'notes' => $notes,
            'metadata' => $metadata,
        ]);
    }

    private function tellBuyer(ReturnRequest $returnRequest, string $message): void
    {
        $this->safely(fn () => $returnRequest->buyer?->notify(new BuyerOrderUpdate($returnRequest->order, $message)));
    }

    private function tellSeller(ReturnRequest $returnRequest, string $message): void
    {
        $this->safely(fn () => $returnRequest->seller?->notify(new SellerReturnUpdate($returnRequest->order, $message)));
    }

    private function safely(callable $notify): void
    {
        try {
            $notify();
        } catch (Throwable $e) {
            report($e);   // a notification never undoes the return step
        }
    }
}
