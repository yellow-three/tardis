<?php

declare(strict_types=1);

namespace Tardis\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Tardis\Bread\Sources\JsonBreadSource;

class TardisBreadExportCommand extends Command
{
    protected $signature = 'tardis:bread:export
        {--file= : Write the export to this file instead of stdout}';

    protected $description = 'Export all BREAD definitions as a single JSON document';

    public function handle(JsonBreadSource $json): int
    {
        $definitions = $json->all();

        $content = json_encode(
            $definitions->map(fn ($definition) => $definition->toArray())->values()->all(),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        )."\n";

        $target = $this->option('file');

        if ($target !== null) {
            File::ensureDirectoryExists(dirname((string) $target));
            File::put($target, $content);

            $this->components->info(
                'Exported '.$definitions->count()." BREAD definition(s) to {$target}."
            );

            return self::SUCCESS;
        }

        $this->line($content);

        return self::SUCCESS;
    }
}
