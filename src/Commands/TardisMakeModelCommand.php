<?php

declare(strict_types=1);

namespace Tardis\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Tardis\Database\ModelGenerator;

class TardisMakeModelCommand extends Command
{
    protected $signature = 'tardis:make-model
        {table : The existing table name}
        {--namespace= : Optional PHP namespace (default: App\\Models)}
        {--path= : Optional output path (default: app_path(\'Models\'))}
        {--force : Overwrite the model if it already exists}';

    protected $description = 'Create an Eloquent model for an existing table';

    public function handle(): int
    {
        $table = $this->argument('table');
        $connection = config('database.default');

        if (! Schema::connection($connection)->hasTable($table)) {
            $this->components->error("Table [{$table}] does not exist.");

            return self::FAILURE;
        }

        $columns = Schema::connection($connection)->getColumns($table);

        try {
            $file = app(ModelGenerator::class)->generate($table, $columns, [
                'namespace' => $this->option('namespace') ?: 'App\Models',
                'path' => $this->option('path') ?: app_path('Models'),
                'force' => (bool) $this->option('force'),
            ]);
        } catch (\RuntimeException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $this->components->info('Model created successfully.');
        $this->components->twoColumnDetail('Model Path', $file);

        return self::SUCCESS;
    }
}
