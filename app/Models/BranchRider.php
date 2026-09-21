<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BranchRider extends Model
{
    protected $fillable = ['branch_id', 'user_id', 'status'];

    public function branch() { return $this->belongsTo(LogisticsBranch::class); }
    public function rider() { return $this->belongsTo(User::class, 'user_id'); }
    public function barangays() { return $this->hasMany(RiderBarangay::class); }
}