<?php

namespace App\Pickrush\Models;

use App\Pickrush\Support\Actor;
use App\Pickrush\Support\Audited;
use App\Pickrush\Support\CompanyOwned;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** A trip batch between two hubs. The receiving hub verifies every parcel against it. */
class Manifest extends Model
{
    use Audited, CompanyOwned;

    protected $table = 'pr_manifests';

    protected array $companyThrough = ['from_hub_id' => 'pr_hubs'];

    protected $guarded = ['id'];

    protected $casts = ['dispatched_at' => 'datetime', 'due_at' => 'datetime', 'arrived_at' => 'datetime',
        'received_at' => 'datetime', 'closed_at' => 'datetime'];

    protected static function booted(): void
    {
        // Hub staff see trips leaving or arriving at their hub only.
        static::addGlobalScope('hub', function (Builder $query) {
            $staff = Actor::staff();
            if ($staff && ! $staff->isCompany()) {
                $query->where(fn ($q) => $q->where('pr_manifests.from_hub_id', $staff->hub_id)->orWhere('pr_manifests.to_hub_id', $staff->hub_id));
            }
        });
    }

    public function fromHub()
    {
        return $this->belongsTo(Hub::class, 'from_hub_id');
    }

    public function toHub()
    {
        return $this->belongsTo(Hub::class, 'to_hub_id');
    }

    public function items()
    {
        return $this->hasMany(ManifestParcel::class);
    }

    /** The multi-stop route this trip is one stop of, if any. */
    public function route()
    {
        return $this->belongsTo(Route::class)->withoutGlobalScopes();
    }

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class)->withoutGlobalScopes();
    }

    public function driver()
    {
        return $this->belongsTo(Driver::class)->withoutGlobalScopes();
    }

    public function arriver()
    {
        return $this->belongsTo(Staff::class, 'arrived_by');
    }

    /** Hours a trip between these hubs has before it is overdue: local within a province, long between two. */
    public static function tripHours(Hub $from, Hub $to): int
    {
        $long = $from->isProvince() && $to->isProvince();

        return (int) config('pickrush.fleet.trip_hours.'.($long ? 'long' : 'local'), $long ? 24 : 6);
    }

    /**
     * Trucks and drivers out on the road: on a single trip not yet received, or on a route whose
     * last stop has not arrived. Rows of [vehicle_id, driver_id].
     *
     * @return \Illuminate\Support\Collection<int, object{vehicle_id: ?int, driver_id: ?int}>
     */
    public static function fleetOnTheRoad(?int $hubId = null)
    {
        $trips = self::withoutGlobalScope('hub')->where('status', 'dispatched')->whereNull('route_id')
            ->when($hubId, fn ($q) => $q->where('from_hub_id', $hubId))->get(['vehicle_id', 'driver_id']);
        $routes = Route::withoutGlobalScope('hub')->where('status', 'dispatched')
            ->when($hubId, fn ($q) => $q->where('hub_id', $hubId))->get(['vehicle_id', 'driver_id']);

        return $trips->toBase()->concat($routes->toBase())
            ->map(fn ($row) => (object) ['vehicle_id' => $row->vehicle_id, 'driver_id' => $row->driver_id])->values();
    }

    /** On the road past its due time, not yet marked arrived. */
    public function isOverdue(): bool
    {
        return $this->status === 'dispatched' && $this->arrived_at === null && $this->due_at?->isPast();
    }

    public function scopeOverdue(Builder $query): Builder
    {
        return $query->where('status', 'dispatched')->whereNull('arrived_at')->where('due_at', '<', now());
    }

    public function getRouteKeyName(): string
    {
        return 'manifest_no';
    }
}
