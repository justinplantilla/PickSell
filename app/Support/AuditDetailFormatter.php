<?php

namespace App\Support;

use Illuminate\Support\Str;

class AuditDetailFormatter
{
    public static function label(string $field): string
    {
        return match ($field) {
            'is_featured' => 'Featured status',
            'moderation_log_id' => 'Moderation reference',
            default => Str::headline($field),
        };
    }

    public static function value(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }

        if ($value === null || $value === '') {
            return 'Not set';
        }

        return is_scalar($value)
            ? (string) $value
            : (string) json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
