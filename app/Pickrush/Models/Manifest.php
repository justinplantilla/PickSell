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

    protected $casts = ['dispatched_at' => 'datetime', 'received_at' => 'datetime', 'closed_at' => 'datetime'];

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

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class)->withoutGlobalScopes();
    }

    public function driver()
    {
        return $this->belongsTo(Driver::class)->withoutGlobalScopes();
    }

    public function getRouteKeyName(): string
    {
        return 'manifest_no';
    }
}
