<?php

declare(strict_types=1);

use Tardis\Dashboard\WidgetManager;
use Tardis\Facades\Tardis;
use Tardis\Formfields\FormfieldManager;
use Tardis\Formfields\Types\TextField;
use Tardis\Menu\MenuManager;
use Tardis\Plugins\PluginManager;
use Tardis\Settings\SettingsManager;

test('Tardis facade is accessible', function () {
    $version = Tardis::version();
    expect($version)->toBe('0.1.0');
});

test('Tardis version returns string', function () {
    $version = Tardis::version();
    expect($version)->toBeString();
});

test('the facade and the container hand out the very same manager instances', function (string $accessor, string $class) {
    // Pages resolve managers with app(Class::class) while host code uses the
    // facade. Two instances means anything registered through one (a menu item,
    // a custom field type, a widget) is invisible to the other.
    expect(Tardis::{$accessor}())->toBe(app($class))
        ->and(app($class))->toBe(app($class));
})->with([
    'menu' => ['menu', MenuManager::class],
    'widgets' => ['widgets', WidgetManager::class],
    'settings' => ['settings', SettingsManager::class],
    'formfields' => ['formfields', FormfieldManager::class],
    'plugins' => ['plugins', PluginManager::class],
]);

test('a field type registered through the facade is the one the pages resolve', function () {
    Tardis::formfields()->registerType('probe', TextField::class);

    expect(app(FormfieldManager::class)->make('probe', 'x'))->toBeInstanceOf(TextField::class);
});
