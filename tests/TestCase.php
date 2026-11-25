<?php

declare(strict_types=1);

namespace Tests;

use BladeUI\Heroicons\BladeHeroiconsServiceProvider;
use BladeUI\Icons\BladeIconsServiceProvider;
use Illuminate\Cache\CacheServiceProvider;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Schema;
use Livewire\LivewireServiceProvider;
use Orchestra\Testbench\TestCase as OrchestraTestCase;
use Tardis\TardisServiceProvider;

abstract class TestCase extends OrchestraTestCase
{
    protected string $tardisStorage = '';

    protected function setUp(): void
    {
        parent::setUp();

        // An in-memory SQLite database is new for every test; MySQL and
        // PostgreSQL keep their tables, so start each test from an empty one.
        if ((getenv('TARDIS_TEST_DB') ?: 'sqlite') !== 'sqlite') {
            Schema::connection('testing')->dropAllTables();
        }

        // TARDIS_TEST_STATE=database runs the whole suite on the database state
        // driver (CI does both); its table exists before anything is stored.
        if (getenv('TARDIS_TEST_STATE') === 'database') {
            (require __DIR__.'/../database/migrations/2026_11_25_000001_create_tardis_state_table.php')->up();
        }
    }

    protected function tearDown(): void
    {
        // Every test boots a new application with a new connection; on MySQL
        // and PostgreSQL they would pile up to the server's connection limit.
        if ((getenv('TARDIS_TEST_DB') ?: 'sqlite') !== 'sqlite' && isset($this->app)) {
            $this->app['db']->disconnect('testing');
        }

        parent::tearDown();

        if ($this->tardisStorage !== '') {
            (new Filesystem)->deleteDirectory($this->tardisStorage);
        }
    }

    protected function getPackageProviders($app): array
    {
        return [
            CacheServiceProvider::class,
            BladeIconsServiceProvider::class,
            BladeHeroiconsServiceProvider::class,
            LivewireServiceProvider::class,
            TardisServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        // Never let tests write plugin/settings/BREAD state into the shared skeleton.
        $this->tardisStorage = sys_get_temp_dir().'/tardis-testbench-storage-'.uniqid();
        $app->useStoragePath($this->tardisStorage);

        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', self::testing_connection());

        // Most tests exercise pages as an anonymous caller; authorization has its
        // own tests (AdminAccessTest, BreadAuthorizationTest) that switch it on.
        $app['config']->set('tardis.authorization.enabled', false);

        $app['config']->set('tardis.state.driver', getenv('TARDIS_TEST_STATE') ?: 'file');

        $app['config']->set('cache.default', 'array');
        $app['config']->set('cache.stores.array', [
            'driver' => 'array',
        ]);
    }

    /**
     * SQLite in memory by default. CI also runs the suite on MySQL and
     * PostgreSQL: TARDIS_TEST_DB=mysql|pgsql with the usual DB_* variables.
     *
     * @return array<string, mixed>
     */
    private static function testing_connection(): array
    {
        $driver = (string) (getenv('TARDIS_TEST_DB') ?: 'sqlite');

        if ($driver === 'sqlite') {
            return ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''];
        }

        return [
            'driver' => $driver,
            'host' => getenv('DB_HOST') ?: '127.0.0.1',
            'port' => getenv('DB_PORT') ?: ($driver === 'pgsql' ? '5432' : '3306'),
            'database' => getenv('DB_DATABASE') ?: 'tardis_test',
            'username' => getenv('DB_USERNAME') ?: 'root',
            'password' => getenv('DB_PASSWORD') ?: '',
            'charset' => $driver === 'pgsql' ? 'utf8' : 'utf8mb4',
            'collation' => $driver === 'pgsql' ? null : 'utf8mb4_unicode_ci',
            'prefix' => '',
            'search_path' => 'public',
        ];
    }
}
