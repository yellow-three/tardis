<?php

declare(strict_types=1);

namespace Tardis\Listeners;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Tardis\Bread\ActivityLogger;
use Tardis\Bread\BreadManager;
use Tardis\Events\BreadRecordCreated;
use Tardis\Events\BreadRecordDeleted;
use Tardis\Events\BreadRecordUpdated;

/**
 * Writes record events to the activity log (tardis.activity_log.*).
 *
 * Passwords and a model's hidden attributes are removed before anything is
 * stored: the log is readable by anyone with the "view activity" ability.
 */
class LogBreadActivity
{
    public function __construct(protected ActivityLogger $logger) {}

    public function handle(BreadRecordCreated|BreadRecordUpdated|BreadRecordDeleted $event): void
    {
        $action = match (true) {
            $event instanceof BreadRecordCreated => 'created',
            $event instanceof BreadRecordUpdated => 'updated',
            default => 'deleted',
        };

        if (! config('tardis.activity_log.enabled', true)
            || ! in_array($action, (array) config('tardis.activity_log.log_events', ['created', 'updated', 'deleted']), true)) {
            return;
        }

        $key = $event->model->getKey();

        // activity_logs.model_id is an unsigned integer column.
        if (! is_int($key) && ! (is_string($key) && ctype_digit($key))) {
            return;
        }

        try {
            if (! Schema::hasTable('activity_logs')) {
                return;
            }

            $redacted = $this->redactedKeys($event);

            $this->logger->log(
                $event->model::class,
                (int) $key,
                $action,
                $event instanceof BreadRecordUpdated ? $this->without($event->old, $redacted) : null,
                match (true) {
                    $event instanceof BreadRecordCreated => $this->without($event->data, $redacted),
                    $event instanceof BreadRecordUpdated => $this->without($event->new, $redacted),
                    default => null,
                },
            );
        } catch (\Throwable $e) {
            Log::warning('Could not write the activity log.', ['error' => $e->getMessage()]);
        }
    }

    /**
     * @return array<int, string>
     */
    protected function redactedKeys(BreadRecordCreated|BreadRecordUpdated|BreadRecordDeleted $event): array
    {
        $keys = $event->model->getHidden();
        $definition = app(BreadManager::class)->find($event->slug);

        foreach ($definition?->fields ?? [] as $field) {
            if (($field['type'] ?? null) === 'password' && isset($field['name'])) {
                $keys[] = $field['name'];
            }
        }

        return $keys;
    }

    /**
     * @param  array<string, mixed>  $values
     * @param  array<int, string>  $remove
     * @return array<string, mixed>
     */
    protected function without(array $values, array $remove): array
    {
        return array_diff_key($values, array_flip($remove));
    }
}
