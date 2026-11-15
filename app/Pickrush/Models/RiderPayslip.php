<?php

namespace App\Pickrush\Models;

use App\Pickrush\Support\Audited;
use App\Pickrush\Support\CompanyOwned;
use App\Pickrush\Support\HubOwned;
use Illuminate\Database\Eloquent\Model;

/**
 * A rider's pay for one week (Monday to Sunday): the week's earnings less its deductions. It is
 * closed at the cutoff, then released by the station manager (or by itself on Tuesday); only a
 * released payslip's net counts toward what the rider can withdraw.
 */
class RiderPayslip extends Model
{
    use Audited, CompanyOwned, HubOwned;

    public const CLOSED = 'closed';

    public const RELEASED = 'released';

    protected $table = 'pr_rider_payslips';

    protected array $companyThrough = ['station_hub_id' => 'pr_hubs'];

    protected $guarded = ['id'];

    protected string $hubColumn = 'station_hub_id';

    protected $casts = [
        'week_start' => 'date',
        'week_end' => 'date',
        'earned' => 'decimal:2',
        'deductions' => 'decimal:2',
        'net' => 'decimal:2',
        'closed_at' => 'datetime',
        'released_at' => 'datetime',
    ];

    public function rider()
    {
        return $this->belongsTo(Rider::class)->withoutGlobalScopes();
    }

    public function station()
    {
        return $this->belongsTo(Hub::class, 'station_hub_id')->withoutGlobalScopes();
    }

    public function releaser()
    {
        return $this->belongsTo(Staff::class, 'released_by');
    }

    public function deductionItems()
    {
        return $this->hasMany(RiderDeduction::class, 'payslip_id');
    }

    public function isReleased(): bool
    {
        return $this->status === self::RELEASED;
    }
}
