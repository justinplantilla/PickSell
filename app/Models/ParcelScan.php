<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ParcelScan extends Model
{
    protected $fillable = [
        'order_id',
        'scanned_by',
        'scan_type',
        'location',
        'notes',
        'scanned_at',
    ];

    protected $casts = [
        'scanned_at' => 'datetime',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function scanner()
    {
        return $this->belongsTo(User::class, 'scanned_by');
    }
}
