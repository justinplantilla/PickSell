<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LogisticsException extends Model
{
    public const TYPES = [
        'failed_delivery',
        'missing_scan',
        'wrong_destination',
        'rider_unavailable',
    ];

    protected $fillable = [
        'order_id',
        'opened_by',
        'type',
        'status',
        'description',
        'resolution',
        'resolved_by',
        'resolved_at',
    ];

    protected $casts = [
        'resolved_at' => 'datetime',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function opener()
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    public function resolver()
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
