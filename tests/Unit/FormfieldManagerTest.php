<?php

declare(strict_types=1);

use Tardis\Formfields\Types\BelongsToManyField;
use Tardis\Formfields\Types\FileField;
use Tardis\Formfields\Types\HasManyField;
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

test('formfield manager resolveType returns correct class for relation and file types', function () {
    $manager = new FormfieldManager;

    expect($manager->resolveType('belongs_to_many'))->toBe(BelongsToManyField::class)
        ->and($manager->resolveType('has_many'))->toBe(HasManyField::class)
        ->and($manager->resolveType('file'))->toBe(FileField::class);
});

test('formfield manager fields maps belongs_to_many relation metadata', function () {
    $manager = new FormfieldManager;

    $fields = $manager->fields([
        ['name' => 'tags', 'type' => 'belongs_to_many', 'relation' => 'tags', 'model' => 'App\Models\Tag', 'label_column' => 'name'],
    ]);

    expect($fields)->toHaveCount(1)
        ->and($fields[0])->toBeInstanceOf(BelongsToManyField::class)
        ->and($fields[0]->relation)->toBe('tags')
        ->and($fields[0]->model)->toBe('App\Models\Tag')
        ->and($fields[0]->labelColumn)->toBe('name');
});

test('formfield manager fields maps has_many relation metadata', function () {
    $manager = new FormfieldManager;

    $fields = $manager->fields([
        ['name' => 'comments', 'type' => 'has_many', 'relation' => 'comments', 'model' => 'App\Models\Comment'],
    ]);

    expect($fields)->toHaveCount(1)
        ->and($fields[0])->toBeInstanceOf(HasManyField::class)
        ->and($fields[0]->relation)->toBe('comments')
        ->and($fields[0]->model)->toBe('App\Models\Comment');
});

test('formfield manager fields maps file upload metadata', function () {
    $manager = new FormfieldManager;

    $fields = $manager->fields([
        ['name' => 'avatar', 'type' => 'file', 'mimes' => ['jpg', 'png'], 'max_size' => 2048, 'disk' => 'public', 'directory' => 'avatars'],
    ]);

    expect($fields)->toHaveCount(1)
        ->and($fields[0])->toBeInstanceOf(FileField::class)
        ->and($fields[0]->mimes)->toBe(['jpg', 'png'])
        ->and($fields[0]->maxSize)->toBe(2048)
        ->and($fields[0]->disk)->toBe('public')
        ->and($fields[0]->directory)->toBe('avatars');
});
