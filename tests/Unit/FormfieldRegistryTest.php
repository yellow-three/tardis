<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Tardis\Bread\Sources\JsonBreadSource;
use Tardis\Facades\Tardis;
use Tardis\Formfields\Formfield;
use Tardis\Formfields\Types\TextField;
use Tardis\Manager\FormfieldManager;

class RegistryProbeField extends TextField
{
    public function type(): string
    {
        return 'probe';
    }
}

test('the registry exposes every built-in field type', function () {
    expect(array_keys((new FormfieldManager)->types()))->toBe([
        'text', 'number', 'select', 'toggle', 'date', 'datetime', 'time',
        'textarea', 'password', 'file', 'checkbox', 'radio', 'slider',
        'slug', 'tags', 'markdown', 'code_editor', 'color', 'hidden', 'belongs_to_many', 'has_many', 'media_picker',
    ]);
});

test('has() answers for built-in and registered types', function () {
    $manager = new FormfieldManager;

    expect($manager->has('toggle'))->toBeTrue()
        ->and($manager->has('wysiwyg'))->toBeFalse();

    $manager->registerType('wysiwyg', RegistryProbeField::class);

    expect($manager->has('wysiwyg'))->toBeTrue();
});

test('assertRegistered throws for an unknown type and names it', function () {
    expect(fn () => (new FormfieldManager)->assertRegistered('wysiwyg'))
        ->toThrow(InvalidArgumentException::class, 'Unsupported BREAD field type [wysiwyg].');
});

test('normalize maps legacy detector names onto registered types', function () {
    $manager = new FormfieldManager;

    expect($manager->normalize('image'))->toBe('file')
        ->and($manager->normalize('email'))->toBe('text')
        ->and($manager->normalize('simple_array'))->toBe('tags');
});

test('normalize passes every registered type through unchanged', function () {
    $manager = new FormfieldManager;

    foreach (array_keys($manager->types()) as $type) {
        expect($manager->normalize($type))->toBe($type);
    }
});

test('a type a host registers can be used in a BREAD definition', function () {
    // This is the extension point FieldType used to close: registerType() worked
    // but any definition naming the new type was rejected on load and on save.
    $dir = sys_get_temp_dir().'/tardis-registry-'.uniqid();
    $source = new JsonBreadSource($dir);

    expect(fn () => $source->save(['slug' => 'notes', 'fields' => [['name' => 'body', 'type' => 'probe']]]))
        ->toThrow(InvalidArgumentException::class);

    Tardis::formfields()->registerType('probe', RegistryProbeField::class);

    $source->save(['slug' => 'notes', 'fields' => [['name' => 'body', 'type' => 'probe']]]);

    expect($source->find('notes')->fields[0]['type'])->toBe('probe');

    File::deleteDirectory($dir);
});

test('every registered renderer is an instantiable Formfield with a view', function () {
    foreach ((new FormfieldManager)->types() as $type => $class) {
        expect(class_exists($class))->toBeTrue("{$class} registered for [{$type}] does not exist")
            ->and(is_subclass_of($class, Formfield::class))->toBeTrue("{$class} for [{$type}] is not a Formfield")
            ->and((new ReflectionClass($class))->isAbstract())->toBeFalse("{$class} for [{$type}] is abstract");

        $field = new $class('x');

        expect(view()->exists($field->render()))->toBeTrue("[{$type}] renders '{$field->render()}', which does not exist");
    }
});
