<?php

declare(strict_types=1);

use Livewire\Livewire;
use Tardis\Manager\ThemeManager;

function themeManager(): ThemeManager
{
    app()->forgetInstance(ThemeManager::class);

    return app(ThemeManager::class);
}

test('a new theme starts from the light built-in colours', function () {
    $page = Livewire::test('tardis::pages.theme-editor');

    expect($page->get('name'))->toBe('')
        ->and($page->get('colors'))->toHaveKeys(['primary', 'base-100', 'base-content']);
});

test('saving stores a theme every user can then pick', function () {
    Livewire::test('tardis::pages.theme-editor')
        ->set('name', 'sunset')->set('label', 'Sunset')->set('scheme', 'dark')
        ->set('colors.primary', '#ff7a00')
        ->call('save')
        ->assertHasNoErrors()->assertSet('saved', true);

    $theme = themeManager()->find('sunset');

    expect($theme)->not->toBeNull()
        ->and($theme->scheme)->toBe('dark')
        ->and($theme->colors['primary'])->toBe('#ff7a00')
        ->and(themeManager()->css())->toContain('[data-theme="sunset"]');
});

test('an unsafe colour or a bad name is refused with an error', function () {
    Livewire::test('tardis::pages.theme-editor')
        ->set('name', 'bad')->set('colors.primary', 'red;} body{display:none')
        ->call('save')->assertHasErrors('name')->assertSet('saved', false);

    Livewire::test('tardis::pages.theme-editor')
        ->set('name', 'Bad Name!')->call('save')->assertHasErrors('name');

    expect(themeManager()->has('bad'))->toBeFalse();
});

test('a built-in name cannot be overwritten, but a built-in can be duplicated', function () {
    $page = Livewire::test('tardis::pages.theme-editor')->call('edit', 'tardis-dark')
        ->assertSet('name', '')->assertSet('scheme', 'dark');

    $page->set('name', 'tardis-light')->call('save')->assertHasErrors('name');
    $page->set('name', 'night-copy')->call('save')->assertHasNoErrors();

    expect(themeManager()->has('night-copy'))->toBeTrue();
});

test('a custom theme can be edited and deleted but a built-in cannot be deleted', function () {
    themeManager()->saveCustom(['name' => 'mint', 'scheme' => 'light', 'colors' => ['primary' => '#0f0', 'base-100' => '#fff', 'base-content' => '#000']]);

    $page = Livewire::test('tardis::pages.theme-editor')->call('edit', 'mint')->assertSet('name', 'mint');

    $page->call('delete', 'tardis-light');
    expect(themeManager()->has('tardis-light'))->toBeTrue();

    $page->call('delete', 'mint');
    expect(themeManager()->has('mint'))->toBeFalse();
});
