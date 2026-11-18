<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
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
