<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One order status change, written automatically by Order::booted(). */
class OrderStatusHistory extends Model
{
    protected $fillable = ['order_id', 'changed_by', 'source', 'from_status', 'to_status', 'reason'];

    public function order()   { return $this->belongsTo(Order::class); }
    public function changer() { return $this->belongsTo(User::class, 'changed_by'); }
}
