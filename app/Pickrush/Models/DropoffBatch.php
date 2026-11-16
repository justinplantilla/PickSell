<?php

namespace App\Pickrush\Models;

use App\Models\User;
use App\Pickrush\Enums\ParcelStatus;
use App\Pickrush\Support\ParcelHubScope;
use Illuminate\Database\Eloquent\Model;

/** Several parcels one seller brings to their station together, on one manifest (DO…). */
class DropoffBatch extends Model
{
    protected $table = 'pr_dropoff_batches';

    protected $guarded = [];

    protected $casts = ['received_at' => 'datetime'];

    public function originHub()
    {
        return $this->belongsTo(Hub::class, 'origin_hub_id');
    }

    public function seller()
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function receiver()
    {
        return $this->belongsTo(Staff::class, 'received_by');
    }

    public function parcels()
    {
        return $this->hasMany(Parcel::class, 'dropoff_batch_id')->withoutGlobalScope(ParcelHubScope::class)->orderBy('id');
    }

    /** The parcel is at the station (or beyond): it is no longer waiting for the seller. */
    public static function arrived(Parcel $parcel): bool
    {
        return ! in_array($parcel->current_status, [ParcelStatus::AwaitingDropoff, ParcelStatus::LabelCreated, ParcelStatus::Cancelled], true);
    }
}
