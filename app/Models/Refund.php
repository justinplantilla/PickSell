<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Refund extends Model
{
    public const STATUSES = ['requested', 'approved', 'processed', 'rejected'];

    protected $fillable = [
        'order_id',
        'return_request_id',
        'requested_by',
        'approved_by',
        'amount',
        'commission_reversal',
        'seller_adjustment',
        'status',
        'reason',
        'decision_notes',
        'approved_at',
        'processed_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'commission_reversal' => 'decimal:2',
        'seller_adjustment' => 'decimal:2',
        'approved_at' => 'datetime',
        'processed_at' => 'datetime',
    ];

    public function order() { return $this->belongsTo(Order::class); }
    public function returnRequest() { return $this->belongsTo(ReturnRequest::class); }
    public function requester() { return $this->belongsTo(User::class, 'requested_by'); }
    public function approver() { return $this->belongsTo(User::class, 'approved_by'); }
    public function financialTransactions()
    {
        return $this->hasMany(FinancialTransaction::class, 'reference_id')
            ->where('reference_type', 'refund');
    }
}
