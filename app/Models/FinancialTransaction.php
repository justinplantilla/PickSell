<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FinancialTransaction extends Model
{
    protected $fillable = [
        'order_id',
        'seller_id',
        'type',
        'debit',
        'credit',
        'amount',
        'reference_type',
        'reference_id',
        'status',
        'description',
        'created_by',
    ];

    protected $casts = [
        'debit' => 'decimal:2',
        'credit' => 'decimal:2',
        'amount' => 'decimal:2',
    ];

    public function order() { return $this->belongsTo(Order::class); }
    public function seller() { return $this->belongsTo(User::class, 'seller_id'); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
}
