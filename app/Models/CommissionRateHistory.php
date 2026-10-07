<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CommissionRateHistory extends Model
{
    protected $fillable = ['rate', 'changed_by', 'effective_from', 'effective_until'];

    protected $casts = [
        'rate' => 'decimal:2',
        'effective_from' => 'datetime',
        'effective_until' => 'datetime',
    ];

    public function changedBy()
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
