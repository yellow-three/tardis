<?php

declare(strict_types=1);

namespace Tardis\Commands;

use Illuminate\Console\Command;
use Tardis\Import\Voyager\ImportReport;
use Tardis\Import\Voyager\VoyagerImporter;

class ImportVoyagerCommand extends Command
{
    protected $signature = 'tardis:import:voyager
        {--connection= : The database connection holding the Voyager tables (default: the default connection)}
        {--only=* : Import only these parts: bread, menus, settings, roles}
        {--dry-run : Report what would be imported without writing anything}
        {--force : Overwrite BREADs and site menus with the same name, and take values of existing settings}';

    protected $description = 'Import BREADs, menus, settings and roles from a Voyager 1.x installation';

    public function handle(): int
    {
        $parts = $this->option('only') ?: VoyagerImporter::PARTS;
        $unknown = array_diff($parts, VoyagerImporter::PARTS);

        if ($unknown !== []) {
            $this->components->error('Unknown part: '.implode(', ', $unknown).'. Use '.implode(', ', VoyagerImporter::PARTS).'.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');

        $report = (new VoyagerImporter(
            connection: $this->option('connection') ?: null,
            dryRun: $dryRun,
            force: (bool) $this->option('force'),
        ))->run(array_values($parts));

        if ($dryRun) {
            $this->components->warn('Dry run: nothing was written.');
        }

        foreach ($report->lines() as $line) {
            $status = match ($line['status']) {
                ImportReport::IMPORTED => $dryRun ? '<fg=cyan>would import</>' : '<fg=green>imported</>',
                ImportReport::SKIPPED => '<fg=yellow>skipped</>',
                default => '<fg=red>unmapped</>',
            };

            $this->components->twoColumnDetail(
                "[{$line['section']}] {$line['item']}".($line['message'] !== '' ? " <fg=gray>{$line['message']}</>" : ''),
                $status,
            );
        }

        $this->newLine();
        $this->components->info(sprintf(
            '%d imported, %d skipped, %d unmapped.',
            $report->count(ImportReport::IMPORTED),
            $report->count(ImportReport::SKIPPED),
            $report->count(ImportReport::UNMAPPED),
        ));

        return self::SUCCESS;
    }
}
