<?php

declare(strict_types=1);

namespace Tardis\Bread;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Tardis\Models\ActivityLog;
use Tardis\Support\ModelResolver;

class ActivityLogger
{
    /** Replaces the value, not the key: reviewers still see what changed. */
    private const REDACTED = '[redacted]';

    /**
     * Keys whose values must never be written to the activity log. The list can
     * be extended with tardis.activity_log.redact; the strong markers also catch
     * custom field names such as "billing_token".
     *
     * @var list<string>
     */
    private const SECRET_KEYS = [
        'password', 'password_confirmation', 'current_password',
        'remember_token', 'api_token', 'api_key', 'token', 'access_token',
        'refresh_token', 'secret', 'client_secret', 'private_key',
        'recovery_code', 'two_factor_secret', 'two_factor_recovery_codes',
    ];

    /** @var list<string> */
    private const SECRET_MARKERS = ['password', 'token', 'secret', 'credential', 'private_key'];

    private static ?bool $requestColumns = null;

    public function log(
        string $modelType,
        int|string $modelId,
        string $action,
        ?array $oldValues = null,
        ?array $newValues = null,
    ): ActivityLog {
        $request = app()->bound('request') ? request() : null;

        return ModelResolver::activityLog()::create([
            'user_id' => auth()->id(),
            'model_type' => $modelType,
            'model_id' => $modelId,
            'action' => $action,
            'old_values' => $this->redact($oldValues),
            'new_values' => $this->redact($newValues),
        ] + ($this->hasRequestColumns() && $request !== null ? [
            'ip_address' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 255) ?: null,
        ] : []));
    }

    /**
     * Record an administrative action (sign-in, role change, plugin switch)
     * that is not a BREAD record change. It never fails the action itself:
     * a disabled or missing log only means nothing is written.
     *
     * @param  array<string, mixed>  $values
     * @param  array<string, mixed>|null  $old  the state before the change, where there is one
     */
    public function audit(string $type, string $action, array $values = [], int|string $id = 0, ?array $old = null): void
    {
        if (! config('tardis.activity_log.enabled', true)) {
            return;
        }

        try {
            if (Schema::hasTable(ActivityLog::TABLE)) {
                $this->log($type, $id, $action, oldValues: $old, newValues: $values);
            }
        } catch (\Throwable $e) {
            Log::warning('Could not write to the activity log.', ['action' => $type.'.'.$action, 'error' => $e->getMessage()]);
        }
    }

    /**
     * One redaction point for every log entry, whether it came from a BREAD
     * event, an audit() call or a plugin calling log() directly. A model's
     * $hidden attributes and password fields are already dropped by
     * LogBreadActivity; this catches plain-text columns on models that do not
     * declare them, plus secrets in nested audit payloads.
     *
     * @param  array<string, mixed>|null  $values
     * @return array<string, mixed>|null
     */
    protected function redact(?array $values): ?array
    {
        if ($values === null) {
            return null;
        }

        $keys = array_map('strtolower', array_merge(
            self::SECRET_KEYS,
            (array) config('tardis.activity_log.redact', []),
        ));

        return $this->redactArray($values, $keys);
    }

    /**
     * @param  array<mixed>  $values
     * @param  list<string>  $keys
     * @return array<mixed>
     */
    private function redactArray(array $values, array $keys): array
    {
        $clean = [];

        foreach ($values as $key => $value) {
            if (is_array($value)) {
                $value = $this->redactArray($value, $keys);
            }

            if (! is_string($key)) {
                $clean[$key] = $value;

                continue;
            }

            $name = strtolower($key);
            $isSensitive = in_array($name, $keys, true);

            if (! $isSensitive) {
                foreach (self::SECRET_MARKERS as $marker) {
                    if (str_contains($name, $marker)) {
                        $isSensitive = true;

                        break;
                    }
                }
            }

            // true/false and null say only that something changed (for example
            // "password_changed"), never the secret itself, so they stay.
            $clean[$key] = $isSensitive && ! is_bool($value) && $value !== null ? self::REDACTED : $value;
        }

        return $clean;
    }

    /** The ip/user agent columns arrive with a later migration. */
    protected function hasRequestColumns(): bool
    {
        return self::$requestColumns ??= Schema::hasColumn(ActivityLog::TABLE, 'ip_address');
    }

    public function created(string $modelType, int|string $modelId, array $values = []): ActivityLog
    {
        return $this->log($modelType, $modelId, 'created', newValues: $values);
    }

    public function updated(string $modelType, int|string $modelId, ?array $old = null, ?array $new = null): ActivityLog
    {
        return $this->log($modelType, $modelId, 'updated', oldValues: $old, newValues: $new);
    }

    public function deleted(string $modelType, int|string $modelId, ?array $old = null): ActivityLog
    {
        return $this->log($modelType, $modelId, 'deleted', oldValues: $old);
    }

    public function restored(string $modelType, int|string $modelId): ActivityLog
    {
        return $this->log($modelType, $modelId, 'restored');
    }
}
