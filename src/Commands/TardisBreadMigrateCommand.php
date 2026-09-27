<?php

declare(strict_types=1);

namespace Tardis\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Tardis\Bread\Sources\ConfigBreadSource;
use Tardis\Bread\Sources\JsonBreadSource;

class TardisBreadMigrateCommand extends Command
{
    protected $signature = 'tardis:bread:migrate
        {--dry-run : Report what would be migrated without writing anything}
        {--delete : Move migrated config files out of config/bread}
        {--force : Overwrite an existing JSON definition with the same slug}';

    protected $description = 'Migrate BREAD definitions from config/bread to JSON storage';

    public function handle(ConfigBreadSource $legacy, JsonBreadSource $json): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $delete = (bool) $this->option('delete');
        $force = (bool) $this->option('force');

        $definitions = $legacy->all();

        if ($definitions->isEmpty()) {
            $this->components->info('No legacy BREAD definitions found in config/bread.');

            return self::SUCCESS;
        }

        $migrated = 0;
        $skipped = 0;

        foreach ($definitions as $slug => $definition) {
            if (! $force && $json->has($slug)) {
                $this->components->twoColumnDetail($slug, 'skipped (already exists)');
                $skipped++;

                continue;
            }

            if ($dryRun) {
                $this->components->twoColumnDetail($slug, 'would be migrated');
                $migrated++;

                continue;
            }

            $json->save($definition);
            $migrated++;

            if ($delete) {
                $legacyFile = $legacy->path().'/'.$slug.'.php';
                $archiveDir = config_path('bread-migrated');

                File::ensureDirectoryExists($archiveDir);

                if (File::exists($legacyFile)) {
                    File::move($legacyFile, $archiveDir.'/'.$slug.'.php');
                }
            }

            $this->components->twoColumnDetail($slug, 'migrated');
        }

        $this->newLine();

        $this->components->info(
            $dryRun
                ? "Would migrate {$migrated} definition(s), {$skipped} skipped."
                : "Migrated {$migrated} definition(s), {$skipped} skipped."
        );

        return self::SUCCESS;
    }
}
