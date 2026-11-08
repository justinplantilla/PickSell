<?php

namespace App\Pickrush\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/** One step in a logistics company's review history: an administrator's decision or a resubmission. */
class CompanyReview extends Model
{
    public const UPDATED_AT = null;

    public const LABELS = [
        'approved' => 'Approved',
        'rejected' => 'Rejected',
        'suspended' => 'Suspended',
        'reinstated' => 'Reinstated',
        'resubmitted' => 'Resubmitted',
    ];

    protected $table = 'pr_company_reviews';

    protected $guarded = ['id'];

    protected $casts = ['created_at' => 'datetime'];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }
}
