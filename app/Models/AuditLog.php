<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

/** Append-only record of admin operations and authorization denials. */
class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'actor_id', 'actor_role', 'action', 'module', 'result', 'permission',
        'subject_type', 'subject_id', 'changes', 'metadata',
        'ip_address', 'user_agent',
    ];

    protected $casts = [
        'changes'    => 'array',
        'metadata'   => 'array',
        'created_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Audit log entries are immutable.'));
        static::deleting(fn () => throw new LogicException('Audit log entries are immutable.'));
    }

    public function actor()   { return $this->belongsTo(User::class, 'actor_id'); }
    public function subject() { return $this->morphTo(); }
}
