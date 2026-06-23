<?php

declare(strict_types=1);

use Tardis\Contracts\Plugins\Features\Filter\FilterMenuItems;
use Tardis\Contracts\Plugins\Features\Filter\FilterWidgets;
use Tardis\Contracts\Plugins\Features\Provider\CSS;
use Tardis\Contracts\Plugins\Features\Provider\JS;
use Tardis\Contracts\Plugins\Features\Provider\MenuItems;
use Tardis\Contracts\Plugins\Features\Provider\Routes;
use Tardis\Contracts\Plugins\Features\Provider\Settings;
use Tardis\Contracts\Plugins\Features\Provider\Widgets;

test('Widgets interface exists', function () {
    expect(interface_exists(Widgets::class))->toBeTrue();
});

test('MenuItems interface exists', function () {
    expect(interface_exists(MenuItems::class))->toBeTrue();
});

test('Routes interface exists', function () {
    expect(interface_exists(Routes::class))->toBeTrue();
});

test('Settings interface exists', function () {
    expect(interface_exists(Settings::class))->toBeTrue();
});

test('JS interface exists', function () {
    expect(interface_exists(JS::class))->toBeTrue();
});

test('CSS interface exists', function () {
    expect(interface_exists(CSS::class))->toBeTrue();
});

test('FilterWidgets interface exists', function () {
    expect(interface_exists(FilterWidgets::class))->toBeTrue();
});

test('FilterMenuItems interface exists', function () {
    expect(interface_exists(FilterMenuItems::class))->toBeTrue();
});

test('Widgets interface has provideWidgets method', function () {
    $reflection = new ReflectionClass(Widgets::class);
    $methods = array_map(fn ($m) => $m->getName(), $reflection->getMethods());

    expect($methods)->toContain('provideWidgets');
});

test('MenuItems interface has provideMenuItems method', function () {
    $reflection = new ReflectionClass(MenuItems::class);
    $methods = array_map(fn ($m) => $m->getName(), $reflection->getMethods());

    expect($methods)->toContain('provideMenuItems');
});

test('FilterWidgets interface has filterWidgets method', function () {
    $reflection = new ReflectionClass(FilterWidgets::class);
    $methods = array_map(fn ($m) => $m->getName(), $reflection->getMethods());

    expect($methods)->toContain('filterWidgets');
});

test('FilterMenuItems interface has filterMenuItems method', function () {
    $reflection = new ReflectionClass(FilterMenuItems::class);
    $methods = array_map(fn ($m) => $m->getName(), $reflection->getMethods());

    expect($methods)->toContain('filterMenuItems');
});
