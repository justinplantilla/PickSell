<?php

namespace App\Pickrush\Models;

use App\Pickrush\Support\Audited;
use Illuminate\Database\Eloquent\Model;

/**
 * One rider task (pickup from a seller, delivery to a buyer, or a return to the seller).
 * Reassignments and retries create new rows, so the history is never overwritten.
 */
class Assignment extends Model
{
    use Audited;

    protected $table = 'pr_assignments';

    protected $guarded = ['id'];

    protected $casts = ['assigned_at' => 'datetime', 'completed_at' => 'datetime', 'cod_collected' => 'decimal:2'];

    public const OPEN = ['assigned', 'out_for_delivery'];

    public function parcel()
    {
        return $this->belongsTo(Parcel::class)->withoutGlobalScopes();
    }

    public function rider()
    {
        return $this->belongsTo(Rider::class)->withoutGlobalScopes();
    }

    public function isOpen(): bool
    {
        return in_array($this->status, self::OPEN, true);
    }
}
