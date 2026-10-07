<?php

declare(strict_types=1);

use Tardis\Theme\BuiltinThemes;
use Tardis\Theme\Theme;

function themeColors(array $override = []): array
{
    return $override + [
        'primary' => 'oklch(45% 0.2 260)',
        'base-100' => 'oklch(97% 0.01 260)',
        'base-content' => 'oklch(20% 0.05 260)',
    ];
}

test('a valid definition becomes a theme', function () {
    $theme = Theme::fromArray(['name' => 'ocean', 'scheme' => 'dark', 'label' => 'Ocean', 'colors' => themeColors()]);

    expect($theme)->toBeInstanceOf(Theme::class)
        ->and($theme->name)->toBe('ocean')
        ->and($theme->scheme)->toBe('dark')
        ->and($theme->label)->toBe('Ocean')
        ->and($theme->builtin)->toBeFalse();
});

test('the label defaults to a headline of the name', function () {
    expect(Theme::fromArray(['name' => 'deep-sea', 'scheme' => 'light', 'colors' => themeColors()])->label)->toBe('Deep Sea');
});

test('an unsafe name or an unknown scheme is rejected', function (array $definition) {
    expect(Theme::fromArray($definition + ['colors' => themeColors()]))->toBeNull();
})->with([
    'uppercase' => [['name' => 'Ocean', 'scheme' => 'dark']],
    'space' => [['name' => 'deep sea', 'scheme' => 'dark']],
    'quote' => [['name' => 'a"b', 'scheme' => 'dark']],
    'empty' => [['name' => '', 'scheme' => 'dark']],
    'scheme' => [['name' => 'ocean', 'scheme' => 'sepia']],
    'missing scheme' => [['name' => 'ocean']],
]);

test('a theme without the three base colours is rejected', function (array $colors) {
    expect(Theme::fromArray(['name' => 'ocean', 'scheme' => 'dark', 'colors' => $colors]))->toBeNull();
})->with([
    'none' => [[]],
    'no primary' => [['base-100' => '#fff', 'base-content' => '#000']],
    'no base' => [['primary' => '#123456', 'base-content' => '#000']],
]);

test('colour values that could escape the rule are rejected', function (string $value) {
    expect(Theme::fromArray(['name' => 'ocean', 'scheme' => 'dark', 'colors' => themeColors(['primary' => $value])]))->toBeNull();
})->with([
    'brace' => ['red;} body{display:none'],
    'style' => ['</style><script>x</script>'],
    'url' => ['url(javascript:alert(1))'],
    'comment' => ['red/*x*/'],
    'newline' => ["red\nblue"],
]);

test('colour formats a designer actually writes are accepted', function (string $value) {
    expect(Theme::fromArray(['name' => 'ocean', 'scheme' => 'dark', 'colors' => themeColors(['accent' => $value])]))->not->toBeNull();
})->with(['#fff', '#1a2b3c', '#1a2b3c80', 'oklch(70% 0.1 200)', 'oklch(70% 0.1 200 / 0.5)', 'rgb(1 2 3)', 'rgb(1, 2, 3)', 'hsl(200 50% 40%)', 'tomato']);

test('unknown colour keys are dropped, not written', function () {
    $theme = Theme::fromArray(['name' => 'ocean', 'scheme' => 'dark', 'colors' => themeColors(['sidebar' => '#123456', 'evil' => 'red'])]);

    expect($theme->colors)->toHaveKeys(['primary', 'base-100', 'base-content'])
        ->not->toHaveKey('sidebar')->not->toHaveKey('evil');
});

test('it renders a data-theme rule with the scheme and its colours', function () {
    $css = Theme::fromArray(['name' => 'ocean', 'scheme' => 'dark', 'colors' => themeColors()])->css();

    expect($css)->toBe('[data-theme="ocean"]{color-scheme:dark;--color-primary:oklch(45% 0.2 260);--color-base-100:oklch(97% 0.01 260);--color-base-content:oklch(20% 0.05 260);}');
});

test('the preview colours come from the theme colours', function () {
    $theme = Theme::fromArray(['name' => 'ocean', 'scheme' => 'dark', 'colors' => themeColors(['secondary' => '#abcdef'])]);

    expect($theme->previewColors())->toContain('oklch(45% 0.2 260)', '#abcdef');
});

test('the built-in themes are valid and declared in the stylesheet', function () {
    $css = file_get_contents(__DIR__.'/../../resources/css/app.css');

    expect(BuiltinThemes::all())->not->toBeEmpty();

    foreach (BuiltinThemes::all() as $definition) {
        $theme = Theme::fromArray($definition + ['builtin' => true]);

        expect($theme)->not->toBeNull("built-in theme {$definition['name']} is invalid")
            ->and($css)->toContain('name: "'.$definition['name'].'"');
    }
});

test('there is a built-in theme for each scheme', function () {
    $schemes = array_column(BuiltinThemes::all(), 'scheme');

    expect($schemes)->toContain('light')->toContain('dark');
});
