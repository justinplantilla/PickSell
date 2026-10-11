<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeliveryAssignment extends Model
{
    protected $fillable = [
        'delivery_id',
        'rider_id',
        'status',
        'offered_at',
        'responded_at',
        'expires_at',
        'rejection_reason',
    ];

    protected $casts = [
        'offered_at' => 'datetime',
        'responded_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function delivery()
    {
        return $this->belongsTo(Delivery::class);
    }

    public function rider()
    {
        return $this->belongsTo(User::class, 'rider_id');
    }

}
