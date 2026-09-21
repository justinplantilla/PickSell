<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Municipality extends Model
{
    protected $fillable = ['province', 'name', 'code'];

    public function barangays() { return $this->hasMany(Barangay::class); }
    public function branches() { return $this->hasMany(LogisticsBranch::class); }
}