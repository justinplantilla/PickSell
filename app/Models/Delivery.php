<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Delivery extends Model
{
    protected $fillable = [
        'order_id',
        'tracking_number',
        'status',
        'origin_branch_id',
        'destination_branch_id',
        'destination_barangay_id',
        'delivery_attempts',
        'picked_up_at',
        'sorting_at',
        'out_for_delivery_at',
        'delivered_at',
        'returned_at',
    ];

    protected $casts = [
        'delivery_attempts' => 'integer',
        'picked_up_at' => 'datetime',
        'sorting_at' => 'datetime',
        'out_for_delivery_at' => 'datetime',
        'delivered_at' => 'datetime',
        'returned_at' => 'datetime',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function assignments()
    {
        return $this->hasMany(DeliveryAssignment::class);
    }

    public function currentAssignment()
    {
        return $this->hasOne(DeliveryAssignment::class)
            ->where('status', 'accepted')
            ->latestOfMany('responded_at');
    }

    public function logs()
    {
        return $this->hasMany(DeliveryLog::class)->oldest('created_at')->oldest('id');
    }

    public function originBranch()
    {
        return $this->belongsTo(LogisticsBranch::class, 'origin_branch_id');
    }

    public function destinationBranch()
    {
        return $this->belongsTo(LogisticsBranch::class, 'destination_branch_id');
    }

    public function destinationBarangay()
    {
        return $this->belongsTo(Barangay::class, 'destination_barangay_id');
    }
}
