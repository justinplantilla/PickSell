<?php

namespace App\Pickrush\Models;

use App\Pickrush\Support\Actor;
use App\Pickrush\Support\Audited;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Something wrong with a parcel or a trip: missing, damaged, mis-sorted, unlabeled, or no route.
 * Each type has its own set of resolutions (see ExceptionDesk).
 */
class ParcelException extends Model
{
    use Audited;

    protected $table = 'pr_exceptions';

    protected $guarded = ['id'];

    protected $casts = ['resolved_at' => 'datetime'];

    public const TYPES = [
        'missing' => 'Missing from trip',
        'damaged' => 'Damaged packaging',
        'mis_sorted' => 'Mis-sorted (arrived at the wrong hub)',
        'unlabeled' => 'Unlabeled or unreadable waybill',
        'no_route' => 'No route (destination unknown or inactive)',
    ];

    protected static function booted(): void
    {
        // A hub sees what it found and what was reported against it.
        static::addGlobalScope('hub', function (Builder $query) {
            $staff = Actor::staff();
            if ($staff && ! $staff->isCompany()) {
                $query->where(fn ($q) => $q->where('pr_exceptions.hub_id', $staff->hub_id)->orWhere('pr_exceptions.against_hub_id', $staff->hub_id));
            }
        });
    }

    public function parcel()
    {
        return $this->belongsTo(Parcel::class)->withoutGlobalScopes();
    }

    public function hub()
    {
        return $this->belongsTo(Hub::class);
    }

    public function againstHub()
    {
        return $this->belongsTo(Hub::class, 'against_hub_id');
    }

    public function manifest()
    {
        return $this->belongsTo(Manifest::class)->withoutGlobalScopes();
    }

    public function label(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }
}
