<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Tardis\Bread\BreadDefinition;
use Tardis\Bread\BreadManager;
use Tardis\Bread\Sources\JsonBreadSource;
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

test('BreadManager delegates to the json source', function () {
    $path = sys_get_temp_dir().'/tardis-manager-'.uniqid();

    try {
        $manager = new BreadManager(new JsonBreadSource($path));

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

test('a settings import that clashes writes nothing', function () {
    $manager = new SettingsManager;
    $manager->create(['key' => 'taken', 'type' => 'text', 'name' => 'Taken', 'value' => 'a']);
    $before = $manager->all()->count();

    $json = json_encode([
        ['key' => 'fresh', 'type' => 'text', 'name' => 'Fresh', 'value' => 'b'],
        ['key' => 'taken', 'type' => 'text', 'name' => 'Taken', 'value' => 'c'],
    ]);

    expect(fn () => $manager->import($json))->toThrow(RuntimeException::class, 'Nothing was imported')
        ->and($manager->all()->count())->toBe($before)
        ->and($manager->findByKey('fresh'))->toBeNull();
});

test('a settings import with a duplicate inside the file writes nothing', function () {
    $manager = new SettingsManager;

    $json = json_encode([
        ['key' => 'dup', 'type' => 'text', 'name' => 'One', 'value' => 'a'],
        ['key' => 'dup', 'type' => 'text', 'name' => 'Two', 'value' => 'b'],
    ]);

    expect(fn () => $manager->import($json))->toThrow(RuntimeException::class)
        ->and($manager->findByKey('dup'))->toBeNull();
});

test('a clean settings import returns how many settings it added', function () {
    $manager = new SettingsManager;

    $json = json_encode([
        ['key' => 'a', 'type' => 'text', 'name' => 'A', 'value' => '1'],
        ['key' => 'b', 'type' => 'text', 'name' => 'B', 'value' => '2'],
        ['no-key' => true],
    ]);

    expect($manager->import($json))->toBe(2);
});
