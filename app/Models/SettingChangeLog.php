<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SettingChangeLog extends Model
{
    protected $fillable = [
        'setting_key',
        'old_value',
        'new_value',
        'changed_by',
    ];

    public function changedBy()
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
