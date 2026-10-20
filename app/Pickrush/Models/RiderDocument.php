<?php

namespace App\Pickrush\Models;

use App\Pickrush\Support\Audited;
use Illuminate\Database\Eloquent\Model;

/** A rider's ID or clearance. Files live on the private disk and are served through a signed, short-lived link. */
class RiderDocument extends Model
{
    use Audited;

    protected $table = 'pr_rider_documents';

    protected $guarded = ['id'];

    protected $casts = ['expiry_date' => 'date'];

    public function rider()
    {
        return $this->belongsTo(Rider::class);
    }

    public function label(): string
    {
        return config("pickrush.rider_documents.{$this->doc_type}", $this->doc_type);
    }

    public function isExpired(): bool
    {
        return $this->expiry_date?->isPast() ?? false;
    }
}
