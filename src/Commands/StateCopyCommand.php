<?php

declare(strict_types=1);

namespace Tardis\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Tardis\State\DatabaseStateStore;
use Tardis\State\FileStateStore;
use Tardis\State\StatePaths;
use Tardis\State\StateStore;

/**
 * Copies the panel state from one driver to the other: run it before (or
 * after) switching tardis.state.driver, so settings, menus, themes, plugin
 * switches and BREAD definitions come along.
 */
class StateCopyCommand extends Command
{
    protected $signature = 'tardis:state:copy
        {--to=database : The driver to copy into: database or file}
        {--force : Overwrite entries that already exist in the target}';

    protected $description = 'Copy the Tardis state (settings, menus, themes, BREAD definitions, ...) between the file and database drivers';

    public function handle(): int
    {
        $to = (string) $this->option('to');

        if (! in_array($to, ['database', 'file'], true)) {
            $this->components->error('--to must be "database" or "file".');

            return self::FAILURE;
        }

        if (! Schema::connection(config('tardis.state.connection'))->hasTable(DatabaseStateStore::TABLE)) {
            $this->components->error('The tardis_state table does not exist; run `php artisan migrate` first.');

            return self::FAILURE;
        }

        $database = new DatabaseStateStore(config('tardis.state.connection'));
        $file = new FileStateStore;
        [$source, $target] = $to === 'database' ? [$file, $database] : [$database, $file];

        $copied = 0;
        $skipped = 0;

        foreach ($source->dump() as $key => $contents) {
            $path = StatePaths::path($key);

            if ($target->exists($path) && ! $this->option('force')) {
                $skipped++;

                continue;
            }

            $target->put($path, $contents);
            $copied++;
        }

        // The bound store may have read the table before the copy.
        if (($bound = app(StateStore::class)) instanceof DatabaseStateStore) {
            $bound->refresh();
        }

        $this->components->info(sprintf('Copied %d entries to the %s driver%s.', $copied, $to, $skipped ? sprintf('; %d already there were kept (use --force to overwrite)', $skipped) : ''));

        return self::SUCCESS;
    }
}
