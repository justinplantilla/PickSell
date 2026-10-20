<?php

namespace App\Pickrush\Models;

use App\Pickrush\Support\Audited;
use App\Pickrush\Support\HubOwned;
use Illuminate\Database\Eloquent\Model;

/** Cash the station sends on to the company, confirmed by HQ finance. */
class HubRemittance extends Model
{
    use Audited, HubOwned;

    protected $table = 'pr_hub_remittances';

    protected $guarded = ['id'];

    protected string $hubColumn = 'station_hub_id';

    protected $casts = [
        'expected_amount' => 'decimal:2',
        'remitted_amount' => 'decimal:2',
        'variance' => 'decimal:2',
        'confirmed_at' => 'datetime',
    ];

    public function station()
    {
        return $this->belongsTo(Hub::class, 'station_hub_id');
    }

    public function riderRemittances()
    {
        return $this->hasMany(CodRemittance::class);
    }
}
