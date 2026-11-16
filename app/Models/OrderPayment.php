<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderPayment extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['amount_due' => 'decimal:2', 'amount_collected' => 'decimal:2', 'collected_at' => 'datetime'];

    public function order() { return $this->belongsTo(Order::class); }
    public function assignment() { return $this->belongsTo(\App\Pickrush\Models\Assignment::class); }
}
