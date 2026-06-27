<?php

declare(strict_types=1);

use Tardis\Contracts\Plugins\AuthorizationPlugin;
use Tardis\Contracts\Plugins\FormfieldPlugin;
use Tardis\Contracts\Plugins\GenericPlugin;
use Tardis\Contracts\Plugins\ThemePlugin;

test('GenericPlugin interface exists', function () {
    expect(interface_exists(GenericPlugin::class))->toBeTrue();
});

test('AuthorizationPlugin interface exists', function () {
    expect(interface_exists(AuthorizationPlugin::class))->toBeTrue();
});

test('ThemePlugin interface exists', function () {
    expect(interface_exists(ThemePlugin::class))->toBeTrue();
});

test('FormfieldPlugin interface exists', function () {
    expect(interface_exists(FormfieldPlugin::class))->toBeTrue();
});
