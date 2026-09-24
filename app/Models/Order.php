<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    public const STATUS_LIFECYCLE = [
        'placed',
        'confirmed',
        'preparing',
        'ready_for_pickup',
        'picked_up',
        'at_sorting_center',
        'sorted',
        'assigned_to_rider',
        'out_for_delivery',
        'delivered',
        'completed',
        'delivery_failed',
        'returned',
        'cancelled',
    ];

    protected $fillable = [
        'order_number', 'product_id', 'buyer_id', 'seller_id', 'logistics_id', 'courier_id',
        'origin_branch_id', 'destination_branch_id', 'destination_barangay_id',
        'product_name', 'quantity', 'amount', 'commission', 'status',
        'waybill_number', 'tracking_status',
        'packed_at', 'handed_over_at', 'delivered_at', 'confirmed_by_seller_at', 'assigned_at',
        'rating', 'feedback',
    ];

    protected $casts = [
        'packed_at' => 'datetime',
        'handed_over_at' => 'datetime',
        'delivered_at' => 'datetime',
        'confirmed_by_seller_at' => 'datetime',
    ];

    public static function statusLifecycle(): array
    {
        return self::STATUS_LIFECYCLE;
    }

    public function buyer()   { return $this->belongsTo(User::class, 'buyer_id'); }
    public function seller()  { return $this->belongsTo(User::class, 'seller_id'); }
    public function logistics() { return $this->belongsTo(User::class, 'logistics_id'); }
    public function courier() { return $this->belongsTo(User::class, 'courier_id'); }
    public function product() { return $this->belongsTo(Product::class); }
    public function originBranch() { return $this->belongsTo(LogisticsBranch::class, 'origin_branch_id'); }
    public function destinationBranch() { return $this->belongsTo(LogisticsBranch::class, 'destination_branch_id'); }
    public function destinationBarangay() { return $this->belongsTo(Barangay::class, 'destination_barangay_id'); }
}
