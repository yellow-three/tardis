<?php

declare(strict_types=1);

namespace Tardis\Diagnostics;

use Illuminate\Support\Facades\Schema;
use Tardis\Assets\Asset;
use Tardis\Bread\BreadManager;
use Tardis\Contracts\Plugins\Features\Provider\CSS;
use Tardis\Contracts\Plugins\Features\Provider\JS;
use Tardis\Manager\PluginManager;
use Tardis\Manager\ThemeManager;

/**
 * Runs the `tardis:doctor` checks and collects their results.
 *
 * Every check is isolated: a check that throws (a missing table while the
 * database is unreachable, a plugin whose provider blows up) becomes a failure
 * for that check alone, so one broken area never hides the state of the rest.
 */
final class DoctorReport
{
    /**
     * @param  list<CheckResult>  $checks
     */
    public function __construct(
        public readonly array $checks,
    ) {}

    public static function run(): self
    {
        $checks = [
            self::guard('PHP version', self::checkPhpVersion(...)),
            self::guard('Laravel version', self::checkLaravelVersion(...)),
            self::guard('Storage', self::checkStorage(...)),
            self::guard('Database tables', self::checkTables(...)),
            self::guard('Published assets', self::checkAssets(...)),
            self::guard('Route cache', self::checkRouteCache(...)),
            self::guard('Authorization plugin', self::checkAuthorization(...)),
            self::guard('Themes', self::checkThemes(...)),
            self::guard('Plugins', self::checkPlugins(...)),
            self::guard('BREAD definitions', self::checkBread(...)),
        ];

        return new self($checks);
    }

    public function hasFailures(): bool
    {
        foreach ($this->checks as $check) {
            if ($check->isFailure()) {
                return true;
            }
        }

        return false;
    }

    public function hasWarnings(): bool
    {
        foreach ($this->checks as $check) {
            if ($check->isWarning()) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<CheckResult>
     */
    public function failures(): array
    {
        return array_values(array_filter($this->checks, fn (CheckResult $check) => $check->isFailure()));
    }

    /**
     * @return array{ok: bool, checks: list<array{name: string, status: string, message: string, hint: ?string}>}
     */
    public function toArray(): array
    {
        return [
            'ok' => ! $this->hasFailures(),
            'checks' => array_map(fn (CheckResult $check) => $check->toArray(), $this->checks),
        ];
    }

    public function toJson(int $options = 0): string
    {
        return (string) json_encode($this->toArray(), $options);
    }

    /**
     * @param  callable(): CheckResult  $check
     */
    private static function guard(string $name, callable $check): CheckResult
    {
        try {
            return $check();
        } catch (\Throwable $e) {
            return CheckResult::fail($name, 'The check could not run: '.$e->getMessage());
        }
    }

    private static function checkPhpVersion(): CheckResult
    {
        $required = self::composerRequirement('php');

        if ($required === null) {
            return CheckResult::warn('PHP version', 'No PHP requirement found in composer.json.');
        }

        if (self::satisfies(PHP_VERSION, $required)) {
            return CheckResult::ok('PHP version', sprintf('PHP %s satisfies %s.', PHP_VERSION, $required));
        }

        return CheckResult::fail(
            'PHP version',
            sprintf('PHP %s does not satisfy %s.', PHP_VERSION, $required),
            'Upgrade PHP to a supported version.',
        );
    }

    private static function checkLaravelVersion(): CheckResult
    {
        $required = self::composerRequirement('laravel/framework');
        $version = app()->version();

        if ($required === null) {
            return CheckResult::warn('Laravel version', sprintf('Laravel %s installed; no requirement found in composer.json.', $version));
        }

        if (self::satisfies($version, $required)) {
            return CheckResult::ok('Laravel version', sprintf('Laravel %s satisfies %s.', $version, $required));
        }

        return CheckResult::fail(
            'Laravel version',
            sprintf('Laravel %s does not satisfy %s.', $version, $required),
            'Update laravel/framework to a supported version.',
        );
    }

    private static function checkStorage(): CheckResult
    {
        $paths = [storage_path(), storage_path('framework')];

        foreach ($paths as $path) {
            if (! is_dir($path)) {
                return CheckResult::fail('Storage', "The directory [{$path}] does not exist.", 'Create the storage directory and its framework subdirectory.');
            }

            if (! is_writable($path)) {
                return CheckResult::fail('Storage', "The directory [{$path}] is not writable.", 'Grant the web server write access to storage.');
            }
        }

        return CheckResult::ok('Storage', 'The storage directories exist and are writable.');
    }

    private static function checkTables(): CheckResult
    {
        $tables = [
            'tardis_permissions',
            'tardis_roles',
            'tardis_permission_role',
            'tardis_role_user',
            'tardis_media',
        ];

        $missing = array_values(array_filter($tables, fn (string $table) => ! Schema::hasTable($table)));

        if ($missing !== []) {
            return CheckResult::fail(
                'Database tables',
                'Missing tables: '.implode(', ', $missing).'.',
                'Run php artisan migrate.',
            );
        }

        return CheckResult::ok('Database tables', 'All TARDIS tables are present.');
    }

    private static function checkAssets(): CheckResult
    {
        $published = public_path('vendor/tardis/assets');
        $source = dirname(__DIR__, 2).'/dist/assets';
        $files = ['app.css', 'app.js'];

        $missing = [];
        $stale = [];

        foreach ($files as $file) {
            $publishedFile = $published.'/'.$file;
            $sourceFile = $source.'/'.$file;

            if (! is_file($publishedFile)) {
                $missing[] = $file;

                continue;
            }

            if (is_file($sourceFile) && hash_file('sha256', $publishedFile) !== hash_file('sha256', $sourceFile)) {
                $stale[] = $file;
            }
        }

        if ($missing !== []) {
            return CheckResult::fail(
                'Published assets',
                'Missing published assets: '.implode(', ', $missing).'.',
                'Run php artisan vendor:publish --tag=tardis-assets --force.',
            );
        }

        if ($stale !== []) {
            return CheckResult::warn(
                'Published assets',
                'Published assets differ from the built files: '.implode(', ', $stale).'.',
                'Run npm run build then republish the assets.',
            );
        }

        return CheckResult::ok('Published assets', 'The published assets match the build.');
    }

    private static function checkRouteCache(): CheckResult
    {
        if (is_file(app()->getCachedRoutesPath())) {
            return CheckResult::warn(
                'Route cache',
                'A cached route file is present; BREAD routes are generated at boot and may be stale.',
                'Run php artisan route:clear after changing BREAD definitions.',
            );
        }

        return CheckResult::ok('Route cache', 'No cached route file is present.');
    }

    private static function checkAuthorization(): CheckResult
    {
        // Authorization is opt-out by design; disabling it is a valid (if risky)
        // configuration, so it warns rather than failing the whole install.
        if (! config('tardis.authorization.enabled', true)) {
            return CheckResult::warn(
                'Authorization plugin',
                'Authorization is disabled; every authenticated user can open the panel.',
                'Enable tardis.authorization.enabled or protect /admin another way.',
            );
        }

        $plugins = app(PluginManager::class)->authorizationPlugins();

        if ($plugins->isEmpty()) {
            return CheckResult::fail(
                'Authorization plugin',
                'No authorization plugin is enabled.',
                'Enable the TARDIS authorization plugin or register your own.',
            );
        }

        return CheckResult::ok('Authorization plugin', $plugins->count().' authorization plugin(s) enabled.');
    }

    /**
     * Themes are data now (storage/tardis/themes.json), so there is no build
     * artefact to look for; the only thing that can be wrong is a corrupt file.
     */
    private static function checkThemes(): CheckResult
    {
        $path = storage_path('tardis/themes.json');

        if (is_file($path) && ! is_array(json_decode((string) file_get_contents($path), true))) {
            return CheckResult::warn('Themes', 'storage/tardis/themes.json is not valid JSON; custom themes are ignored.');
        }

        $count = app(ThemeManager::class)->all()->count();

        return CheckResult::ok('Themes', "{$count} themes available.");
    }

    private static function checkPlugins(): CheckResult
    {
        $enabled = app(PluginManager::class)->enabled();

        if ($enabled->isEmpty()) {
            return CheckResult::warn('Plugins', 'No plugins are enabled.');
        }

        $missing = [];

        foreach ($enabled as $name => $plugin) {
            $instance = $plugin['instance'] ?? null;

            if (! is_object($instance)) {
                $missing[] = "{$name} (does not resolve)";

                continue;
            }

            foreach ([[CSS::class, 'provideCSS'], [JS::class, 'provideJS']] as [$contract, $method]) {
                if (! $instance instanceof $contract) {
                    continue;
                }

                $provided = $instance->{$method}();

                foreach (is_array($provided) ? $provided : [$provided] as $asset) {
                    if ($asset instanceof Asset && $asset->file !== null && ! is_file($asset->file)) {
                        $missing[] = "{$name}: {$asset->file}";
                    }
                }
            }
        }

        if ($missing !== []) {
            return CheckResult::fail('Plugins', 'Missing plugin assets: '.implode(', ', $missing).'.');
        }

        return CheckResult::ok('Plugins', $enabled->count().' plugin(s) enabled and resolvable.');
    }

    private static function checkBread(): CheckResult
    {
        $definitions = app(BreadManager::class)->all();

        if ($definitions->isEmpty()) {
            return CheckResult::ok('BREAD definitions', 'No BREAD definitions to check.');
        }

        $problems = [];

        foreach ($definitions as $definition) {
            if (! class_exists($definition->model)) {
                $problems[] = "{$definition->slug}: model [{$definition->model}] not found";

                continue;
            }

            try {
                $table = (new $definition->model)->getTable();
            } catch (\Throwable $e) {
                $problems[] = "{$definition->slug}: cannot resolve table ({$e->getMessage()})";

                continue;
            }

            if (! Schema::hasTable($table)) {
                $problems[] = "{$definition->slug}: table [{$table}] not found";
            }
        }

        if ($problems !== []) {
            return CheckResult::fail('BREAD definitions', implode('; ', $problems).'.');
        }

        return CheckResult::ok('BREAD definitions', $definitions->count().' definition(s) healthy.');
    }

    private static function composerRequirement(string $package): ?string
    {
        $path = dirname(__DIR__, 2).'/composer.json';

        if (! is_file($path)) {
            return null;
        }

        $data = json_decode((string) file_get_contents($path), true);

        if (! is_array($data) || ! is_array($data['require'] ?? null)) {
            return null;
        }

        $requirement = $data['require'][$package] ?? null;

        return is_string($requirement) && $requirement !== '' ? $requirement : null;
    }

    /**
     * A small constraint matcher for the subset composer.json uses here
     * (`^`, `~`, comparison operators, `||`). The package does not depend on
     * composer/semver, so this avoids pulling a dependency in for one check.
     */
    private static function satisfies(string $version, string $constraint): bool
    {
        $version = ltrim(trim($version), 'vV');

        foreach (preg_split('/\s*\|\|\s*/', trim($constraint)) ?: [] as $group) {
            if (self::satisfiesGroup($version, $group)) {
                return true;
            }
        }

        return false;
    }

    private static function satisfiesGroup(string $version, string $group): bool
    {
        $group = trim($group);

        if ($group === '' || $group === '*') {
            return true;
        }

        foreach (preg_split('/[\s,]+/', $group, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $part) {
            if (! self::satisfiesConstraint($version, $part)) {
                return false;
            }
        }

        return true;
    }

    private static function satisfiesConstraint(string $version, string $constraint): bool
    {
        $constraint = trim($constraint);

        if ($constraint === '' || $constraint === '*') {
            return true;
        }

        if (preg_match('/^(>=|<=|>|<|==|=|!=)\s*(.+)$/', $constraint, $matches) === 1) {
            $operator = $matches[1] === '=' ? '==' : $matches[1];

            return version_compare($version, ltrim(trim($matches[2]), 'vV'), $operator);
        }

        if (str_starts_with($constraint, '^')) {
            $base = ltrim(substr($constraint, 1), 'vV');
            $parts = array_map('intval', explode('.', $base));
            $major = $parts[0] ?? 0;
            $minor = $parts[1] ?? 0;
            $upper = $major > 0 ? ($major + 1).'.0.0' : '0.'.($minor + 1).'.0';

            return version_compare($version, $base, '>=') && version_compare($version, $upper, '<');
        }

        if (str_starts_with($constraint, '~')) {
            $base = ltrim(substr($constraint, 1), 'vV');
            $parts = explode('.', $base);
            $major = (int) ($parts[0] ?? 0);
            $minor = (int) ($parts[1] ?? 0);
            $upper = count($parts) >= 3 ? $major.'.'.($minor + 1).'.0' : ($major + 1).'.0';

            return version_compare($version, $base, '>=') && version_compare($version, $upper, '<');
        }

        return version_compare($version, ltrim($constraint, 'vV'), '==');
    }
}
