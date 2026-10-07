<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Tardis\Facades\Tardis;
use Tardis\Manager\ThemeManager;
use Tardis\Support\UserPreferences;
use Tardis\Theme\ThemePreference;

function customTheme(string $name, string $scheme = 'dark', array $colors = []): array
{
    return ['name' => $name, 'scheme' => $scheme, 'label' => ucfirst($name), 'colors' => $colors + [
        'primary' => '#336699', 'base-100' => $scheme === 'dark' ? '#101820' : '#fafafa', 'base-content' => $scheme === 'dark' ? '#eeeeee' : '#111111',
    ]];
}

function themesFile(): string
{
    return storage_path('tardis/themes.json');
}

test('the built-in themes are always available', function () {
    $names = app(ThemeManager::class)->all()->pluck('name')->all();

    expect($names)->toContain('tardis-light', 'tardis-dark');
});

test('themes added in storage/tardis/themes.json are available after the built-in ones', function () {
    File::ensureDirectoryExists(dirname(themesFile()));
    File::put(themesFile(), json_encode(['themes' => [customTheme('ocean'), customTheme('paper', 'light')]]));

    $themes = (new ThemeManager)->all();

    expect($themes->pluck('name')->all())->toBe(['tardis-light', 'tardis-dark', 'ocean', 'paper'])
        ->and($themes->firstWhere('name', 'ocean')->builtin)->toBeFalse()
        ->and($themes->firstWhere('name', 'tardis-dark')->builtin)->toBeTrue();
});

test('an invalid or duplicate custom theme is skipped instead of breaking the page', function () {
    File::ensureDirectoryExists(dirname(themesFile()));
    File::put(themesFile(), json_encode(['themes' => [
        customTheme('good'),
        ['name' => 'Bad Name', 'scheme' => 'dark', 'colors' => []],
        customTheme('tardis-dark', 'light'),
        customTheme('good', 'light'),
    ]]));

    $themes = (new ThemeManager)->all();

    expect($themes->pluck('name')->all())->toBe(['tardis-light', 'tardis-dark', 'good'])
        ->and($themes->firstWhere('name', 'tardis-dark')->scheme)->toBe('dark');
});

test('a corrupt themes file means only the built-in themes', function () {
    File::ensureDirectoryExists(dirname(themesFile()));
    File::put(themesFile(), '{broken');

    expect((new ThemeManager)->all()->pluck('name')->all())->toBe(['tardis-light', 'tardis-dark']);
});

test('a theme can be saved, found and removed', function () {
    $manager = new ThemeManager;

    expect($manager->saveCustom(customTheme('ocean')))->toBeTrue();

    $fresh = new ThemeManager;

    expect($fresh->has('ocean'))->toBeTrue()
        ->and($fresh->find('ocean')->scheme)->toBe('dark');

    expect($fresh->deleteCustom('ocean'))->toBeTrue()
        ->and((new ThemeManager)->has('ocean'))->toBeFalse();
});

test('saving refuses an invalid theme and a built-in name', function () {
    $manager = new ThemeManager;

    expect($manager->saveCustom(['name' => 'x y', 'scheme' => 'dark', 'colors' => []]))->toBeFalse()
        ->and($manager->saveCustom(customTheme('tardis-light')))->toBeFalse()
        ->and(File::exists(themesFile()))->toBeFalse();
});

test('a built-in theme cannot be deleted', function () {
    expect((new ThemeManager)->deleteCustom('tardis-dark'))->toBeFalse();
});

test('only runtime themes produce css, the built-in ones live in the stylesheet', function () {
    $manager = new ThemeManager;
    $manager->saveCustom(customTheme('ocean'));

    $css = (new ThemeManager)->css();

    expect($css)->toContain('[data-theme="ocean"]{')->not->toContain('tardis-dark');
});

test('names can be listed per scheme', function () {
    $manager = new ThemeManager;
    $manager->saveCustom(customTheme('ocean'));
    $manager->saveCustom(customTheme('paper', 'light'));

    $fresh = new ThemeManager;

    expect($fresh->names('dark'))->toBe(['tardis-dark', 'ocean'])
        ->and($fresh->names('light'))->toBe(['tardis-light', 'paper']);
});

test('without any stored choice the built-in themes and dark mode apply', function () {
    expect(app(ThemePreference::class)->resolve(null))->toBe(['mode' => 'dark', 'light' => 'tardis-light', 'dark' => 'tardis-dark']);
});

test('the global defaults in settings apply when the user chose nothing', function () {
    $themes = new ThemeManager;
    $themes->saveCustom(customTheme('ocean'));

    foreach ([['mode', 'light'], ['theme_light', 'tardis-light'], ['theme_dark', 'ocean']] as [$key, $value]) {
        Tardis::settings()->create(['group' => 'appearance', 'key' => $key, 'name' => $key, 'type' => 'text', 'value' => $value]);
    }

    expect((new ThemePreference(new ThemeManager, app(UserPreferences::class), Tardis::settings()))->resolve(null))
        ->toBe(['mode' => 'light', 'light' => 'tardis-light', 'dark' => 'ocean']);
});

test('a user choice overrides the global default, slot by slot', function () {
    Tardis::settings()->create(['group' => 'appearance', 'key' => 'mode', 'name' => 'Mode', 'type' => 'text', 'value' => 'light']);
    app(UserPreferences::class)->set(5, 'theme', ['mode' => 'dark']);

    expect(app(ThemePreference::class)->resolve(5))->toBe(['mode' => 'dark', 'light' => 'tardis-light', 'dark' => 'tardis-dark'])
        ->and(app(ThemePreference::class)->resolve(6)['mode'])->toBe('light');
});

test('a stored choice that is no longer valid falls back instead of failing', function () {
    app(UserPreferences::class)->set(5, 'theme', ['mode' => 'sepia', 'light' => 'deleted-theme', 'dark' => 'tardis-light']);

    // 'tardis-light' is a light theme and cannot fill the dark slot either.
    expect(app(ThemePreference::class)->resolve(5))->toBe(['mode' => 'dark', 'light' => 'tardis-light', 'dark' => 'tardis-dark']);
});

test('saving stores a validated choice for the user', function () {
    $themes = new ThemeManager;
    $themes->saveCustom(customTheme('ocean'));

    $prefs = new ThemePreference(new ThemeManager, app(UserPreferences::class), Tardis::settings());

    expect($prefs->save(9, ['mode' => 'system', 'light' => 'tardis-light', 'dark' => 'ocean']))->toBeTrue()
        ->and($prefs->resolve(9))->toBe(['mode' => 'system', 'light' => 'tardis-light', 'dark' => 'ocean']);
});

test('saving refuses a theme of the wrong scheme or an unknown one', function () {
    $prefs = app(ThemePreference::class);

    expect($prefs->save(9, ['mode' => 'dark', 'light' => 'tardis-dark', 'dark' => 'tardis-dark']))->toBeFalse()
        ->and($prefs->save(9, ['mode' => 'dark', 'light' => 'tardis-light', 'dark' => 'nope']))->toBeFalse()
        ->and($prefs->save(9, ['mode' => 'purple']))->toBeFalse()
        ->and(app(UserPreferences::class)->get(9, 'theme'))->toBeNull();
});
