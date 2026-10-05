<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One Admin decision on a registration application. Kept as history; never edited. */
class RegistrationReview extends Model
{
    public const APPROVED = 'approved';
    public const DISAPPROVED = 'disapproved';

    protected $fillable = ['user_id', 'reviewed_by', 'decision', 'reason', 'verification_snapshot', 'reviewed_at'];

    protected $casts = [
        'verification_snapshot' => 'array',
        'reviewed_at' => 'datetime',
    ];

    public function applicant() { return $this->belongsTo(User::class, 'user_id'); }
    public function reviewer()  { return $this->belongsTo(User::class, 'reviewed_by'); }
}
