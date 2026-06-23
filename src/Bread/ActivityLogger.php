<?php

namespace Tardis\Bread;

use Tardis\Models\ActivityLog;

class ActivityLogger
{
    public function log(
        string $modelType,
        int|string $modelId,
        string $action,
        ?array $oldValues = null,
        ?array $newValues = null,
    ): ActivityLog {
        return ActivityLog::create([
            'user_id' => auth()->id(),
            'model_type' => $modelType,
            'model_id' => $modelId,
            'action' => $action,
            'old_values' => $oldValues,
            'new_values' => $newValues,
        ]);
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
