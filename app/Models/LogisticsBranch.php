<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LogisticsBranch extends Model
{
    protected $fillable = ['municipality_id', 'logistics_id', 'name', 'address', 'status'];

    public function municipality() { return $this->belongsTo(Municipality::class); }
    public function logistics() { return $this->belongsTo(User::class, 'logistics_id'); }
    public function riderAssignments() { return $this->hasMany(BranchRider::class, 'branch_id'); }
}