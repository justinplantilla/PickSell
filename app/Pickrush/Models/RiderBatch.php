<?php

namespace App\Pickrush\Models;

use App\Pickrush\Support\Audited;
use Illuminate\Database\Eloquent\Model;

/**
 * Tasks the dispatcher gave one rider in one go: a pickup batch (a stop is one seller) or a
 * delivery batch (deliveries and returns to sellers; a stop is one recipient). The rider accepts
 * or declines the whole batch in the rider app, and every batch ends back at the rider's station.
 */
class RiderBatch extends Model
{
    use Audited;

    protected $table = 'pr_rider_batches';

    protected $guarded = ['id'];

    protected $casts = [
        'offered_at' => 'datetime',
        'responded_at' => 'datetime',
        'hub_arrived_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    /** Batches the rider still has to act on. */
    public const OPEN = ['offered', 'accepted'];

    protected static function booted(): void
    {
        static::created(function (RiderBatch $batch) {
            $prefix = $batch->type === 'delivery' ? 'DV-' : 'PK-';
            $batch->forceFill(['batch_no' => $prefix.str_pad((string) $batch->id, 4, '0', STR_PAD_LEFT)])->saveQuietly();
        });
    }

    public function rider()
    {
        return $this->belongsTo(Rider::class)->withoutGlobalScopes();
    }

    public function station()
    {
        return $this->belongsTo(Hub::class, 'station_hub_id');
    }

    public function assignments()
    {
        return $this->hasMany(Assignment::class, 'batch_id');
    }

    public function isDelivery(): bool
    {
        return $this->type === 'delivery';
    }

    public function isOpen(): bool
    {
        return in_array($this->status, self::OPEN, true);
    }
}
