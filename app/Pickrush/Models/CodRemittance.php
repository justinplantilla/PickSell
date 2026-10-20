<?php

namespace App\Pickrush\Models;

use App\Pickrush\Support\Audited;
use App\Pickrush\Support\HubOwned;
use Illuminate\Database\Eloquent\Model;

/** End-of-day cash handed from a rider to the station: system-expected against counted. */
class CodRemittance extends Model
{
    use Audited, HubOwned;

    protected $table = 'pr_cod_remittances';

    protected $guarded = ['id'];

    protected string $hubColumn = 'station_hub_id';

    protected $casts = [
        'remit_date' => 'date',
        'expected_amount' => 'decimal:2',
        'received_amount' => 'decimal:2',
        'variance' => 'decimal:2',
        'resolved_at' => 'datetime',
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
