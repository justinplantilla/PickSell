<?php

namespace App\Pickrush\Models;

use App\Pickrush\Support\Audited;
use App\Pickrush\Support\CompanyOwned;
use Illuminate\Database\Eloquent\Model;

/**
 * A new driver's license or OR/CR the rider sent from the app, waiting for the station hub
 * manager. Approved, its files become the rider's documents; rejected, the rider sees the reason.
 * A newer one for the same document replaces a pending one.
 */
class RiderDocumentUpdate extends Model
{
    use Audited, CompanyOwned;

    public const TYPES = ['drivers_license', 'or_cr'];

    protected $table = 'pr_rider_document_updates';

    protected array $companyThrough = ['rider_id' => 'pr_riders'];

    protected $guarded = ['id'];

    protected $casts = ['expiry_date' => 'date', 'files' => 'array', 'reviewed_at' => 'datetime'];

    public function rider()
    {
        return $this->belongsTo(Rider::class);
    }

    public function reviewer()
    {
        return $this->belongsTo(Staff::class, 'reviewed_by');
    }

    public function label(): string
    {
        return config("pickrush.rider_documents.{$this->doc_type}", $this->doc_type);
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }
}
