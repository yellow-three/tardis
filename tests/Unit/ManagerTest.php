<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Tardis\Bread\BreadDefinition;
use Tardis\Bread\BreadManager;
use Tardis\Bread\Sources\ConfigBreadSource;
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

test('BreadManager delegates to the config source', function () {
    $path = sys_get_temp_dir().'/tardis-manager-'.uniqid();

    try {
        $manager = new BreadManager(new ConfigBreadSource($path));

        $manager->save([
            'slug' => 'posts',
            'model' => 'App\Models\Post',
            'name' => 'Post',
            'name_plural' => 'Posts',
            'fields' => [],
        ]);

        expect($manager->find('posts'))->toBeInstanceOf(BreadDefinition::class)
            ->and($manager->all())->toHaveCount(1);
    } finally {
        File::deleteDirectory($path);
    }
});
