<?php

namespace Tardis\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class TardisMakePluginCommand extends Command
{
    protected $signature = 'tardis:make-plugin
        {name : The plugin name (e.g., "blog" for TardisBlog)}
        {--namespace= : Optional PHP namespace (default: Tardis\\{StudlyName})}
        {--package-dir=packages/Tardis : Base directory for the package}
        {--with-menu : Include MenuItems trait}
        {--with-widgets : Include Widgets trait}
        {--with-settings : Include Settings trait}
        {--with-migration : Create a migration file}
        {--with-model : Create an Eloquent model}';

    protected $description = 'Create a new TARDIS plugin package';

    public function handle(): int
    {
        $name = $this->argument('name');
        $studlyName = Str::studly($name);
        $lowercaseName = Str::kebab($name);
        $baseDir = $this->option('package-dir');
        $namespace = $this->option('namespace') ?: "Tardis\\{$studlyName}";
        $pluginDir = "{$baseDir}/{$studlyName}";
        $stubPath = __DIR__.'/../../stubs/plugin';

        $this->createDirectories($pluginDir);

        $replacements = $this->buildReplacements($studlyName, $lowercaseName, $namespace);

        $this->generateFromStub("{$stubPath}/composer.json.stub", "{$pluginDir}/composer.json", $replacements);
        $this->generateFromStub("{$stubPath}/config.stub", "{$pluginDir}/config/tardis-{$lowercaseName}.php", $replacements);
        $this->generateFromStub("{$stubPath}/ServiceProvider.stub", "{$pluginDir}/src/Tardis{$studlyName}ServiceProvider.php", $replacements);
        $this->generateFromStub("{$stubPath}/Plugin.stub", "{$pluginDir}/src/Tardis{$studlyName}Plugin.php", $replacements);
        $this->generateFromStub("{$stubPath}/routes.stub", "{$pluginDir}/routes/web.php", $replacements);
        $this->generateFromStub("{$stubPath}/SFC-page.stub", "{$pluginDir}/resources/views/pages/admin/⚡{$lowercaseName}.blade.php", $replacements);

        if ($this->option('with-model')) {
            $this->generateFromStub("{$stubPath}/Model.stub", "{$pluginDir}/src/Models/{$studlyName}.php", $replacements);
        }

        if ($this->option('with-migration')) {
            $tableName = Str::plural(Str::snake($name));
            $migrationReplacements = array_merge($replacements, ['{{TABLE_NAME}}' => $tableName]);
            $timestamp = date('Y_m_d_His');
            $this->generateFromStub(
                "{$stubPath}/migration.stub",
                "{$pluginDir}/database/migrations/{$timestamp}_create_{$tableName}_table.php",
                $migrationReplacements
            );
        }

        $this->components->info("TARDIS plugin [Tardis{$studlyName}] created successfully.");
        $this->components->twoColumnDetail('Package Directory', $pluginDir);
        $this->newLine();
        $this->components->bulletList([
            "Add \"tardis/{$lowercaseName}\": \"@dev\" to the require section of your root composer.json",
            "Add \"packages/Tardis/{$studlyName}\" to the repositories array in your root composer.json",
            'Run composer update to register the package',
        ]);

        return self::SUCCESS;
    }

    protected function createDirectories(string $pluginDir): void
    {
        $directories = [
            "{$pluginDir}/src/Models",
            "{$pluginDir}/database/migrations",
            "{$pluginDir}/config",
            "{$pluginDir}/resources/views/pages/admin",
            "{$pluginDir}/routes",
        ];

        foreach ($directories as $directory) {
            File::ensureDirectoryExists($directory);
        }
    }

    protected function buildReplacements(string $studlyName, string $lowercaseName, string $namespace): array
    {
        $replacements = [
            '{{STUDLY_NAME}}' => $studlyName,
            '{{LOWERCASE_NAME}}' => $lowercaseName,
            '{{NAMESPACE}}' => $namespace,
            '{{MENU_IMPORT}}' => '',
            '{{WIDGET_IMPORT}}' => '',
            '{{SETTINGS_IMPORT}}' => '',
            '{{MENU_INTERFACE}}' => '',
            '{{WIDGET_INTERFACE}}' => '',
            '{{SETTINGS_INTERFACE}}' => '',
            '{{MENU_METHOD}}' => '',
            '{{WIDGET_METHOD}}' => '',
            '{{SETTINGS_METHOD}}' => '',
        ];

        if ($this->option('with-menu')) {
            $replacements['{{MENU_IMPORT}}'] = 'use Tardis\Contracts\Plugins\Features\Provider\MenuItems;';
            $replacements['{{MENU_INTERFACE}}'] = ', MenuItems';
            $replacements['{{MENU_METHOD}}'] = $this->getMenuMethod();
        }

        if ($this->option('with-widgets')) {
            $replacements['{{WIDGET_IMPORT}}'] = 'use Tardis\Contracts\Plugins\Features\Provider\Widgets;';
            $replacements['{{WIDGET_INTERFACE}}'] = ', Widgets';
            $replacements['{{WIDGET_METHOD}}'] = $this->getWidgetMethod();
        }

        if ($this->option('with-settings')) {
            $replacements['{{SETTINGS_IMPORT}}'] = 'use Tardis\Contracts\Plugins\Features\Provider\Settings;';
            $replacements['{{SETTINGS_INTERFACE}}'] = ', Settings';
            $replacements['{{SETTINGS_METHOD}}'] = $this->getSettingsMethod();
        }

        return $replacements;
    }

    protected function generateFromStub(string $stubPath, string $destinationPath, array $replacements): void
    {
        $content = File::get($stubPath);
        $content = str_replace(array_keys($replacements), array_values($replacements), $content);
        File::put($destinationPath, $content);
    }

    protected function getMenuMethod(): string
    {
        return <<<'PHP'

    public function provideMenuItems(): array
    {
        return [
            //
        ];
    }
PHP;
    }

    protected function getWidgetMethod(): string
    {
        return <<<'PHP'

    public function provideWidgets(): array
    {
        return [
            //
        ];
    }
PHP;
    }

    protected function getSettingsMethod(): string
    {
        return <<<'PHP'

    public function provideSettings(): array
    {
        return [
            //
        ];
    }
PHP;
    }
}
