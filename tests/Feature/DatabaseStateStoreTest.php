<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tardis\Bread\Sources\JsonBreadSource;
use Tardis\Dashboard\DashboardLayout;
use Tardis\Menu\MenuOverlay;
use Tardis\Menu\SiteMenuStore;
use Tardis\Plugins\PluginManager;
use Tardis\Settings\SettingsManager;
use Tardis\State\DatabaseStateStore;
use Tardis\State\FileStateStore;
use Tardis\State\StateStore;
use Tardis\Support\UserPreferences;
use Tardis\Theme\ThemeManager;

class StateStoreTestModel extends Model
{
    protected $table = 'state_things';
}

function useDatabaseState(): void
{
    config()->set('tardis.state.driver', 'database');
    app()->forgetInstance(StateStore::class);
}

/** @return array<string, string> key => value */
function stateRows(): array
{
    return DB::table('tardis_state')->pluck('value', 'key')->all();
}

beforeEach(function () {
    $this->artisan('migrate');
    useDatabaseState();
});

test('the driver is chosen by tardis.state.driver', function () {
    expect(app(StateStore::class))->toBeInstanceOf(DatabaseStateStore::class);

    config()->set('tardis.state.driver', 'file');
    app()->forgetInstance(StateStore::class);
    expect(app(StateStore::class))->toBeInstanceOf(FileStateStore::class);

    config()->set('tardis.state.driver', 'redis');
    app()->forgetInstance(StateStore::class);
    expect(fn () => app(StateStore::class))->toThrow(InvalidArgumentException::class, 'tardis.state.driver');
});

test('settings, plugins, preferences, menus, dashboard and themes are rows, not files', function () {
    $settings = new SettingsManager;
    $settings->create(['group' => 'site', 'key' => 'title', 'type' => 'text', 'value' => 'Shared']);
    (new PluginManager)->disable('reports');
    (new UserPreferences)->set(7, 'locale', 'tr');
    (new MenuOverlay)->change('tardis.dashboard', ['title' => 'Home']);
    (new SiteMenuStore)->create('footer');
    (new DashboardLayout)->change('welcome', ['hidden' => true]);
    (new ThemeManager)->saveCustom(['name' => 'ocean', 'scheme' => 'dark', 'colors' => ['primary' => '#336699', 'base-100' => '#101820', 'base-content' => '#eeeeee']]);

    expect(array_keys(stateRows()))->toContain(
        'settings/settings.json', 'settings/audit.json', 'plugins.json', 'preferences.json',
        'menus.json', 'site-menus.json', 'dashboard.json', 'themes.json',
    )->and(File::exists(storage_path('tardis/plugins.json')))->toBeFalse()
        ->and(File::exists(storage_path('tardis/settings/settings.json')))->toBeFalse();

    // Another server: fresh instances read the same rows.
    app()->forgetInstance(StateStore::class);

    expect((new SettingsManager)->get('site.title'))->toBe('Shared')
        ->and(json_decode(stateRows()['plugins.json'], true)['disabled'])->toBe(['reports'])
        ->and((new UserPreferences)->get(7, 'locale'))->toBe('tr')
        ->and((new DashboardLayout)->all())->toBe(['welcome' => ['hidden' => true]]);

    (new DashboardLayout)->reset();
    expect(stateRows())->not->toHaveKey('dashboard.json');
});

test('a change written by another server is seen through the signature', function () {
    $settings = new SettingsManager;
    $settings->create(['group' => 'site', 'key' => 'title', 'type' => 'text', 'value' => 'Before']);
    expect($settings->get('site.title'))->toBe('Before');

    // Another server writes the row.
    $items = json_decode(stateRows()['settings/settings.json'], true);
    $items = array_map(fn (array $item) => $item['key'] === 'title' ? ['value' => 'After'] + $item : $item, $items);
    DB::table('tardis_state')->where('key', 'settings/settings.json')->update(['value' => json_encode($items), 'version' => DB::raw('version + 1')]);

    // The next request (a new scoped store) sees it; the long-lived manager
    // notices through the version.
    app()->forgetInstance(StateStore::class);

    expect($settings->get('site.title'))->toBe('After');
});

test('two writers changing the same entry both land', function () {
    $first = new PluginManager;
    $second = new PluginManager;

    $first->disable('reports');
    app()->forgetInstance(StateStore::class);
    $second->disable('newsletter');

    expect(json_decode(stateRows()['plugins.json'], true)['disabled'])->toEqualCanonicalizing(['reports', 'newsletter']);
});

test('BREAD definitions, their backups and rollback live in the table', function () {
    $source = new JsonBreadSource;
    $definition = ['slug' => 'things', 'model' => StateStoreTestModel::class, 'name' => 'Thing', 'name_plural' => 'Things', 'table' => 'state_things'];

    $source->save($definition);
    $source->save(['name_plural' => 'All things'] + $definition);

    expect(stateRows())->toHaveKey('bread/things.json')
        ->and($source->all()->keys()->all())->toBe(['things'])
        ->and($source->find('things')->namePlural)->toBe('All things')
        ->and($source->backups('things'))->toHaveCount(1)
        ->and(File::isDirectory(storage_path('tardis/bread')))->toBeFalse();

    expect($source->rollback('things', $source->backups('things')->first()['name']))->toBeTrue()
        ->and((new JsonBreadSource)->find('things')->namePlural)->toBe('Things');

    expect($source->delete('things'))->toBeTrue()
        ->and($source->has('things'))->toBeFalse()
        ->and(stateRows())->not->toHaveKey('bread/things.json');
});

test('a store given its own path keeps using that file', function () {
    $dir = sys_get_temp_dir().'/tardis-own-'.uniqid();
    (new PluginManager($dir.'/plugins.json'))->disable('reports');

    expect(File::exists($dir.'/plugins.json'))->toBeTrue()
        ->and(stateRows())->not->toHaveKey('plugins.json');

    File::deleteDirectory($dir);
});

test('tardis:state:copy moves the file state into the table and back', function () {
    config()->set('tardis.state.driver', 'file');
    app()->forgetInstance(StateStore::class);
    (new PluginManager)->disable('reports');
    (new JsonBreadSource)->save(['slug' => 'things', 'model' => StateStoreTestModel::class, 'name' => 'Thing', 'name_plural' => 'Things', 'table' => 'state_things']);

    $this->artisan('tardis:state:copy', ['--to' => 'database'])->assertSuccessful();

    expect(stateRows())->toHaveKeys(['plugins.json', 'bread/things.json'])
        ->and(stateRows())->not->toHaveKey('plugins.json.lock');

    // Existing entries are kept unless --force.
    DB::table('tardis_state')->where('key', 'plugins.json')->update(['value' => json_encode(['disabled' => ['other']])]);
    $this->artisan('tardis:state:copy', ['--to' => 'database'])->assertSuccessful()->expectsOutputToContain('kept');
    expect(json_decode(stateRows()['plugins.json'], true)['disabled'])->toBe(['other']);

    File::delete(storage_path('tardis/plugins.json'));
    $this->artisan('tardis:state:copy', ['--to' => 'file'])->assertSuccessful();
    expect(json_decode(File::get(storage_path('tardis/plugins.json')), true)['disabled'])->toBe(['other']);

    $this->artisan('tardis:state:copy', ['--to' => 'redis'])->assertFailed();
});

test('before the migration the database driver reads nothing and does not fail the page', function () {
    DB::getSchemaBuilder()->drop('tardis_state');
    app()->forgetInstance(StateStore::class);

    expect((new UserPreferences)->get(7, 'locale', 'en'))->toBe('en')
        ->and((new SettingsManager)->all())->not->toBeEmpty(); // the preset
});
