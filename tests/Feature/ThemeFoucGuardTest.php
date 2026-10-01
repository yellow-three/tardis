<?php

declare(strict_types=1);

/**
 * Guard for the theme FOUC fix in resources/views/layouts/admin.blade.php.
 *
 * The fix is a blocking <script> in <head> that resolves the stored theme and
 * writes data-theme before the first paint. Alpine boots only after the
 * stylesheet loads, so without it the static data-theme="dark" on <html> paints
 * first and light-theme users see a dark flash on every full page load.
 *
 * Two things silently reintroduce that flash, so both are pinned here:
 *
 *  - the blocking script drifting to after @tardisStyles — it stops blocking,
 *    the browser paints with the static attribute first, and the fix silently
 *    becomes a no-op while still looking correct in the template;
 *
 *  - the blocking script and the Alpine store disagreeing on localStorage keys —
 *    the head script resolves one theme, the store resolves another on boot and
 *    overwrites it, producing a flash plus a wrong-theme frame.
 *
 * These are template-source assertions rather than HTTP assertions because the
 * invariant is a *position within the template*: Livewire::test()->html()
 * renders the component only and never emits the layout.
 */
const TARDIS_ADMIN_LAYOUT = __DIR__.'/../../resources/views/layouts/admin.blade.php';

/** The line that writes the theme; its presence marks the blocking script. */
const TARDIS_THEME_APPLY = "document.documentElement.setAttribute('data-theme', applied)";

/** Where the Alpine store begins. */
const TARDIS_THEME_STORE = "Alpine.store('theme'";

function tardisLayout(): string
{
    $layout = file_get_contents(TARDIS_ADMIN_LAYOUT);

    expect($layout)->toBeString();

    return $layout;
}

test('the blocking theme script runs before the stylesheets are linked', function (): void {
    $layout = tardisLayout();

    $apply = strpos($layout, TARDIS_THEME_APPLY);
    $styles = strpos($layout, '@tardisStyles');
    $livewireStyles = strpos($layout, '@livewireStyles');

    expect($apply)->not->toBeFalse('blocking theme script is missing from the admin layout');
    expect($styles)->not->toBeFalse('@tardisStyles is missing from the admin layout');

    // Ordering is the whole point: a script placed after the stylesheet link is
    // no longer blocking and the flash returns.
    expect($apply)
        ->toBeLessThan($styles, 'the blocking theme script must precede @tardisStyles or it no longer blocks paint')
        ->toBeLessThan($livewireStyles, 'the blocking theme script must precede @livewireStyles');
});

test('the theme manifest is resolved in head so the blocking script can read it', function (): void {
    $layout = tardisLayout();

    $manifest = strpos($layout, 'window.__TARDIS_THEMES__ =');
    $headClose = strpos($layout, '</head>');
    $apply = strpos($layout, TARDIS_THEME_APPLY);

    expect($manifest)->not->toBeFalse('window.__TARDIS_THEMES__ assignment is missing');
    expect($headClose)->not->toBeFalse('the admin layout has no closing </head>');

    // The blocking script resolves light/dark theme *names* from the manifest.
    // If the manifest were still emitted in the body the head script would read
    // undefined and fall back to 'winter'/'dark', flashing for any package that
    // ships differently-named themes.
    expect($manifest)
        ->toBeLessThan($headClose, 'the theme manifest must be emitted before </head>')
        ->toBeLessThan($apply, 'the blocking theme script must be able to read the manifest');
});

test('the blocking script and the Alpine store share the same localStorage keys', function (): void {
    $layout = tardisLayout();

    $storeAt = strpos($layout, TARDIS_THEME_STORE);
    expect($storeAt)->not->toBeFalse('the Alpine theme store is missing from the admin layout');

    // Head script: everything before @tardisStyles. Store: from Alpine.store on.
    $headScript = substr($layout, 0, strpos($layout, '@tardisStyles'));
    $store = substr($layout, $storeAt);

    preg_match_all("/'tardis-theme-[a-z]+'/", $headScript, $headKeys);
    preg_match_all("/'tardis-theme-[a-z]+'/", $store, $storeKeys);

    $head = array_values(array_unique($headKeys[0]));
    $storeUnique = array_values(array_unique($storeKeys[0]));

    expect($head)->not->toBe([], 'no localStorage theme keys found in the blocking script');
    expect($head)->toBe($storeUnique);
});

test('the blocking script resolves system mode through matchMedia', function (): void {
    $headScript = substr(tardisLayout(), 0, strpos(tardisLayout(), '@tardisStyles'));

    expect($headScript)
        ->toContain("'system'")
        ->toContain('(prefers-color-scheme: dark)');
});

test('the static data-theme fallback stays for the no-JS path', function (): void {
    // The blocking script is an enhancement, not the only source of the
    // attribute: without JS the html element still needs a valid theme.
    // Note: `[^\n]*` rather than `[^>]*` — the lang attribute interpolates
    // app()->getLocale(), whose -> contains a `>` that would end a tag-scoped
    // character class early.
    expect(tardisLayout())
        ->toMatch('/<html[^\n]*\sdata-theme="dark"/');
});
