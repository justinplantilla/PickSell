<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RiderBarangay extends Model
{
    protected $fillable = ['branch_rider_id', 'barangay_id', 'is_primary'];
    protected $casts = ['is_primary' => 'boolean'];

    public function assignment() { return $this->belongsTo(BranchRider::class, 'branch_rider_id'); }
    public function barangay() { return $this->belongsTo(Barangay::class); }
}