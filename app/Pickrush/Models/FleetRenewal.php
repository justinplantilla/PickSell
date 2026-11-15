<?php

namespace App\Pickrush\Models;

use Illuminate\Database\Eloquent\Model;

/** A truck's OR/CR or a driver's license that was renewed, with what the renewal replaced. */
class FleetRenewal extends Model
{
    protected $table = 'pr_fleet_renewals';

    protected $guarded = ['id'];

    protected $casts = ['previous' => 'array'];

    public function renewer()
    {
        return $this->belongsTo(Staff::class, 'renewed_by');
    }
}
