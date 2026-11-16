<?php

namespace App\Pickrush\Models;

use App\Pickrush\Support\Actor;
use App\Pickrush\Support\Audited;
use App\Pickrush\Support\CompanyOwned;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * One truck run from a province hub to several of its stations. Each stop is its own trip
 * (manifest), in stop order; the truck and driver stay on the road until the last stop arrives.
 */
class Route extends Model
{
    use Audited, CompanyOwned;

    protected $table = 'pr_routes';

    protected array $companyThrough = ['hub_id' => 'pr_hubs'];

    protected $guarded = ['id'];

    protected $casts = ['dispatched_at' => 'datetime', 'completed_at' => 'datetime'];

    protected static function booted(): void
    {
        // Hub staff see the routes their hub sends, or that stop at their hub.
        static::addGlobalScope('hub', function (Builder $query) {
            $staff = Actor::staff();
            if ($staff && ! $staff->isCompany()) {
                $query->where(fn ($q) => $q->where('pr_routes.hub_id', $staff->hub_id)
                    ->orWhereHas('stops', fn ($s) => $s->withoutGlobalScope('hub')->where('to_hub_id', $staff->hub_id)));
            }
        });
    }

    public function hub()
    {
        return $this->belongsTo(Hub::class);
    }

    /** The stops' trips, in the order the truck reaches them. */
    public function stops()
    {
        return $this->hasMany(Manifest::class)->withoutGlobalScope('hub')->orderBy('stop_order');
    }

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class)->withoutGlobalScopes();
    }

    public function driver()
    {
        return $this->belongsTo(Driver::class)->withoutGlobalScopes();
    }

    /** The next stop the truck has not reached yet, while it is on the road. */
    public function nextStop(): ?Manifest
    {
        return $this->status === 'dispatched' ? $this->stops->first(fn (Manifest $stop) => $stop->arrived_at === null) : null;
    }

    /** Where the truck is along the route, in a line: for HQ, the stations and the driver's sheet. */
    public function position(): string
    {
        $stops = $this->stops;
        $arrived = $stops->whereNotNull('arrived_at');
        $next = $this->nextStop();

        return match (true) {
            $this->status === 'draft' => 'Being loaded',
            $this->status === 'completed' => "All {$stops->count()} stops reached",
            $arrived->isEmpty() => "On the way to {$next?->toHub?->name} (stop 1 of {$stops->count()})",
            default => "Dropped at {$arrived->sortBy('stop_order')->last()->toHub?->name} ({$arrived->count()} of {$stops->count()})"
                .($next ? ", heading to {$next->toHub?->name}" : ''),
        };
    }

    public function getRouteKeyName(): string
    {
        return 'route_no';
    }
}
