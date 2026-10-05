<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ComplianceCase extends Model
{
    public const TYPES = [
        'category_mismatch' => 'Category mismatch',
        'prohibited_item' => 'Prohibited item',
        'counterfeit' => 'Counterfeit / IP',
        'misleading_listing' => 'Misleading listing',
        'fulfillment' => 'Fulfillment failures',
        'customer_complaints' => 'Repeated customer complaints',
        'other' => 'Other',
    ];

    /** Ordered low → high; the weight feeds the seller risk indicator. */
    public const SEVERITIES = ['low' => 1, 'medium' => 2, 'high' => 3, 'critical' => 5];

    public const OPEN_STATUSES = ['open', 'investigating'];
    public const CLOSED_STATUSES = ['resolved', 'dismissed'];

    protected $fillable = ['seller_id', 'opened_by', 'product_id', 'type', 'status', 'severity', 'description', 'resolved_at'];

    protected $casts = ['resolved_at' => 'datetime'];

    public function seller()  { return $this->belongsTo(User::class, 'seller_id'); }
    public function opener()  { return $this->belongsTo(User::class, 'opened_by'); }
    public function product() { return $this->belongsTo(Product::class); }
    public function actions() { return $this->hasMany(ComplianceAction::class)->oldest()->oldest('id'); }

    public function isOpen(): bool
    {
        return in_array($this->status, self::OPEN_STATUSES, true);
    }

    public function scopeOpen($query)
    {
        return $query->whereIn('status', self::OPEN_STATUSES);
    }

    public function getTypeLabelAttribute(): string
    {
        return self::TYPES[$this->type] ?? ucfirst(str_replace('_', ' ', $this->type));
    }
}
