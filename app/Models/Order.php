<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $fillable = [
        'order_number', 'product_id', 'buyer_id', 'seller_id', 'logistics_id', 'courier_id',
        'product_name', 'quantity', 'amount', 'commission', 'status',
        'waybill_number', 'tracking_status',
        'packed_at', 'handed_over_at', 'delivered_at',
        'rating', 'feedback',
    ];

    protected $casts = [
        'packed_at' => 'datetime',
        'handed_over_at' => 'datetime',
        'delivered_at' => 'datetime',
    ];

    public function buyer()   { return $this->belongsTo(User::class, 'buyer_id'); }
    public function seller()  { return $this->belongsTo(User::class, 'seller_id'); }
    public function logistics() { return $this->belongsTo(User::class, 'logistics_id'); }
    public function courier() { return $this->belongsTo(User::class, 'courier_id'); }
    public function product() { return $this->belongsTo(Product::class); }
}
