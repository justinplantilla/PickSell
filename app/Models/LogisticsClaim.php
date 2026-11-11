<?php

namespace App\Models;

use App\Pickrush\Models\Company;
use Illuminate\Database\Eloquent\Model;

/** What a logistics company owes for a parcel it lost or damaged; deducted from its share. */
class LogisticsClaim extends Model
{
    /** PlatformSetting key for the most one claim may be. */
    public const CAP_SETTING = 'logistics_claim_cap';

    public const DEFAULT_CAP = 5000;

    protected $guarded = ['id'];

    protected $casts = [
        'item_value' => 'decimal:2',
        'shipping_fee' => 'decimal:2',
        'amount' => 'decimal:2',
        'capped' => 'boolean',
        'waived_at' => 'datetime',
    ];

    public function issue()
    {
        return $this->belongsTo(LogisticsIssue::class, 'logistics_issue_id');
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public static function cap(): float
    {
        $value = PlatformSetting::get(self::CAP_SETTING, (string) self::DEFAULT_CAP);

        return is_numeric($value) && (float) $value > 0 ? (float) $value : (float) self::DEFAULT_CAP;
    }
}
