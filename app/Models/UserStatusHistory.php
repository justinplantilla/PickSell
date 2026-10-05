<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One change of users.status, written automatically by User::booted() whenever the status changes. */
class UserStatusHistory extends Model
{
    protected $fillable = ['user_id', 'changed_by', 'from_status', 'to_status', 'reason'];

    public function user()    { return $this->belongsTo(User::class); }
    public function changer() { return $this->belongsTo(User::class, 'changed_by'); }
}
