<?php

namespace App\Pickrush\Models;

use App\Pickrush\Support\Audited;
use App\Pickrush\Support\CompanyOwned;
use App\Pickrush\Support\HubOwned;
use Illuminate\Database\Eloquent\Model;

/**
 * End-of-day cash handed from a rider to the station: system-expected (COD less the rider pay kept)
 * against counted. The staff count waits for the rider to confirm it in the rider app
 * (awaiting_rider); then it is reconciled, or disputed when the count differs from the system total.
 * A rejected count is void and the cash is counted again.
 */
class CodRemittance extends Model
{
    use Audited, CompanyOwned, HubOwned;

    public const AWAITING_RIDER = 'awaiting_rider';

    public const REJECTED = 'rejected';

    protected $table = 'pr_cod_remittances';

    protected array $companyThrough = ['station_hub_id' => 'pr_hubs'];

    protected $guarded = ['id'];

    protected string $hubColumn = 'station_hub_id';

    protected $casts = [
        'remit_date' => 'date',
        'expected_amount' => 'decimal:2',
        'rider_pay_kept' => 'decimal:2',
        'received_amount' => 'decimal:2',
        'variance' => 'decimal:2',
        'resolved_at' => 'datetime',
        'rider_responded_at' => 'datetime',
    ];

    public function rider()
    {
        return $this->belongsTo(Rider::class)->withoutGlobalScopes();
    }

    public function receiver()
    {
        return $this->belongsTo(Staff::class, 'received_by');
    }

    public function assignments()
    {
        return $this->hasMany(Assignment::class);
    }
}
