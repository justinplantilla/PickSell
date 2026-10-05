<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

/** One entry in a seller's compliance record. Append-only. */
class ComplianceAction extends Model
{
    public const CASE_OPENED = 'case_opened';
    public const NOTE = 'note';
    public const SEVERITY_CHANGED = 'severity_changed';
    public const WARNING = 'warning';
    public const SUSPENSION = 'suspension';
    public const REINSTATEMENT = 'reinstatement';
    public const CASE_RESOLVED = 'case_resolved';
    public const CASE_DISMISSED = 'case_dismissed';

    public const LABELS = [
        self::CASE_OPENED => 'Case opened',
        self::NOTE => 'Note / evidence',
        self::SEVERITY_CHANGED => 'Severity changed',
        self::WARNING => 'Warning issued',
        self::SUSPENSION => 'Seller suspended',
        self::REINSTATEMENT => 'Seller reinstated',
        self::CASE_RESOLVED => 'Case resolved',
        self::CASE_DISMISSED => 'Case dismissed',
    ];

    protected $fillable = ['seller_id', 'compliance_case_id', 'actor_id', 'action', 'reason', 'attachments', 'metadata'];

    protected $casts = ['attachments' => 'array', 'metadata' => 'array'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Compliance records are append-only.'));
        static::deleting(fn () => throw new LogicException('Compliance records are append-only.'));
    }

    public function seller()         { return $this->belongsTo(User::class, 'seller_id'); }
    public function complianceCase() { return $this->belongsTo(ComplianceCase::class); }
    public function actor()          { return $this->belongsTo(User::class, 'actor_id'); }

    public function getLabelAttribute(): string
    {
        return self::LABELS[$this->action] ?? ucfirst(str_replace('_', ' ', $this->action));
    }
}
