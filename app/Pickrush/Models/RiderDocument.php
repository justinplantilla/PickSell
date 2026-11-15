<?php

namespace App\Pickrush\Models;

use App\Pickrush\Support\Audited;
use App\Pickrush\Support\CompanyOwned;
use Illuminate\Database\Eloquent\Model;

/**
 * A rider's ID or clearance. Files live on the private disk and are served through a signed,
 * short-lived link. A renewal keeps the one it replaced, with replaced_at set.
 */
class RiderDocument extends Model
{
    use Audited, CompanyOwned;

    protected $table = 'pr_rider_documents';

    protected array $companyThrough = ['rider_id' => 'pr_riders'];

    protected $guarded = ['id'];

    protected $casts = ['expiry_date' => 'date', 'expiry_estimated' => 'boolean', 'replaced_at' => 'datetime'];

    /** The documents in force, not the ones a renewal replaced. */
    public function scopeCurrent($query)
    {
        return $query->whereNull('replaced_at');
    }

    public function rider()
    {
        return $this->belongsTo(Rider::class);
    }

    public function label(): string
    {
        return config("pickrush.rider_documents.{$this->doc_type}", $this->doc_type);
    }

    /** Valid through its expiry day; expired from the day after. */
    public function isExpired(): bool
    {
        return $this->expiry_date?->lt(today()) ?? false;
    }
}
