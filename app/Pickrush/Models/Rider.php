<?php

namespace App\Pickrush\Models;

use App\Pickrush\Support\Audited;
use App\Pickrush\Support\HubOwned;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A rider attached to one station hub. Accounts are created by the station (never self sign-up),
 * verified by a second person, then approved. Riders sign in with the rider app (rider API).
 */
class Rider extends Model
{
    use Audited, HubOwned;

    protected $table = 'pr_riders';

    protected $guarded = ['id'];

    protected string $hubColumn = 'station_hub_id';

    protected $hidden = ['device_id'];

    protected $casts = [
        'birthdate' => 'date',
        'cod_limit' => 'decimal:2',
        'on_probation' => 'boolean',
        'verified_at' => 'datetime',
        'approved_at' => 'datetime',
    ];

    public const STATUSES = ['pending', 'under_review', 'active', 'suspended', 'deactivated', 'rejected'];

    public function station()
    {
        return $this->belongsTo(Hub::class, 'station_hub_id');
    }

    public function zone()
    {
        return $this->belongsTo(Zone::class);
    }

    public function documents()
    {
        return $this->hasMany(RiderDocument::class);
    }

    public function assignments()
    {
        return $this->hasMany(Assignment::class);
    }

    public function creator()
    {
        return $this->belongsTo(Staff::class, 'created_by');
    }

    public function verifier()
    {
        return $this->belongsTo(Staff::class, 'verified_by');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isOnShift(): bool
    {
        return $this->shift_status === 'on_shift';
    }

    /** Open tasks: assigned or out for delivery. */
    public function openAssignments()
    {
        return $this->assignments()->whereIn('status', ['assigned', 'out_for_delivery']);
    }

    /** A cash shortage or overage not yet resolved by the hub manager blocks new dispatch. */
    public function hasOpenVariance(): bool
    {
        return CodRemittance::where('rider_id', $this->id)->where('status', 'disputed')->exists();
    }
}
