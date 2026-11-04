<?php

namespace App\Pickrush\Models;

use App\Pickrush\Support\Audited;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** The per-kg charge above over_kg (the heaviest tier) to a destination region, valid between two dates. */
class ShippingOverweightRate extends Model
{
    use Audited;

    protected $table = 'pr_shipping_overweight_rates';

    protected $guarded = ['id'];

    protected $casts = [
        'over_kg' => 'decimal:2',
        'per_kg_fee' => 'decimal:2',
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
