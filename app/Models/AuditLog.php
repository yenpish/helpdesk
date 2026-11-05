<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;

class AuditLog extends Model
{
    /** Secrets must never enter the audit snapshot JSON. */
    protected static function booted(): void
    {
        static::saving(function (AuditLog $log): void {
            $log->old_values = self::redactSnapshot($log->old_values);
            $log->new_values = self::redactSnapshot($log->new_values);
        });
    }

    private static function redactSnapshot(mixed $values): mixed
    {
        if (! is_array($values)) {
            return $values;
        }

        foreach ($values as $key => $value) {
            $name = Str::lower((string) $key);
            if (
                str_contains($name, 'password')
                || str_contains($name, 'token')
                || str_contains($name, 'secret')
                || str_contains($name, 'pin')
                || str_contains($name, 'signature')
                || str_contains($name, 'credential')
                || str_contains($name, 'authorization')
                || str_contains($name, 'private_key')
                || str_contains($name, 'api_key')
                || str_contains($name, 'client_key')
                || $name === 'key'
                || str_ends_with($name, '_key')
                || str_contains($name, 'transport_error')
                || $name === 'last_error'
            ) {
                unset($values[$key]);
                continue;
            }

            if (is_array($value)) {
                $values[$key] = self::redactSnapshot($value);
            }
        }

        return $values;
    }

    protected $fillable = [
        'user_id',
        'action',
        'auditable_type',
        'auditable_id',
        'description',
        'old_values',
        'new_values',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }
}
