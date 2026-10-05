<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    public function index(): View
    {
        $auditLogs = AuditLog::with('user')
            ->latest()
            ->paginate(25);

        $auditLogs->getCollection()->transform(function (AuditLog $log) {
            $oldValues = $this->normalizeValues($log->getRawOriginal('old_values'));
            $newValues = $this->normalizeValues($log->getRawOriginal('new_values'));
            $changes = $this->displayChanges($log->action, $oldValues, $newValues);

            if (
                $log->auditable_type === User::class
                && str_contains(strtolower($log->description ?? ''), 'password updated')
            ) {
                $changes[] = [
                    'field' => 'Password',
                    'before' => '—',
                    'after' => 'Changed (value hidden)',
                ];
            }

            $log->setAttribute(
                'display_changes',
                $changes
            );

            return $log;
        });

        return view('audit-logs.index', compact('auditLogs'));
    }

    private function normalizeValues(mixed $values): array
    {
        for ($attempt = 0; is_string($values) && $attempt < 3; $attempt++) {
            $decoded = json_decode($values, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                return [];
            }

            $values = $decoded;
        }

        if (!is_array($values)) {
            return [];
        }

        $sensitiveOrInternal = [
            'id', 'password', 'password_confirmation', 'remember_token', 'token',
            'api_token', 'two_factor_secret', 'two_factor_recovery_codes',
            'created_at', 'updated_at', 'deleted_at', 'email_verified_at',
            'created_by', 'updated_by', 'attendance_pin',
        ];

        foreach ($values as $key => $value) {
            $normalizedKey = strtolower((string) $key);

            if (in_array($normalizedKey, $sensitiveOrInternal, true)
                || str_ends_with($normalizedKey, '_id')
                || str_contains($normalizedKey, 'password')
                || str_contains($normalizedKey, 'token')
                || str_contains($normalizedKey, 'secret')
                || str_contains($normalizedKey, 'pin')) {
                unset($values[$key]);
                continue;
            }

            if (is_array($value)) {
                $values[$key] = $this->normalizeValues($value);
            }
        }

        return $values;
    }

    private function displayChanges(string $action, array $oldValues, array $newValues): array
    {
        $keys = array_unique(array_merge(array_keys($oldValues), array_keys($newValues)));
        $changes = [];

        foreach ($keys as $key) {
            $beforeExists = array_key_exists($key, $oldValues);
            $afterExists = array_key_exists($key, $newValues);

            if (strtolower($action) === 'updated'
                && $beforeExists && $afterExists && $oldValues[$key] === $newValues[$key]) {
                continue;
            }

            $changes[] = [
                'field' => Str::headline((string) $key),
                'before' => $beforeExists ? $this->displayValue($key, $oldValues[$key]) : '—',
                'after' => $afterExists ? $this->displayValue($key, $newValues[$key]) : '—',
            ];
        }

        return $changes;
    }

    private function displayValue(string|int $key, mixed $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }

        if (is_array($value)) {
            $items = [];
            foreach ($value as $nestedKey => $nestedValue) {
                $items[] = is_string($nestedKey)
                    ? Str::headline($nestedKey) . ': ' . $this->displayValue($nestedKey, $nestedValue)
                    : $this->displayValue($key, $nestedValue);
            }

            return implode('; ', $items);
        }

        if ((string) $key === 'role') {
            return match ($value) {
                'admin' => 'System Admin',
                'organizer' => 'Organizer',
                'user' => 'User',
                'technician' => 'Technician (legacy)',
                default => Str::headline((string) $value),
            };
        }

        if (is_string($value) && str_ends_with((string) $key, '_at')) {
            try {
                return Carbon::parse($value)->format('d M Y, h:i A');
            } catch (\Throwable) {
                // Keep an unparseable historical value readable as stored.
            }
        }

        return (string) $value;
    }
}
