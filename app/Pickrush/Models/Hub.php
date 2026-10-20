<?php

namespace App\Pickrush\Models;

use App\Models\Geo\Municipality;
use App\Pickrush\Enums\HubType;
use App\Pickrush\Enums\StaffRole;
use App\Pickrush\Support\Audited;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** A province hub (sorting center) or a station hub (one city/municipality). */
class Hub extends Model
{
    use Audited;

    protected $table = 'pr_hubs';

    protected $guarded = [];

    protected $casts = ['type' => HubType::class, 'is_active' => 'boolean'];

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_hub_id');
    }

    public function childStations()
    {
        return $this->hasMany(self::class, 'parent_hub_id');
    }

    public function staff()
    {
        return $this->hasMany(Staff::class, 'hub_id');
    }

    public function municipality()
    {
        return $this->belongsTo(Municipality::class, 'psgc_code', 'code');
    }

    public function scopeProvinces(Builder $query): Builder
    {
        return $query->where('type', HubType::Province);
    }

    public function scopeStations(Builder $query): Builder
    {
        return $query->where('type', HubType::Station);
    }

    public function isStation(): bool
    {
        return $this->type === HubType::Station;
    }

    public function isProvince(): bool
    {
        return $this->type === HubType::Province;
    }

    /** The province hub this hub sorts through (itself for a province hub). */
    public function provinceHub(): self
    {
        return $this->isProvince() ? $this : $this->parent;
    }

    /**
     * What still stops this hub from operating. Every hub is active; readiness tells HQ
     * which ones still need people. A station also needs a zone and an approved rider.
     *
     * @return list<string>
     */
    public function readinessGaps(): array
    {
        $gaps = [];
        if (! $this->staff()->where('role', StaffRole::HubManager)->where('is_active', true)->where('is_sample', false)->exists()) {
            $gaps[] = 'No active hub manager';
        }
        if ($this->isStation()) {
            if (! Zone::withoutGlobalScopes()->where('station_hub_id', $this->id)->exists()) {
                $gaps[] = 'No delivery zone set up';
            }
            if (! Rider::withoutGlobalScopes()->where('station_hub_id', $this->id)->where('status', 'active')->exists()) {
                $gaps[] = 'No approved rider';
            }
        }

        return $gaps;
    }
}
