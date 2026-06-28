<?php

declare(strict_types=1);

use Tardis\Classes\MenuItem;
use Tardis\Classes\UserMenuItem;
use Tardis\Manager\MenuManager;
use Illuminate\Support\Collection;

test('menu manager can be instantiated', function () {
    $manager = new MenuManager;

    expect($manager)->toBeInstanceOf(MenuManager::class);
});

test('menu manager all starts empty', function () {
    $manager = new MenuManager;

    expect($manager->all())->toHaveCount(0);
});

test('menu manager addItems adds items', function () {
    $manager = new MenuManager;
    $item1 = new MenuItem('Dashboard');
    $item2 = new MenuItem('Settings');

    $manager->addItems($item1, $item2);

    expect($manager->all())->toHaveCount(2);
});

test('menu manager all returns sorted by order', function () {
    $manager = new MenuManager;
    $item1 = (new MenuItem('Settings'))->order(10);
    $item2 = (new MenuItem('Dashboard'))->order(1);

    $manager->addItems($item1, $item2);

    $items = $manager->all();
    expect($items->first()->title)->toBe('Dashboard')
        ->and($items->last()->title)->toBe('Settings');
});

test('menu manager tree returns all items', function () {
    $manager = new MenuManager;
    $item1 = new MenuItem('Users');
    $item2 = new MenuItem('Posts');

    $manager->addItems($item1, $item2);

    $tree = $manager->tree();
    expect($tree)->toHaveCount(2);
});

test('menu manager userMenu returns user menu items', function () {
    $manager = new MenuManager;
    $userItem = (new UserMenuItem('Profile'))->route('profile.edit');
    $sidebarItem = new MenuItem('Dashboard');

    $manager->addItems($userItem, $sidebarItem);

    $userMenu = $manager->userMenu();
    expect($userMenu)->toHaveCount(1)
        ->and($userMenu->first()->title)->toBe('Profile');
});
