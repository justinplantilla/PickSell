<?php

namespace App\Pickrush\Models;

use Illuminate\Database\Eloquent\Model;

/** A 6-digit code emailed to someone applying as a rider, and the token they get for entering it. */
class RiderEmailCode extends Model
{
    protected $table = 'pr_rider_email_codes';

    protected $guarded = ['id'];

    protected $casts = ['expires_at' => 'datetime', 'verified_at' => 'datetime'];
}
