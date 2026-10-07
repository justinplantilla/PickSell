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

    protected $fillable = [
        'order_id', 'buyer_id', 'seller_id', 'reason', 'details', 'status',
        'seller_note', 'dispute_status', 'admin_notes', 'admin_decision',
        'resolved_by', 'approved_at', 'rejected_at', 'received_at',
        'refund_due_at', 'completed_at', 'admin_resolved_at', 'quantity',
        'refund_amount', 'attachments', 'carrier', 'tracking_number',
        'tracking_status', 'tracking_updated_at', 'reviewed_by', 'reviewed_at',
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
    ];

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
