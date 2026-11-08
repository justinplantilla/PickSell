<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One seller's orders from one checkout, shipped as one Pickrush parcel. Holds the delivery fee
 * and its split as quoted at checkout; nothing here is recalculated later.
 */
class OrderShipment extends Model
{
    public const VOID = ['returned', 'cancelled'];

    protected $guarded = ['id'];

    protected $casts = [
        'chargeable_weight_kg' => 'decimal:2',
        'delivery_fee' => 'decimal:2',
        'rider_pay' => 'decimal:2',
        'platform_commission' => 'decimal:2',
        'logistics_share' => 'decimal:2',
        'delivery_commission_rate' => 'decimal:2',
        'delivered_at' => 'datetime',
        'failed_at' => 'datetime',
        'earnings_posted_at' => 'datetime',
    ];

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    /** Orders still travelling in the parcel (a cancelled one has been taken out). */
    public function activeOrders()
    {
        return $this->hasMany(Order::class)->where('status', '!=', 'cancelled');
    }

    /** The logistics company the buyer chose for this parcel. */
    public function logisticsCompany()
    {
        return $this->belongsTo(\App\Pickrush\Models\Company::class, 'logistics_company_id');
    }

    public function buyer()
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function seller()
    {
        return $this->belongsTo(User::class, 'seller_id');
    }
}
