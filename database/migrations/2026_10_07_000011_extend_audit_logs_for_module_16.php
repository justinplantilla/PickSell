<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table): void {
            $table->string('module', 64)->nullable();
            $table->string('result', 30)->default('success');
            $table->index(['module', 'action']);
            $table->index(['result', 'created_at']);
        });

        DB::table('audit_logs')
            ->select(['id', 'action'])
            ->addSelect(['changes', 'metadata'])
            ->orderBy('id')
            ->chunkById(500, function ($logs): void {
                foreach ($logs as $log) {
                    DB::table('audit_logs')
                        ->where('id', $log->id)
                        ->update([
                            'module' => Str::before((string) $log->action, '.'),
                            'result' => $log->action === 'authorization.denied' ? 'denied' : 'success',
                            'changes' => $this->sanitizeJson($log->changes),
                            'metadata' => $this->sanitizeJson($log->metadata),
                        ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table): void {
            $table->dropIndex(['module', 'action']);
            $table->dropIndex(['result', 'created_at']);
            $table->dropColumn(['module', 'result']);
        });
    }

    private function sanitizeJson(?string $json): ?string
    {
        if ($json === null) {
            return null;
        }

        $values = json_decode($json, true);
        if (! is_array($values)) {
            return $json;
        }

        return json_encode($this->sanitize($values), JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE);
    }

    private function sanitize(array $values): array
    {
        $sanitized = [];
        foreach ($values as $key => $value) {
            if (is_string($key) && preg_match('/(?:password|passwd|token|secret|credential|authorization|cookie|session|payment|card|cvv|cvc|iban|bank_account|account_number|routing_number|email|phone|contact|address|birthday|birth_date|social_security|ssn|first_name|last_name|full_name)/i', $key)) {
                $sanitized[$key] = $this->redact($value);
            } else {
                $sanitized[$key] = is_array($value) ? $this->sanitize($value) : $value;
            }
        }

        return $sanitized;
    }

    private function redact(mixed $value): mixed
    {
        if (! is_array($value)) {
            return '[redacted]';
        }

        $redacted = [];
        foreach ($value as $key => $item) {
            $redacted[$key] = is_array($item) ? $this->redact($item) : '[redacted]';
        }

        return $redacted;
    }
};
