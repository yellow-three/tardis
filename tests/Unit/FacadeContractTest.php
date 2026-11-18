<?php

declare(strict_types=1);

use Tardis\Facades\Tardis as TardisFacade;
use Tardis\Media\MediaManager;
use Tardis\Plugins\PluginManager;
use Tardis\TardisManager;

/**
 * The facade's @method lines are what IDEs and static analysis believe; they
 * named a class that does not exist and missed three methods.
 */
test('the facade documents exactly the public methods of TardisManager, with real return types', function () {
    $doc = (string) (new ReflectionClass(TardisFacade::class))->getDocComment();
    preg_match_all('/@method\s+static\s+(\S+)\s+(\w+)\(/', $doc, $matches, PREG_SET_ORDER);

    $documented = [];

    foreach ($matches as [, $type, $method]) {
        $documented[] = $method;

        expect(method_exists(TardisManager::class, $method))->toBeTrue("@method {$method} is not on TardisManager");

        if (str_starts_with($type, '\\')) {
            expect(class_exists(ltrim($type, '\\')))->toBeTrue("@method {$method} returns missing class {$type}");
        }
    }

    $public = collect((new ReflectionClass(TardisManager::class))->getMethods(ReflectionMethod::IS_PUBLIC))
        ->reject(fn (ReflectionMethod $m) => $m->isConstructor() || str_starts_with($m->getName(), '__'))
        ->map->getName()
        ->sort()->values()->all();

    sort($documented);

    expect($documented)->toBe($public);
});

test('the facade hands out what the container holds now, not a copy it kept', function () {
    $first = TardisFacade::plugins();
    app()->instance(PluginManager::class, $replacement = new PluginManager(sys_get_temp_dir().'/tardis-facade-'.uniqid().'.json'));

    expect(TardisFacade::plugins())->toBe($replacement)->not->toBe($first);
});

test('the version is a semantic version string', function () {
    expect(TardisManager::version())->toMatch('/^\d+\.\d+\.\d+/');
});

test('media is reachable through the facade and bound once', function () {
    expect(TardisFacade::media())->toBe(app(MediaManager::class));
});
