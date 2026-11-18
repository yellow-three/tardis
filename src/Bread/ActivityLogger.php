<?php

declare(strict_types=1);

namespace Tardis\Bread;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Tardis\Models\ActivityLog;
use Tardis\Support\ModelResolver;

class ActivityLogger
{
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
            'old_values' => $oldValues,
            'new_values' => $newValues,
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
     */
    public function audit(string $type, string $action, array $values = [], int|string $id = 0): void
    {
        if (! config('tardis.activity_log.enabled', true)) {
            return;
        }

        try {
            if (Schema::hasTable(ActivityLog::TABLE)) {
                $this->log($type, $id, $action, newValues: $values);
            }
        } catch (\Throwable $e) {
            Log::warning('Could not write to the activity log.', ['action' => $type.'.'.$action, 'error' => $e->getMessage()]);
        }
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
