<?php

namespace App\Pickrush\Models;

use App\Pickrush\Support\Audited;
use App\Pickrush\Support\CompanyOwned;
use App\Pickrush\Support\HubOwned;
use Illuminate\Database\Eloquent\Model;

/**
 * A company truck or van kept at a hub for trips, with the driver who usually takes it and its LTO
 * papers: the CR (ownership) and the OR of its yearly registration.
 */
class Vehicle extends Model
{
    use Audited, CompanyOwned, HubOwned;

    protected $table = 'pr_vehicles';

    protected array $companyThrough = ['hub_id' => 'pr_hubs'];

    protected $guarded = ['id'];

    protected $casts = ['is_active' => 'boolean', 'registration_expiry' => 'date'];

    public function hub()
    {
        return $this->belongsTo(Hub::class);
    }

    public function defaultDriver()
    {
        return $this->belongsTo(Driver::class, 'default_driver_id');
    }

    public function renewals()
    {
        return $this->hasMany(FleetRenewal::class)->latest('id');
    }

    /** The CR and OR numbers and the OR's expiry are on file. */
    public function hasPapers(): bool
    {
        return $this->cr_no && $this->or_no && $this->registration_expiry;
    }

    public function isRegistrationExpired(): bool
    {
        return $this->registration_expiry?->lt(today()) ?? false;
    }

    /** Active, with its papers on file and its registration current: the only trucks a trip can leave with. */
    public function canTravel(): bool
    {
        return $this->is_active && $this->hasPapers() && ! $this->isRegistrationExpired();
    }

    /** How many active vehicles a hub may keep: a station runs one, a province hub up to three. */
    public static function limitFor(Hub $hub): int
    {
        return (int) config('pickrush.fleet.max_vehicles.'.$hub->type->value, 1);
    }
}
