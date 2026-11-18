<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Tardis\Menu\MenuItem;
use Tardis\Menu\MenuOverlay;
use Tardis\Plugins\PluginManager;
use Tardis\Support\LegacyNames;
use Tardis\TardisManager;

test('every pre-0.2 class name still resolves to its new class', function () {
    foreach (LegacyNames::MAP as $old => $new) {
        expect(class_exists($old))->toBeTrue("{$old} does not resolve")
            ->and(new ReflectionClass($old))->name->toBe($new);
    }
});

test('an old manager name resolves to the same container instance', function () {
    expect(app('Tardis\Manager\PluginManager'))->toBe(app(PluginManager::class))
        ->and(app('Tardis\Tardis'))->toBe(app(TardisManager::class))
        ->and(app('tardis'))->toBe(app(TardisManager::class));
});

test('the old Classes and Manager folders stay empty', function () {
    expect(is_dir(__DIR__.'/../../src/Classes'))->toBeFalse()
        ->and(is_dir(__DIR__.'/../../src/Manager'))->toBeFalse();
});

test('no concrete class shares its short name with a contract', function () {
    $contracts = collect(File::allFiles(__DIR__.'/../../src/Contracts'))->map(fn ($f) => $f->getFilenameWithoutExtension())->all();
    $concrete = collect(File::allFiles(__DIR__.'/../../src'))
        ->reject(fn ($f) => str_contains($f->getRealPath(), '/src/Contracts/'))
        ->map(fn ($f) => $f->getFilenameWithoutExtension())
        ->all();

    expect(array_values(array_intersect($contracts, $concrete)))->toBe([]);
});

test('the pre-0.2 command names still run the renamed commands', function () {
    $commands = Artisan::all();

    foreach (['tardis:make-bread' => 'tardis:make:bread', 'tardis:make-model' => 'tardis:make:model', 'tardis:make-plugin' => 'tardis:make:plugin', 'tardis:bread:migrate' => 'tardis:bread:import-legacy'] as $old => $new) {
        expect($commands[$old] ?? null)->toBe($commands[$new]);
    }
});

test('a menu item or overlay entry saved under an old route name keeps working', function () {
    expect((new MenuItem('Media'))->route('tardis.media')->id())->toBe('tardis.media.index')
        ->and(LegacyNames::menuId('tardis.bread.read:posts'))->toBe('tardis.bread.show:posts')
        ->and(LegacyNames::menuId('custom-link'))->toBe('custom-link');

    $path = sys_get_temp_dir().'/tardis-menus-'.uniqid().'.json';
    file_put_contents($path, json_encode(['items' => ['tardis.media' => ['hidden' => true]], 'custom' => []]));

    try {
        expect((new MenuOverlay($path))->items())->toBe(['tardis.media.index' => ['hidden' => true]]);
    } finally {
        @unlink($path);
    }
});
