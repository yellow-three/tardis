<?php

declare(strict_types=1);

use Tardis\Core\Contracts\Plugins\AuthenticationPlugin;
use Tardis\Core\Contracts\Plugins\AuthorizationPlugin;
use Tardis\Core\Contracts\Plugins\FormfieldPlugin;
use Tardis\Core\Contracts\Plugins\GenericPlugin;
use Tardis\Core\Contracts\Plugins\ThemePlugin;

test('GenericPlugin interface exists', function () {
    expect(interface_exists(GenericPlugin::class))->toBeTrue();
});

test('AuthenticationPlugin interface exists', function () {
    expect(interface_exists(AuthenticationPlugin::class))->toBeTrue();
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
