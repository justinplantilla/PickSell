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
    private const SENSITIVE_KEYS = '/(?:password|passwd|token|secret|credential|authorization|cookie|session|payment|card|cvv|cvc|iban|bank_account|account_number|routing_number|email|phone|contact|address|birthday|birth_date|social_security|ssn|first_name|last_name|full_name)/i';

    /**
     * Record an operation. Call inside the same DB transaction as the change it describes,
     * so the change and its audit entry commit (or roll back) together.
     *
     * @param  array<string, array{from: mixed, to: mixed}>  $changes
     */
    public function record(
        string $action,
        ?Model $subject = null,
        array $changes = [],
        array $metadata = [],
        ?string $permission = null,
        string $result = 'success',
    ): AuditLog {
        $actor = auth()->user();
        $request = request();

        return AuditLog::create([
            'actor_id' => $actor?->id,
            'actor_role' => $actor?->role,
            'action' => $action,
            'module' => Str::before($action, '.'),
            'result' => $result,
            'permission' => $permission,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'changes' => self::sanitize($changes) ?: null,
            'metadata' => self::sanitize($metadata) ?: null,
            'ip_address' => $request?->ip(),
            'user_agent' => $request ? Str::limit((string) $request->userAgent(), 252) : null,
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
                'route' => $request->route()?->getName(),
                'method' => $request->method(),
                'path' => $request->path(),
                'status' => $exception->getStatusCode(),
                'reason' => $exception->getMessage() ?: null,
            ], null, 'denied');
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

    private static function sanitize(array $values): array
    {
        $sanitized = [];
        foreach ($values as $key => $value) {
            $sanitized[$key] = is_string($key) && preg_match(self::SENSITIVE_KEYS, $key)
                ? self::redact($value)
                : (is_array($value) ? self::sanitize($value) : $value);
        }

        return $sanitized;
    }

    private static function redact(mixed $value): mixed
    {
        if (! is_array($value)) {
            return '[redacted]';
        }

        $redacted = [];
        foreach ($value as $key => $item) {
            $redacted[$key] = is_array($item) ? self::redact($item) : '[redacted]';
        }

        return $redacted;
    }
}
