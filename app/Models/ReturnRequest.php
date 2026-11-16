<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReturnRequest extends Model
{
    public const STATUSES = [
        'requested',
        'awaiting_item',
        'received',
        'inspected',
        'approved_for_refund',
        'refund_due',
        'completed',
        'rejected',
    ];

    /** Return reasons a buyer may choose (codes as BuyerReturnRequestController validates them). */
    public const REASONS = [
        'damaged' => 'Damaged item',
        'wrong_item' => 'Wrong item received',
        'wrong_size' => 'Wrong size',
        'not_as_described' => 'Not as described',
        'other' => 'Other',
    ];

    /**
     * What a buyer may ask a return for: the seller's fault only. "Other" stays for returns PickSell
     * opens from a support complaint (RefundService::createComplaintReturn).
     */
    public const BUYER_REASONS = ['damaged', 'wrong_item', 'wrong_size', 'not_as_described'];

    /** Not settled yet: the order stays Delivered, Order Received is blocked and the payment is held. */
    public const OPEN = ['requested', 'awaiting_item', 'received', 'inspected', 'approved_for_refund', 'refund_due'];

    /** The parcel's external_ref prefix for the return waybill (PicksellBridge::bookReturn). */
    public const PARCEL_REF = 'RET-';

    /** ... and for the item sent back to the buyer when PickSell sides with the seller. */
    public const BACK_TO_BUYER_REF = 'RTB-';

    protected $fillable = [
        'order_id', 'buyer_id', 'seller_id', 'reason', 'details', 'status',
        'seller_note', 'dispute_status', 'admin_notes', 'admin_decision',
        'resolved_by', 'approved_at', 'rejected_at', 'received_at',
        'refund_due_at', 'completed_at', 'admin_resolved_at', 'quantity',
        'refund_amount', 'attachments', 'carrier', 'tracking_number',
        'tracking_status', 'tracking_updated_at', 'reviewed_by', 'reviewed_at',
        'refund_gcash_number', 'refund_gcash_name', 'return_fee', 'inspection_result', 'inspection_note', 'inspected_at',
    ];

    protected $casts = [
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'received_at' => 'datetime',
        'refund_due_at' => 'datetime',
        'completed_at' => 'datetime',
        'admin_resolved_at' => 'datetime',
        'refund_amount' => 'decimal:2',
        'attachments' => 'array',
        'tracking_updated_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'return_fee' => 'decimal:2',
        'inspected_at' => 'datetime',
    ];

    /** Still being handled (see OPEN); a rejection under dispute counts too. */
    public function isOpen(): bool
    {
        return in_array($this->status, self::OPEN, true) || ($this->status === 'rejected' && $this->dispute_status === 'open');
    }

    public static function parcelRef(self|int $returnRequest): string
    {
        return self::PARCEL_REF.(is_int($returnRequest) ? $returnRequest : $returnRequest->id);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function buyer()
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function seller()
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function resolver()
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function events()
    {
        return $this->hasMany(ReturnRequestEvent::class)->oldest();
    }

    public function refunds()
    {
        return $this->hasMany(Refund::class);
    }

    public function refund()
    {
        return $this->hasOne(Refund::class)->latestOfMany();
    }

    public function latestRefund()
    {
        return $this->hasOne(Refund::class)->latestOfMany();
    }
}
