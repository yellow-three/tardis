<?php

declare(strict_types=1);

/**
 * Guard for the theme FOUC fix shared by the admin and auth layouts.
 *
 * A blocking <script> in <head> resolves the stored theme and writes data-theme
 * before the first paint. Alpine boots only after the stylesheet loads, so
 * without it the static data-theme="dark" on <html> paints first and light-theme
 * users see a dark flash on every full page load.
 *
 * That logic lives in exactly one place — <x-tardis::theme-boot /> — because the
 * head script and the Alpine store must always resolve the same theme. Every
 * failure mode below is silent, so each one is pinned:
 *
 *  - a layout placing the component after @tardisStyles: it stops blocking, the
 *    browser paints with the static attribute first, and the fix silently becomes
 *    a no-op while still looking correct in the template;
 *
 *  - the component and the Alpine store disagreeing on localStorage keys: the head
 *    script resolves one theme, the store resolves another on boot and overwrites
 *    it, producing a flash plus a wrong-theme frame;
 *
 *  - a layout quietly dropping the component: that page renders with the hardcoded
 *    theme regardless of the visitor's choice. That is precisely how the auth
 *    layout behaved before it adopted the component.
 *
 * Most of these are template-source assertions because the invariant is a
 * *position within the template*: Livewire::test()->html() renders the component
 * only and never emits the layout. The one behavioural test below renders the
 * component for real, since no source assertion can catch a Blade typo or a
 * missing PHP symbol — those would surface as a 500 inside <head>, on every page.
 */

use Illuminate\Support\Facades\Blade;
use Tardis\Manager\AssetManager;

const TARDIS_ADMIN_LAYOUT = __DIR__.'/../../resources/views/layouts/admin.blade.php';
const TARDIS_AUTH_LAYOUT = __DIR__.'/../../resources/views/layouts/auth.blade.php';
const TARDIS_THEME_BOOT = __DIR__.'/../../resources/views/components/theme-boot.blade.php';

/** Every layout that must apply the visitor's stored theme before first paint. */
const TARDIS_LAYOUT_NAMES = ['admin', 'auth'];

/** The line that writes the theme; its presence marks the blocking script. */
const TARDIS_THEME_APPLY = "document.documentElement.setAttribute('data-theme', applied)";

/** Where the Alpine store begins. */
const TARDIS_THEME_STORE = "Alpine.store('theme'";

/** The shared component both layouts delegate to. */
const TARDIS_BOOT_INCLUDE = '<x-tardis::theme-boot />';

function tardisLayout(string $name = 'admin'): string
{
    $paths = [
        'admin' => TARDIS_ADMIN_LAYOUT,
        'auth' => TARDIS_AUTH_LAYOUT,
    ];

    return tardisSource($paths[$name]);
}

function tardisSource(string $path): string
{
    $source = file_get_contents($path);

    expect($source)->toBeString();

    return $source;
}

test('each layout applies the stored theme before the stylesheets are linked', function (string $layout): void {
    $source = tardisLayout($layout);

    $boot = strpos($source, TARDIS_BOOT_INCLUDE);
    $styles = strpos($source, '@tardisStyles');
    $livewireStyles = strpos($source, '@livewireStyles');

    expect($boot)->not->toBeFalse("the theme-boot component is missing from the {$layout} layout");
    expect($styles)->not->toBeFalse("@tardisStyles is missing from the {$layout} layout");
    expect($livewireStyles)->not->toBeFalse("@livewireStyles is missing from the {$layout} layout");

    // Ordering is the whole point: a component placed after the stylesheet link
    // is no longer blocking and the flash returns.
    expect($boot)
        ->toBeLessThan($styles, "the theme-boot component must precede @tardisStyles in {$layout} or it no longer blocks paint")
        ->toBeLessThan($livewireStyles, "the theme-boot component must precede @livewireStyles in {$layout}");
})->with(TARDIS_LAYOUT_NAMES);

test('the boot component renders the manifest and the apply call', function (): void {
    // Rendered for real: a source assertion still passes when the component has a
    // Blade typo or calls a symbol that does not exist, and that fails as a 500
    // inside <head> on every page load.
    @unlink(AssetManager::packageHotPath());

    $html = Blade::render(TARDIS_BOOT_INCLUDE);

    expect($html)
        ->toContain('window.__TARDIS_THEMES__ =')
        ->toContain(TARDIS_THEME_APPLY);
});

test('the boot component emits the manifest before the script that reads it', function (): void {
    $component = tardisSource(TARDIS_THEME_BOOT);

    $manifest = strpos($component, 'window.__TARDIS_THEMES__ =');
    $apply = strpos($component, TARDIS_THEME_APPLY);

    expect($manifest)->not->toBeFalse('the boot component does not publish the themes manifest');
    expect($apply)->not->toBeFalse('the boot component never writes data-theme');

    // The script resolves the light/dark theme *names* from the manifest. Published
    // the other way round it reads undefined and falls back to 'winter'/'dark',
    // flashing for any package that ships differently-named themes.
    expect($manifest)
        ->toBeLessThan($apply, 'the manifest must be published before the script that reads it');
});

test('the boot component and the Alpine store share the same localStorage keys', function (): void {
    $admin = tardisLayout('admin');
    $component = tardisSource(TARDIS_THEME_BOOT);

    $storeAt = strpos($admin, TARDIS_THEME_STORE);
    expect($storeAt)->not->toBeFalse('the Alpine theme store is missing from the admin layout');

    $store = substr($admin, $storeAt);

    preg_match_all("/'tardis-theme-[a-z]+'/", $component, $componentKeys);
    preg_match_all("/'tardis-theme-[a-z]+'/", $store, $storeKeys);

    $bootUnique = array_values(array_unique($componentKeys[0]));
    $storeUnique = array_values(array_unique($storeKeys[0]));

    expect($bootUnique)->not->toBe([], 'no localStorage theme keys found in the boot component');
    expect($bootUnique)->toBe($storeUnique);
});

test('the boot component resolves system mode through matchMedia', function (): void {
    expect(tardisSource(TARDIS_THEME_BOOT))
        ->toContain("'system'")
        ->toContain('(prefers-color-scheme: dark)');
});

test('the static data-theme fallback stays in every layout for the no-JS path', function (string $layout): void {
    // The blocking script is an enhancement, not the only source of the attribute:
    // without JS the html element still needs a valid theme.
    // Note: `[^\n]*` rather than `[^>]*` — the lang attribute interpolates
    // app()->getLocale(), whose -> contains a `>` that would end a tag-scoped
    // character class early.
    expect(tardisLayout($layout))
        ->toMatch('/<html[^\n]*\sdata-theme="dark"/');
})->with(TARDIS_LAYOUT_NAMES);
