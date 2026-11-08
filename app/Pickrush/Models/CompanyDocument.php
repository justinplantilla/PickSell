<?php

namespace App\Pickrush\Models;

use Illuminate\Database\Eloquent\Model;

/** A document a logistics company submitted with its registration, kept on the private disk. */
class CompanyDocument extends Model
{
    public const TYPES = [
        'valid_id' => 'Valid ID',
        'dti_permit' => 'Business / DTI permit',
    ];

    /** Documents never sit on a public disk; only administrators stream them. */
    public const DISK = 'local';

    protected $table = 'pr_company_documents';

    protected $guarded = ['id'];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}
