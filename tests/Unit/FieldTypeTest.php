<?php

declare(strict_types=1);

use Tardis\Bread\FieldType;
use Tardis\Formfields\Formfield;
use Tardis\Manager\FormfieldManager;

test('FieldType exposes every registered field type', function () {
    $values = array_map(fn (FieldType $type) => $type->value, FieldType::cases());

    expect($values)->toHaveCount(19)
        ->and($values)->toBe([
            'text', 'number', 'select', 'toggle', 'date', 'datetime', 'time',
            'textarea', 'password', 'file', 'checkbox', 'radio', 'slider',
            'slug', 'tags', 'markdown', 'code_editor', 'belongs_to_many', 'has_many',
        ]);
});

test('fromValue maps a known type', function () {
    expect(FieldType::fromValue('toggle'))->toBe(FieldType::Toggle);
});

test('fromValue throws for an unknown type', function () {
    expect(fn () => FieldType::fromValue('wysiwyg'))
        ->toThrow(InvalidArgumentException::class, 'Unsupported BREAD field type [wysiwyg].');
});

test('normalize maps legacy detector types onto supported types', function () {
    expect(FieldType::normalize('image'))->toBe('file')
        ->and(FieldType::normalize('email'))->toBe('text')
        ->and(FieldType::normalize('simple_array'))->toBe('tags');
});

test('normalize passes supported types through unchanged', function () {
    foreach (FieldType::cases() as $type) {
        expect(FieldType::normalize($type->value))->toBe($type->value);
    }
});

/**
 * FieldType's docblock calls the enum <-> $registeredTypes pairing a MUST, but
 * nothing enforced it. Drift is silent in one direction and fatal in the other:
 * a new enum case makes both BREAD sources validate the type, then
 * FormfieldManager::make() instantiates `new null` and the page dies at runtime.
 * These tests turn that into a suite failure at the point of the mistake.
 */
test('every FieldType case has a renderer registered for it', function () {
    $manager = new FormfieldManager;

    $missing = array_values(array_filter(
        array_map(fn (FieldType $type) => $type->value, FieldType::cases()),
        fn (string $value) => $manager->resolveType($value) === null,
    ));

    expect($missing)->toBe([], sprintf(
        'FieldType cases with no registered renderer: %s. Add the field class to '
        .'FormfieldManager::$registeredTypes or drop the case.',
        implode(', ', $missing),
    ));
});

test('every registered renderer maps back to a known FieldType case', function () {
    $known = array_map(fn (FieldType $type) => $type->value, FieldType::cases());

    $registered = (new ReflectionProperty(FormfieldManager::class, 'registeredTypes'))
        ->getValue(new FormfieldManager);

    $orphans = array_values(array_diff(array_keys($registered), $known));

    expect($orphans)->toBe([], sprintf(
        'Renderers registered for types the enum rejects: %s. Validation would '
        .'refuse these before they ever reach a renderer.',
        implode(', ', $orphans),
    ));
});

test('every registered renderer is an instantiable Formfield', function () {
    $registered = (new ReflectionProperty(FormfieldManager::class, 'registeredTypes'))
        ->getValue(new FormfieldManager);

    foreach ($registered as $type => $class) {
        expect(class_exists($class))
            ->toBeTrue("{$class} registered for [{$type}] does not exist")
            ->and(is_subclass_of($class, Formfield::class))
            ->toBeTrue("{$class} registered for [{$type}] is not a Formfield")
            ->and((new ReflectionClass($class))->isAbstract())
            ->toBeFalse("{$class} registered for [{$type}] is abstract and cannot be instantiated");
    }
});
