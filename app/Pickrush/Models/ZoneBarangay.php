<?php

namespace App\Pickrush\Models;

use Illuminate\Database\Eloquent\Model;

class ZoneBarangay extends Model
{
    protected $table = 'pr_zone_barangays';

    protected $guarded = ['id'];

    public $timestamps = false;

    public function zone()
    {
        return $this->belongsTo(Zone::class);
    }
}
