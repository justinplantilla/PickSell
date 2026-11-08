<?php

namespace App\Pickrush\Models;

use App\Pickrush\Support\CompanyOwned;
use Illuminate\Database\Eloquent\Model;

class ManifestParcel extends Model
{
    use CompanyOwned;

    protected $table = 'pr_manifest_parcels';

    protected array $companyThrough = ['parcel_id' => 'pr_parcels'];

    protected $guarded = ['id'];

    public $timestamps = false;

    protected $casts = ['scanned_at' => 'datetime'];

    public function manifest()
    {
        return $this->belongsTo(Manifest::class);
    }

    public function scanner()
    {
        return $this->belongsTo(Staff::class, 'scanned_by');
    }

    public function parcel()
    {
        return $this->belongsTo(Parcel::class)->withoutGlobalScopes();
    }
}
