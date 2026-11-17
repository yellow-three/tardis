<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Livewire\Livewire;
use Tardis\Bread\BreadDefinition;
use Tardis\Bread\Sources\JsonBreadSource;
use Tardis\Models\Role;

/**
 * Instantiating this is the side effect a hostile "model" would be chosen for.
 */
class BreadModelClassProbe
{
    public static int $constructed = 0;

    public function __construct()
    {
        static::$constructed++;
    }
}

beforeEach(function () {
    BreadModelClassProbe::$constructed = 0;

    $this->breadStoragePath = sys_get_temp_dir().'/tardis-bread-model-class-'.uniqid();
    app()->instance(JsonBreadSource::class, new JsonBreadSource($this->breadStoragePath));
});

afterEach(function () {
    File::deleteDirectory($this->breadStoragePath);
});

function writeRawBread(string $path, string $slug, string $model): void
{
    File::ensureDirectoryExists($path);
    File::put($path.'/'.$slug.'.json', json_encode([
        'slug' => $slug,
        'model' => $model,
        'name' => 'Thing',
        'name_plural' => 'Things',
        'fields' => [['name' => 'title', 'type' => 'text']],
    ]));
}

test('only concrete Eloquent models count as a BREAD model', function () {
    expect(BreadDefinition::isModelClass(BreadModelClassProbe::class))->toBeFalse()
        ->and(BreadDefinition::isModelClass('Illuminate\\Support\\Collection'))->toBeFalse()
        ->and(BreadDefinition::isModelClass('Illuminate\\Database\\Eloquent\\Model'))->toBeFalse()
        ->and(BreadDefinition::isModelClass('No\\Such\\Class'))->toBeFalse()
        ->and(BreadDefinition::isModelClass(Role::class))->toBeTrue();
});

test('a definition whose model is not an Eloquent model cannot be saved', function () {
    $source = new JsonBreadSource($this->breadStoragePath);

    expect(fn () => $source->save([
        'slug' => 'things',
        'model' => BreadModelClassProbe::class,
        'name' => 'Thing',
        'fields' => [],
    ]))->toThrow(InvalidArgumentException::class);
});

test('a BREAD query refuses a model that is not an Eloquent model', function () {
    $definition = BreadDefinition::fromArray(['slug' => 'things', 'model' => BreadModelClassProbe::class, 'fields' => []]);

    expect(fn () => $definition->query())->toThrow(InvalidArgumentException::class);
});

test('the BREAD pages refuse a definition whose model is not an Eloquent model', function (string $component, array $params, int $status) {
    writeRawBread($this->breadStoragePath, 'things', BreadModelClassProbe::class);

    Livewire::test($component, ['slug' => 'things'] + $params)->assertStatus($status);

    expect(BreadModelClassProbe::$constructed)->toBe(0);
})->with([
    // The listing renders its "no model" state rather than failing.
    'browse' => ['tardis::pages.bread.index', [], 200],
    'add' => ['tardis::pages.bread.create', [], 404],
    'read' => ['tardis::pages.bread.read', ['id' => 1], 404],
    'edit' => ['tardis::pages.bread.edit', ['id' => 1], 404],
]);

test('the builder will not instantiate a class that is not an Eloquent model', function () {
    Livewire::test('tardis::pages.bread-builder')
        ->set('model', BreadModelClassProbe::class)
        ->call('detectFields');

    expect(BreadModelClassProbe::$constructed)->toBe(0);
});
