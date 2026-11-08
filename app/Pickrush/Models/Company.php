<?php

namespace App\Pickrush\Models;

use App\Pickrush\Support\Audited;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A logistics company on the portal. Each one runs its own hubs, staff, riders and parcels;
 * Pickrush is the default company and the carrier pre-selected at checkout.
 */
class Company extends Model
{
    use Audited;

    public const STATUSES = ['pending', 'approved', 'rejected', 'suspended'];

    protected $table = 'pr_companies';

    protected $guarded = ['id'];

    protected $casts = [
        'is_default' => 'boolean',
        'approved_at' => 'datetime',
        'submitted_at' => 'datetime',
        'terms_accepted_at' => 'datetime',
    ];

    private static ?int $defaultId = null;

    /** Pickrush: owns records made with no company in context (bridge, console, old data). */
    public static function defaultId(): int
    {
        return self::$defaultId ??= (int) self::where('is_default', true)->value('id');
    }

    public static function forgetDefault(): void
    {
        self::$defaultId = null;
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', 'approved');
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function owner()
    {
        return $this->belongsTo(Staff::class, 'owner_staff_id');
    }

    public function documents()
    {
        return $this->hasMany(CompanyDocument::class);
    }

    /** The newest upload of each required document. */
    public function latestDocument(string $type): ?CompanyDocument
    {
        return $this->documents()->where('type', $type)->orderByDesc('version')->first();
    }

    public function reviews()
    {
        return $this->hasMany(CompanyReview::class)->latest('id');
    }

    public function hubs()
    {
        return $this->hasMany(Hub::class);
    }

    public function staff()
    {
        return $this->hasMany(Staff::class);
    }
}
