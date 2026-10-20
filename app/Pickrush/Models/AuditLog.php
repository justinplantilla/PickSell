<?php

namespace App\Pickrush\Models;

use App\Pickrush\Support\Actor;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/** Immutable record of every Pickrush write: who, from which hub, what changed. */
class AuditLog extends Model
{
    protected $table = 'pr_audit_logs';

    public const UPDATED_AT = null;

    protected $guarded = [];

    protected $casts = ['before' => 'array', 'after' => 'array', 'created_at' => 'datetime'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Audit log entries are immutable.'));
        static::deleting(fn () => throw new LogicException('Audit log entries are immutable.'));
    }

    public static function record(?Model $subject, string $action, array $before = [], array $after = [], ?Actor $actor = null): self
    {
        $actor ??= Actor::current();

        return self::create([
            'actor_type' => $actor->type,
            'actor_id' => $actor->id,
            'actor_hub_id' => $actor->hubId,
            'action' => $subject ? class_basename($subject).'.'.$action : $action,
            'subject_type' => $subject ? class_basename($subject) : null,
            'subject_id' => $subject?->getKey(),
            'before' => $before ?: null,
            'after' => $after ?: null,
            'ip' => app()->runningInConsole() ? null : request()->ip(),
        ]);
    }
}
