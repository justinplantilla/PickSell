<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

class DeliveryLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'delivery_id',
        'actor_id',
        'actor_role',
        'from_status',
        'to_status',
        'note',
        'proof_image_url',
        'latitude',
        'longitude',
    ];

    protected $casts = [
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
    ];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Delivery logs are immutable.'));
        static::deleting(fn () => throw new LogicException('Delivery logs are immutable.'));
    }

    public function delivery()
    {
        return $this->belongsTo(Delivery::class);
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

}
