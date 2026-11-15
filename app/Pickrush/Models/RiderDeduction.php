<?php

namespace App\Pickrush\Models;

use App\Pickrush\Support\Audited;
use App\Pickrush\Support\CompanyOwned;
use Illuminate\Database\Eloquent\Model;

/**
 * Taken off a rider's pay: cash short at a remittance, or a parcel the rider damaged or lost (a
 * carrier fault that is not the rider's comes off the company's share instead). It lands on the
 * payslip of the week it is open in. The rider can dispute it before that payslip is released; a
 * disputed one waits for the station manager, who upholds it (it lands on the next payslip) or
 * waives it.
 */
class RiderDeduction extends Model
{
    use Audited, CompanyOwned;

    public const ACTIVE = 'active';

    public const DISPUTED = 'disputed';

    public const WAIVED = 'waived';

    public const KINDS = ['shortage' => 'Cash short', 'damage' => 'Damaged parcel', 'lost' => 'Lost parcel', 'other' => 'Other'];

    protected $table = 'pr_rider_deductions';

    protected array $companyThrough = ['rider_id' => 'pr_riders'];

    protected $guarded = ['id'];

    protected $casts = [
        'amount' => 'decimal:2',
        'disputed_at' => 'datetime',
        'decided_at' => 'datetime',
        'occurred_at' => 'datetime',
    ];

    public function rider()
    {
        return $this->belongsTo(Rider::class)->withoutGlobalScopes();
    }

    public function payslip()
    {
        return $this->belongsTo(RiderPayslip::class, 'payslip_id')->withoutGlobalScopes();
    }

    public function parcel()
    {
        return $this->belongsTo(Parcel::class);
    }

    public function label(): string
    {
        return self::KINDS[$this->kind] ?? ucfirst($this->kind);
    }
}
