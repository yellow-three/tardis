<?php

declare(strict_types=1);

use Tardis\Formfields\Types\NumberField;
use Tardis\Formfields\Types\TextField;
use Tardis\Manager\FormfieldManager;

test('formfield manager can be instantiated', function () {
    $manager = new FormfieldManager;

    expect($manager)->toBeInstanceOf(FormfieldManager::class);
});

test('formfield manager resolveType returns correct class for text', function () {
    $manager = new FormfieldManager;

    expect($manager->resolveType('text'))->toBe(TextField::class);
});

test('formfield manager resolveType returns correct class for number', function () {
    $manager = new FormfieldManager;

    expect($manager->resolveType('number'))->toBe(NumberField::class);
});

test('formfield manager resolveType returns null for unknown type', function () {
    $manager = new FormfieldManager;

    expect($manager->resolveType('unknown'))->toBeNull();
});

test('formfield manager make creates field instance', function () {
    $manager = new FormfieldManager;

    $field = $manager->make('text', 'title', 'Title');

    expect($field)->toBeInstanceOf(TextField::class)
        ->and($field->name)->toBe('title')
        ->and($field->label)->toBe('Title');
});

test('formfield manager make with null label uses name', function () {
    $manager = new FormfieldManager;

    $field = $manager->make('text', 'title');

    expect($field->label)->toBe('title');
});

test('formfield manager registerType adds custom type', function () {
    $manager = new FormfieldManager;

    $manager->registerType('custom', TextField::class);

    expect($manager->resolveType('custom'))->toBe(TextField::class);
});

test('formfield manager fields creates multiple fields', function () {
    $manager = new FormfieldManager;

    $fields = $manager->fields([
        ['name' => 'title', 'type' => 'text'],
        ['name' => 'quantity', 'type' => 'number'],
    ]);

    expect($fields)->toHaveCount(2)
        ->and($fields[0])->toBeInstanceOf(TextField::class)
        ->and($fields[1])->toBeInstanceOf(NumberField::class);
});
