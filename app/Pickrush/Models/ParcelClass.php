<?php

namespace App\Pickrush\Models;

use App\Pickrush\Services\OperationRefused;
use Illuminate\Database\Eloquent\Model;

/**
 * STANDARD / HEAVY / BULKY: a chargeable-weight band (above min, up to and including max) and
 * what the delivery rider earns for it. No class may pay below config('pickrush.shipping.min_rider_pay').
 */
class ParcelClass extends Model
{
    protected $table = 'pr_parcel_classes';

    protected $primaryKey = 'code';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $guarded = [];

    protected $casts = [
        'min_kg_exclusive' => 'decimal:2',
        'max_kg_inclusive' => 'decimal:2',
        'rider_pay' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $class) {
            $minimum = number_format((float) config('pickrush.shipping.min_rider_pay'), 2, '.', '');
            if (bccomp((string) $class->rider_pay, $minimum, 2) < 0) {
                throw new OperationRefused("Rider pay for {$class->code} cannot be below the ₱{$minimum} minimum.");
            }
        });
    }
}
