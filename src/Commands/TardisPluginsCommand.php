<?php

declare(strict_types=1);

namespace Tardis\Commands;

use Illuminate\Console\Command;
use Tardis\Manager\PluginManager;

class TardisPluginsCommand extends Command
{
    protected $signature = 'tardis:plugins
        {action=list : list, enable or disable}
        {name? : The plugin to enable or disable}
        {--json : Print the list as JSON}';

    protected $description = 'List the TARDIS plugins, or enable/disable one';

    public function handle(PluginManager $plugins): int
    {
        return match ($this->argument('action')) {
            'list' => $this->list($plugins),
            'enable', 'disable' => $this->toggle($plugins),
            default => $this->unknown(),
        };
    }

    protected function list(PluginManager $plugins): int
    {
        $rows = $plugins->allWithInfo()->filter()->map(fn (array $info) => [
            'name' => $info['name'],
            'type' => $info['type'],
            'state' => $info['enabled'] ? 'enabled' : 'disabled',
            'locked' => $plugins->isLocked($info['name']) ? 'yes' : 'no',
            'version' => $info['version'] ?? '-',
            'class' => $info['class'],
        ])->values();

        if ($this->option('json')) {
            $this->line($rows->toJson(JSON_PRETTY_PRINT));

            return self::SUCCESS;
        }

        if ($rows->isEmpty()) {
            $this->components->info('No plugins are registered.');

            return self::SUCCESS;
        }

        $this->table(['Name', 'Type', 'State', 'Locked', 'Version', 'Class'], $rows->map(fn ($row) => array_values($row))->all());

        return self::SUCCESS;
    }

    protected function toggle(PluginManager $plugins): int
    {
        $name = (string) $this->argument('name');
        $action = $this->argument('action');

        if ($name === '' || $plugins->get($name) === null) {
            $this->components->error("Unknown plugin [{$name}].");

            return self::FAILURE;
        }

        if ($action === 'disable' && $plugins->isLocked($name)) {
            $this->components->error("[{$name}] is locked: authentication and authorization plugins cannot be disabled.");

            return self::FAILURE;
        }

        $action === 'enable' ? $plugins->enable($name) : $plugins->disable($name);
        $this->components->info("Plugin [{$name}] ".($action === 'enable' ? 'enabled' : 'disabled').'.');

        return self::SUCCESS;
    }

    protected function unknown(): int
    {
        $this->components->error('Unknown action. Use list, enable or disable.');

        return self::FAILURE;
    }
}
