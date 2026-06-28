<?php

declare(strict_types=1);

use Illuminate\Support\Collection;
use Tardis\Classes\MenuItem;

test('menu item can be created with title', function () {
    $item = new MenuItem('Dashboard');

    expect($item->title)->toBe('Dashboard');
});

test('menu item can be created with icon', function () {
    $item = new MenuItem('Dashboard', 'home');

    expect($item->title)->toBe('Dashboard')
        ->and($item->icon)->toBe('home');
});

test('menu item icon defaults to null', function () {
    $item = new MenuItem('Dashboard');

    expect($item->icon)->toBeNull();
});

test('menu item fluent route method', function () {
    $item = (new MenuItem('Users'))->route('admin.users.index');

    expect($item->routeName)->toBe('admin.users.index')
        ->and($item->routeParams)->toBe([]);
});

test('menu item fluent route method with params', function () {
    $item = (new MenuItem('Post'))->route('admin.posts.show', ['id' => 1]);

    expect($item->routeName)->toBe('admin.posts.show')
        ->and($item->routeParams)->toBe(['id' => 1]);
});

test('menu item fluent url method', function () {
    $item = (new MenuItem('External'))->url('https://example.com');

    expect($item->url)->toBe('https://example.com');
});

test('menu item fluent permission method', function () {
    $item = (new MenuItem('Admin'))->permission('manage-users');

    expect($item->permission)->toBe('manage-users');
});

test('menu item fluent badge method', function () {
    $item = (new MenuItem('Notifications'))->badge('red', '5');

    expect($item->badgeColor)->toBe('red')
        ->and($item->badgeValue)->toBe('5');
});

test('menu item badge with null value', function () {
    $item = (new MenuItem('Notifications'))->badge('blue');

    expect($item->badgeColor)->toBe('blue')
        ->and($item->badgeValue)->toBeNull();
});

test('menu item fluent order method', function () {
    $item = (new MenuItem('Settings'))->order(1);

    expect($item->order)->toBe(1);
});

test('menu item has default order of 50', function () {
    $item = new MenuItem('Settings');

    expect($item->order)->toBe(50);
});

test('menu item fluent addChildren method', function () {
    $child1 = new MenuItem('Create');
    $child2 = new MenuItem('Edit');
    $parent = (new MenuItem('Posts'))->addChildren($child1, $child2);

    expect($parent->children)->toHaveCount(2)
        ->and($parent->children->get(0)->title)->toBe('Create')
        ->and($parent->children->get(1)->title)->toBe('Edit');
});

test('menu item href returns # when no route or url', function () {
    $item = new MenuItem('Placeholder');

    expect($item->href())->toBe('#');
});

test('menu item href returns url when set', function () {
    $item = (new MenuItem('External'))->url('https://example.com');

    expect($item->href())->toBe('https://example.com');
});

test('menu item isVisible returns true when no permission set', function () {
    $item = new MenuItem('Dashboard');

    expect($item->isVisible())->toBeTrue();
});

test('menu item children defaults to empty collection', function () {
    $item = new MenuItem('Posts');

    expect($item->children)->toBeInstanceOf(Collection::class)
        ->and($item->children->isEmpty())->toBeTrue();
});
