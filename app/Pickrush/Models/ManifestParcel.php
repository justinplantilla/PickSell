<?php

namespace App\Pickrush\Models;

use Illuminate\Database\Eloquent\Model;

class ManifestParcel extends Model
{
    protected $table = 'pr_manifest_parcels';

    protected $guarded = ['id'];

    public $timestamps = false;

    protected $casts = ['scanned_at' => 'datetime'];

    public function manifest()
    {
        return $this->belongsTo(Manifest::class);
    }

    public function parcel()
    {
        return $this->belongsTo(Parcel::class)->withoutGlobalScopes();
    }
}
