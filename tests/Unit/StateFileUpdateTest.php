<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Tardis\Manager\PluginManager;
use Tardis\Support\UserPreferences;

beforeEach(function () {
    $this->dir = sys_get_temp_dir().'/tardis-state-'.uniqid();
    File::ensureDirectoryExists($this->dir);
});

afterEach(function () {
    File::deleteDirectory($this->dir);
});

/**
 * Two requests (two workers) each load the plugin state at the start. Each
 * then switches off a different plugin; the second write must not undo the
 * first one's change by writing its own stale copy of the list.
 */
test('two managers switching off different plugins keep both changes', function () {
    $path = $this->dir.'/plugins.json';

    $first = new PluginManager($path);
    $second = new PluginManager($path);

    $first->disable('reports');
    $second->disable('newsletter');

    $data = json_decode(File::get($path), true);

    expect($data['disabled'])->toEqualCanonicalizing(['reports', 'newsletter']);
});

test('re-enabling a plugin removes only that plugin from the stored list', function () {
    $path = $this->dir.'/plugins.json';

    (new PluginManager($path))->disable('reports');
    $stale = new PluginManager($path);
    (new PluginManager($path))->disable('newsletter');

    $stale->enable('reports');

    expect(json_decode(File::get($path), true)['disabled'])->toBe(['newsletter']);
});

test('preferences of different users written in turn are both kept', function () {
    $path = $this->dir.'/preferences.json';

    (new UserPreferences($path))->set(1, 'locale', 'tr');
    (new UserPreferences($path))->set(2, 'locale', 'en');

    $prefs = new UserPreferences($path);

    expect($prefs->get(1, 'locale'))->toBe('tr')
        ->and($prefs->get(2, 'locale'))->toBe('en');
});
