<?php

namespace App\Pickrush\Support;

use App\Pickrush\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;

/** Records every create, update and delete of the model in pr_audit_logs with before/after values. */
trait Audited
{
    protected static function bootAudited(): void
    {
        static::created(fn (Model $model) => AuditLog::record($model, 'created', [], $model->auditable($model->getAttributes())));

        static::updated(function (Model $model) {
            $changed = array_keys($model->getChanges());
            $changed = array_values(array_diff($changed, ['updated_at', 'remember_token', 'last_login_at']));
            if ($changed === []) {
                return;
            }
            AuditLog::record($model, 'updated',
                $model->auditable(array_intersect_key($model->getOriginal(), array_flip($changed))),
                $model->auditable(array_intersect_key($model->getAttributes(), array_flip($changed))));
        });

        static::deleted(fn (Model $model) => AuditLog::record($model, 'deleted', $model->auditable($model->getOriginal()), []));
    }

    /** Never write secrets to the audit log. */
    protected function auditable(array $values): array
    {
        return array_diff_key($values, array_flip(['password', 'remember_token']));
    }
}
