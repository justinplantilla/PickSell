<?php

namespace App\Pickrush\Models;

use App\Pickrush\Support\Audited;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** Pickrush's fee for parcels up to max_weight_kg on a route (or, without an origin, to a destination), valid between two dates. */
class ShippingRate extends Model
{
    use Audited;

    protected $table = 'pr_shipping_rates';

    protected $guarded = ['id'];

    protected $casts = [
        'max_weight_kg' => 'decimal:2',
        'fee' => 'decimal:2',
        'effective_from' => 'date',
        'effective_to' => 'date',
    ];

    /** Rows in force on a date (effective_to is the last day a row applies). */
    public function scopeEffectiveOn(Builder $query, string $date): void
    {
        $query->whereDate('effective_from', '<=', $date)
            ->where(fn ($q) => $q->whereNull('effective_to')->orWhereDate('effective_to', '>=', $date));
    }
}
