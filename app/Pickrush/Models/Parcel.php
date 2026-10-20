<?php

namespace App\Pickrush\Models;

use App\Pickrush\Enums\ParcelStatus;
use App\Pickrush\Support\Audited;
use App\Pickrush\Support\ParcelHubScope;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;

/**
 * One physical parcel. Status, current hub and next hub are cached from the event log; change
 * them only through ParcelLedger::record(), never by updating these columns directly.
 */
#[ScopedBy(ParcelHubScope::class)]
class Parcel extends Model
{
    use Audited;

    protected $table = 'pr_parcels';

    protected $guarded = ['id'];

    protected $casts = [
        'current_status' => ParcelStatus::class,
        'cod_amount' => 'decimal:2',
        'shipping_fee' => 'decimal:2',
        'weight_kg' => 'decimal:2',
        'needs_address_fix' => 'boolean',
        'is_return' => 'boolean',
        'delivered_at' => 'datetime',
    ];

    public function events()
    {
        return $this->hasMany(ParcelEvent::class)->orderBy('occurred_at')->orderBy('id');
    }

    public function originHub()
    {
        return $this->belongsTo(Hub::class, 'origin_hub_id');
    }

    public function destHub()
    {
        return $this->belongsTo(Hub::class, 'dest_hub_id');
    }

    public function currentHub()
    {
        return $this->belongsTo(Hub::class, 'current_hub_id');
    }

    public function nextHub()
    {
        return $this->belongsTo(Hub::class, 'next_hub_id');
    }

    public function assignments()
    {
        return $this->hasMany(Assignment::class)->latest('id');
    }

    public function openAssignment()
    {
        return $this->hasOne(Assignment::class)->whereIn('status', Assignment::OPEN)->latestOfMany();
    }

    /** Where the rider takes it: the buyer, or the seller when it is coming back. */
    public function dropOffBarangayCode(): ?string
    {
        return $this->is_return ? $this->pickup_barangay_code : $this->delivery_barangay_code;
    }

    public function getRouteKeyName(): string
    {
        return 'tracking_no';
    }
}
