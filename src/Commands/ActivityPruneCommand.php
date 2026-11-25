<?php

declare(strict_types=1);

namespace Tardis\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Tardis\Models\ActivityLog;
use Tardis\Support\ModelResolver;

/**
 * Removes activity log entries older than tardis.activity_log.retention_days.
 * Meant for the scheduler: Schedule::command('tardis:activity:prune')->daily().
 */
class ActivityPruneCommand extends Command
{
    protected $signature = 'tardis:activity:prune
        {--days= : Keep this many days instead of tardis.activity_log.retention_days}
        {--pretend : Only count the entries that would be removed}';

    protected $description = 'Remove activity log entries older than the retention period';

    public function handle(): int
    {
        $days = $this->option('days') ?? config('tardis.activity_log.retention_days');

        if ($days === null || $days === '') {
            $this->components->info('No retention period is set (tardis.activity_log.retention_days); nothing was removed.');

            return self::SUCCESS;
        }

        if (! is_numeric($days) || (int) $days < 1) {
            $this->components->error('The retention period must be a whole number of days, at least 1.');

            return self::FAILURE;
        }

        if (! Schema::hasTable(ActivityLog::TABLE)) {
            $this->components->warn('The activity log table does not exist; run the migrations first.');

            return self::SUCCESS;
        }

        $cutoff = now()->subDays((int) $days);
        $query = ModelResolver::activityLog()::query()->where('created_at', '<', $cutoff);

        if ($this->option('pretend')) {
            $this->components->info(sprintf('%d entries older than %s would be removed.', $query->count(), $cutoff->toDateString()));

            return self::SUCCESS;
        }

        // In chunks, so a years-old log does not hold one long lock.
        $removed = 0;

        do {
            $ids = (clone $query)->orderBy('id')->limit(1000)->pluck('id');
            $removed += $ids->isEmpty() ? 0 : ModelResolver::activityLog()::query()->whereKey($ids)->delete();
        } while ($ids->count() === 1000);

        $this->components->info(sprintf('Removed %d activity log entries older than %s.', $removed, $cutoff->toDateString()));

        return self::SUCCESS;
    }
}
