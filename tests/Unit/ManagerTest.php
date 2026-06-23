<?php

declare(strict_types=1);

use Tardis\Bread\BreadManager;
use Tardis\Manager\FormfieldManager;
use Tardis\Manager\MenuManager;
use Tardis\Manager\PluginManager;
use Tardis\Manager\SettingsManager;
use Tardis\Manager\WidgetManager;

test('PluginManager can be instantiated', function () {
    $manager = new PluginManager;
    expect($manager)->toBeInstanceOf(PluginManager::class);
});

test('MenuManager can be instantiated', function () {
    $manager = new MenuManager;
    expect($manager)->toBeInstanceOf(MenuManager::class);
});

test('WidgetManager can be instantiated', function () {
    $manager = new WidgetManager;
    expect($manager)->toBeInstanceOf(WidgetManager::class);
});

test('SettingsManager can be instantiated', function () {
    $manager = new SettingsManager;
    expect($manager)->toBeInstanceOf(SettingsManager::class);
});

test('FormfieldManager can be instantiated', function () {
    $manager = new FormfieldManager;
    expect($manager)->toBeInstanceOf(FormfieldManager::class);
});

test('BreadManager class exists', function () {
    expect(class_exists(BreadManager::class))->toBeTrue();
});
