<?php

namespace App\Pickrush\Models;

use App\Models\Geo\Barangay;
use App\Pickrush\Support\Audited;
use App\Pickrush\Support\HubOwned;
use Illuminate\Database\Eloquent\Model;

/** A group of barangays inside one station's municipality, with a default and a backup rider. */
class Zone extends Model
{
    use Audited, HubOwned;

    protected $table = 'pr_zones';

    protected $guarded = ['id'];

    protected string $hubColumn = 'station_hub_id';

    public function station()
    {
        return $this->belongsTo(Hub::class, 'station_hub_id');
    }

    public function defaultRider()
    {
        return $this->belongsTo(Rider::class, 'default_rider_id');
    }

    public function backupRider()
    {
        return $this->belongsTo(Rider::class, 'backup_rider_id');
    }

    public function barangayLinks()
    {
        return $this->hasMany(ZoneBarangay::class);
    }

    public function barangays()
    {
        return $this->belongsToMany(Barangay::class, 'pr_zone_barangays', 'zone_id', 'barangay_code', 'id', 'code');
    }
}
