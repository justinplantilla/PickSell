<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Complaint extends Model
{
    protected $fillable = [
        'filed_by', 'against_user_id', 'subject', 'details',
        'evidence_path', 'status', 'admin_notes',
    ];

    public function filer()   { return $this->belongsTo(User::class, 'filed_by'); }
    public function against() { return $this->belongsTo(User::class, 'against_user_id'); }
}
