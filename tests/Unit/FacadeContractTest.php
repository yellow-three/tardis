<?php

declare(strict_types=1);

use Tardis\Facades\Tardis as TardisFacade;
use Tardis\Manager\MediaManager;
use Tardis\Manager\PluginManager;
use Tardis\Tardis;

/**
 * The facade's @method lines are what IDEs and static analysis believe; they
 * named a class that does not exist and missed three methods.
 */
test('the facade documents exactly the public methods of the Tardis class, with real return types', function () {
    $doc = (string) (new ReflectionClass(TardisFacade::class))->getDocComment();
    preg_match_all('/@method\s+static\s+(\S+)\s+(\w+)\(/', $doc, $matches, PREG_SET_ORDER);

    $documented = [];

    foreach ($matches as [, $type, $method]) {
        $documented[] = $method;

        expect(method_exists(Tardis::class, $method))->toBeTrue("@method {$method} is not on Tardis");

        if (str_starts_with($type, '\\')) {
            expect(class_exists(ltrim($type, '\\')))->toBeTrue("@method {$method} returns missing class {$type}");
        }
    }

    $public = collect((new ReflectionClass(Tardis::class))->getMethods(ReflectionMethod::IS_PUBLIC))
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
    expect(Tardis::version())->toMatch('/^\d+\.\d+\.\d+/');
});

test('media is reachable through the facade and bound once', function () {
    expect(TardisFacade::media())->toBe(app(MediaManager::class));
});
