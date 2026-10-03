<?php

declare(strict_types=1);

use Livewire\Livewire;
use Tardis\Manager\MenuManager;
use Tardis\Manager\PluginManager;
use Tardis\Menu\MenuOverlay;

function freshMenu(): MenuManager
{
    app()->forgetInstance(MenuManager::class);
    $menu = app(MenuManager::class);
    $menu->collectFromPlugins(app(PluginManager::class));

    return $menu;
}

function menuTitles(bool $withHidden = false): array
{
    return freshMenu()->all($withHidden)->pluck('title')->all();
}

test('the overlay is empty until something changes', function () {
    expect(app(MenuOverlay::class)->isEmpty())->toBeTrue();
});

test('a hidden item leaves the sidebar but stays editable', function () {
    $media = __('tardis::menu.media');
    expect(menuTitles())->toContain($media);

    app(MenuOverlay::class)->change('tardis.media', ['hidden' => true]);

    expect(menuTitles())->not->toContain($media)
        ->and(menuTitles(withHidden: true))->toContain($media);

    app(MenuOverlay::class)->change('tardis.media', ['hidden' => false]);
    expect(menuTitles())->toContain($media)->and(app(MenuOverlay::class)->isEmpty())->toBeTrue();
});

test('an item can be renamed, moved to another section and re-ordered', function () {
    app(MenuOverlay::class)->change('tardis.media', ['title' => 'Files', 'section' => 'Library', 'order' => -5]);

    $first = freshMenu()->all()->first();

    expect($first->title)->toBe('Files')->and($first->section)->toBe('Library');
});

test('a custom link appears with its own url and rejects unsafe ones', function () {
    $overlay = app(MenuOverlay::class);
    $id = $overlay->addCustom(['title' => 'Docs', 'url' => 'https://example.test/docs', 'icon' => 'book-open', 'new_tab' => true]);

    $item = freshMenu()->all()->firstWhere('title', 'Docs');

    expect($item)->not->toBeNull()
        ->and($item->href())->toBe('https://example.test/docs')
        ->and($item->newTab)->toBeTrue()
        ->and($item->icon)->toBe('heroicon-o-book-open');

    $overlay->removeCustom($id);
    expect(menuTitles())->not->toContain('Docs');

    foreach (['javascript:alert(1)', 'data:text/html,x', '//evil.test', 'docs'] as $bad) {
        expect(fn () => $overlay->addCustom(['title' => 'X', 'url' => $bad]))->toThrow(InvalidArgumentException::class);
    }
});

test('a custom link can require an ability', function () {
    app(MenuOverlay::class)->addCustom(['title' => 'Secret', 'url' => '/secret', 'permission' => 'see secret']);

    // with no authorization plugin deciding, it is visible; the permission is carried on the item
    expect(freshMenu()->all()->firstWhere('title', 'Secret')->permission)->toBe('see secret');
});

test('an icon name that is not a plain heroicon name falls back', function () {
    app(MenuOverlay::class)->addCustom(['title' => 'Odd', 'url' => '/odd', 'icon' => '"><script>']);

    expect(freshMenu()->all()->firstWhere('title', 'Odd')->icon)->toBe('heroicon-o-link');
});

test('the builder page hides, renames, moves and restores', function () {
    $page = Livewire::test('tardis::pages.menu-builder')
        ->call('toggle', 'tardis.media')
        ->call('rename', 'tardis.media', 'Files');

    expect(menuTitles())->not->toContain('Files');
    expect(menuTitles(withHidden: true))->toContain('Files');

    $page->call('toggle', 'tardis.media');
    $ids = fn () => freshMenu()->all()->map->id()->values()->all();
    $before = $ids();
    $page->call('move', $before[1], -1);

    expect($ids()[0])->toBe($before[1]);

    $page->call('resetMenu');
    expect(app(MenuOverlay::class)->isEmpty())->toBeTrue();
});

test('the builder page adds and removes a link and reports a bad url', function () {
    $page = Livewire::test('tardis::pages.menu-builder')
        ->set('linkTitle', 'Docs')->set('linkUrl', 'javascript:alert(1)')
        ->call('addLink')
        ->assertHasErrors('linkUrl');

    $page->set('linkUrl', '/docs')->call('addLink')->assertHasNoErrors()->assertSet('linkTitle', '');

    expect(menuTitles())->toContain('Docs');

    $id = app(MenuOverlay::class)->custom()[0]['id'];
    $page->call('removeLink', $id);

    expect(menuTitles())->not->toContain('Docs');
});

test('the sidebar entry for the builder is listed', function () {
    expect(menuTitles())->toContain(__('tardis::menu.menu_builder'));
});
