<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class AuditLogger
{
    /**
     * Record an operation. Call inside the same DB transaction as the change it describes,
     * so the change and its audit entry commit (or roll back) together.
     *
     * @param  array<string, array{from: mixed, to: mixed}>  $changes
     */
    public function record(string $action, ?Model $subject = null, array $changes = [], array $metadata = [], ?string $permission = null): AuditLog
    {
        $actor = auth()->user();
        $request = request();

        return AuditLog::create([
            'actor_id'     => $actor?->id,
            'actor_role'   => $actor?->role,
            'action'       => $action,
            'permission'   => $permission,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id'   => $subject?->getKey(),
            'changes'      => $changes ?: null,
            'metadata'     => $metadata ?: null,
            'ip_address'   => $request?->ip(),
            'user_agent'   => $request ? Str::limit((string) $request->userAgent(), 252) : null,
        ]);
    }

    /**
     * Record a refused admin request (failed Gate/Policy or deny-by-default). Never throws:
     * failing to audit a denial must not turn the 403 into a 500.
     */
    public function recordDenial(Request $request, HttpExceptionInterface $exception): void
    {
        $fromGate = $exception->getPrevious() instanceof AuthorizationException;
        if (! $request->is('admin', 'admin/*') || (! $fromGate && $exception->getStatusCode() !== 403)) {
            return;
        }

        try {
            $this->record('authorization.denied', null, [], [
                'route'   => $request->route()?->getName(),
                'method'  => $request->method(),
                'path'    => $request->path(),
                'status'  => $exception->getStatusCode(),
                'reason'  => $exception->getMessage() ?: null,
            ]);
        } catch (Throwable $e) {
            report($e);
        }
    }

    /**
     * Build a {field: {from, to}} diff for the given attributes of a model before it is saved.
     *
     * @param  string[]  $fields
     */
    public static function diff(Model $model, array $newValues, array $fields): array
    {
        $changes = [];
        foreach ($fields as $field) {
            if (array_key_exists($field, $newValues) && $model->getAttribute($field) != $newValues[$field]) {
                $changes[$field] = ['from' => $model->getAttribute($field), 'to' => $newValues[$field]];
            }
        }

        return $changes;
    }
}
