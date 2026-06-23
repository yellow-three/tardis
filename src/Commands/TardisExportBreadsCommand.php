<?php

namespace Tardis\Core\Commands;

use Illuminate\Console\Command;
use Tardis\Core\Bread\BreadManager;

class TardisExportBreadsCommand extends Command
{
    protected $signature = 'tardis:export-breads';

    protected $description = 'Export database bread definitions to JSON files';

    public function handle(BreadManager $bread): int
    {
        $breads = $bread->databaseSource()->all();

        if ($breads->isEmpty()) {
            $this->components->warn('No database breads found to export.');

            return self::SUCCESS;
        }

        foreach ($breads as $data) {
            $bread->save($data);
            $this->components->twoColumnDetail($data['slug'], 'Exported');
        }

        $this->components->info('All breads exported successfully.');

        return self::SUCCESS;
    }
}
