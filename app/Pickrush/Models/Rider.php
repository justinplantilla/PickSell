<?php

namespace App\Pickrush\Models;

use App\Pickrush\Support\Audited;
use App\Pickrush\Support\CompanyOwned;
use App\Pickrush\Support\HubOwned;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A rider attached to one station hub. Riders apply in the rider app (email and password) and stay
 * pending until the station hub manager approves; only an active rider can be given parcels.
 */
class Rider extends Model
{
    use Audited, CompanyOwned, HubOwned;

    protected $table = 'pr_riders';

    protected $guarded = ['id'];

    protected string $hubColumn = 'station_hub_id';

    /** Minutes without a check-in from the rider app before an on-shift rider goes offline. */
    public const IDLE_MINUTES = 5;

    protected $hidden = ['device_id', 'password'];

    protected $casts = [
        'last_seen_at' => 'datetime',
        'birthdate' => 'date',
        'cod_limit' => 'decimal:2',
        'on_probation' => 'boolean',
        'verified_at' => 'datetime',
        'approved_at' => 'datetime',
        'applied_at' => 'datetime',
        'password' => 'hashed',
    ];

    public const STATUSES = ['pending', 'under_review', 'active', 'suspended', 'deactivated', 'rejected'];

    /** Vehicles a rider can apply with. */
    public const VEHICLE_TYPES = ['motorcycle' => 'Motorcycle', 'tricycle' => 'Tricycle'];

    /** "Motorcycle · NBC 1234", as buyers see the rider bringing their parcel; null when neither is known. */
    public function vehicleLabel(): ?string
    {
        $label = collect([self::VEHICLE_TYPES[$this->vehicle_type] ?? null, $this->plate_no])->filter()->join(' · ');

        return $label !== '' ? $label : null;
    }

    protected static function booted(): void
    {
        // The rest of Pickrush shows full_name; keep it in step with the name the rider gave.
        static::saving(function (Rider $rider) {
            if ($rider->first_name !== null || $rider->last_name !== null) {
                $rider->full_name = trim("{$rider->first_name} {$rider->last_name}");
            }
        });
    }

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

    public function tokens()
    {
        return $this->hasMany(RiderToken::class);
    }

    public function verifier()
    {
        return $this->belongsTo(Staff::class, 'verified_by');
    }

    public function approver()
    {
        return $this->belongsTo(Staff::class, 'approved_by');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    /** Waiting for the hub manager (under_review is from the old two-person check). */
    public function isApplication(): bool
    {
        return in_array($this->status, ['pending', 'under_review'], true);
    }

    public function appliedInApp(): bool
    {
        return $this->created_by === null;
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /** On shift and the app was open recently; a rider who closed the app is not offered tasks. */
    public function isOnShift(): bool
    {
        return $this->shift_status === 'on_shift' && ! $this->isIdle();
    }

    /** The rider app has not checked in for IDLE_MINUTES (closed, or killed by the phone). */
    public function isIdle(): bool
    {
        return ! $this->last_seen_at || $this->last_seen_at->lt(now()->subMinutes(self::IDLE_MINUTES));
    }

    /** Signed in to the rider app on at least one device, so new tasks actually reach them. */
    public function isSignedIn(): bool
    {
        return $this->tokens_exists ?? $this->tokens()->exists();
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

    /** A new rider without a company in context takes the company of its hub. */
    protected function inferCompanyId(): ?int
    {
        $hubId = $this->station_hub_id;

        return $hubId ? Hub::withoutGlobalScopes()->whereKey($hubId)->value('company_id') : null;
    }
}
