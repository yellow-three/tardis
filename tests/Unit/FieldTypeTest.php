<?php

declare(strict_types=1);

use Tardis\Bread\FieldType;

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
