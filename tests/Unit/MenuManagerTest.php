<?php

declare(strict_types=1);

use Tardis\Classes\MenuItem;
use Tardis\Manager\MenuManager;

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

test('menu manager forGroup filters by group', function () {
    $manager = new MenuManager;
    $item1 = (new MenuItem('Users'))->group('admin');
    $item2 = (new MenuItem('Posts'))->group('content');
    $item3 = (new MenuItem('Categories'))->group('content');

    $manager->addItems($item1, $item2, $item3);

    $contentItems = $manager->forGroup('content');
    expect($contentItems)->toHaveCount(2);
});

test('menu manager groups returns grouped items', function () {
    $manager = new MenuManager;
    $item1 = (new MenuItem('Users'))->group('admin');
    $item2 = (new MenuItem('Posts'))->group('content');

    $manager->addItems($item1, $item2);

    $groups = $manager->groups();
    expect($groups)->toHaveKey('admin')
        ->and($groups)->toHaveKey('content');
});
