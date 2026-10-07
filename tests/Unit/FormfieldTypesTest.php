<?php

declare(strict_types=1);

use Tardis\Formfields\Types\CoordinatesField;
use Tardis\Formfields\Types\RepeaterField;
use Tardis\Formfields\Types\RichTextField;
use Tardis\Formfields\Types\SelectMultipleField;
use Tardis\Formfields\Types\SimpleArrayField;

test('coordinates field has correct type and view', function () {
    $field = new CoordinatesField('location');

    expect($field->type())->toBe('coordinates')
        ->and($field->render())->toBe('tardis::formfields.coordinates')
        ->and($field->name)->toBe('location');
});

test('coordinates field parses a stored pair into lat/lng inputs', function () {
    $field = new CoordinatesField('location');

    expect($field->edit('41.0082,28.9784'))->toBe(['lat' => '41.0082', 'lng' => '28.9784'])
        ->and($field->edit(null))->toBe(['lat' => '', 'lng' => ''])
        ->and($field->edit(''))->toBe(['lat' => '', 'lng' => '']);
});

test('coordinates field add is an empty pair', function () {
    $field = new CoordinatesField('location');

    expect($field->add(null))->toBe(['lat' => '', 'lng' => '']);
});

test('coordinates field joins the pair back into one column', function () {
    $field = new CoordinatesField('location');

    expect($field->store(['lat' => '41.0082', 'lng' => '28.9784']))->toBe('41.0082,28.9784')
        ->and($field->store(['lat' => '41.0082', 'lng' => '']))->toBe('41.0082,')
        ->and($field->store(['lat' => '', 'lng' => '']))->toBeNull()
        ->and($field->store('41.0082,28.9784'))->toBe('41.0082,28.9784');
});

test('coordinates field browse and read stringify both shapes', function () {
    $field = new CoordinatesField('location');

    expect($field->browse(['lat' => '41', 'lng' => '28']))->toBe('41,28')
        ->and($field->read('41,28'))->toBe('41,28')
        ->and($field->read(null))->toBeNull();
});

test('rich text field has correct type and view', function () {
    $field = new RichTextField('body');

    expect($field->type())->toBe('rich_text')
        ->and($field->render())->toBe('tardis::formfields.rich-text')
        ->and($field->name)->toBe('body');
});

test('simple array field has correct type and view', function () {
    $field = new SimpleArrayField('tags');

    expect($field->type())->toBe('simple_array')
        ->and($field->render())->toBe('tardis::formfields.simple-array')
        ->and($field->name)->toBe('tags');
});

test('simple array field normalises stored values to a string list', function () {
    $field = new SimpleArrayField('tags');

    expect($field->edit(['a', 'b']))->toBe(['a', 'b'])
        ->and($field->edit(null))->toBe([])
        ->and($field->add(null))->toBe([]);
});

test('simple array field drops blank entries and stores null when empty', function () {
    $field = new SimpleArrayField('tags');

    expect($field->store([' a ', '', 'b', '  ']))->toBe(['a', 'b'])
        ->and($field->store([]))->toBeNull()
        ->and($field->store(null))->toBeNull();
});

test('simple array field exposes min and max from its definition', function () {
    $field = new SimpleArrayField('tags');
    $field->configure(['min' => 1, 'max' => 5]);

    expect($field->min)->toBe(1)
        ->and($field->max)->toBe(5)
        ->and($field->viewData()['min'])->toBe(1)
        ->and($field->viewData()['max'])->toBe(5);
});

test('repeater field has correct type and view', function () {
    $field = new RepeaterField('rows');

    expect($field->type())->toBe('repeater')
        ->and($field->render())->toBe('tardis::formfields.repeater')
        ->and($field->name)->toBe('rows');
});

test('repeater field normalises sub-field definitions', function () {
    $field = new RepeaterField('rows');
    $field->configure(['fields' => [
        'title',
        ['name' => 'amount', 'type' => 'number', 'label' => 'Amount'],
        ['name' => 'note', 'type' => 'not-a-type'],
        ['type' => 'text'],
    ]]);

    $subfields = $field->subfields();

    expect($subfields)->toHaveCount(3)
        ->and($subfields[0])->toBe(['name' => 'title', 'type' => 'text', 'label' => 'Title', 'options' => []])
        ->and($subfields[1]['type'])->toBe('number')
        ->and($subfields[1]['label'])->toBe('Amount')
        ->and($subfields[2]['type'])->toBe('text');
});

test('repeater field edit fills missing sub-field keys per row', function () {
    $field = new RepeaterField('rows');
    $field->configure(['fields' => [
        ['name' => 'title'],
        ['name' => 'amount'],
    ]]);

    expect($field->edit([['title' => 'One'], ['amount' => '5']]))->toBe([
        ['title' => 'One', 'amount' => ''],
        ['amount' => '5', 'title' => ''],
    ])->and($field->edit(null))->toBe([]);
});

test('repeater field store drops wholly blank rows and nulls an empty list', function () {
    $field = new RepeaterField('rows');
    $field->configure(['fields' => [
        ['name' => 'title'],
        ['name' => 'amount'],
    ]]);

    expect($field->store([
        ['title' => ' One ', 'amount' => '5'],
        ['title' => '', 'amount' => ''],
        ['title' => '  ', 'amount' => ''],
    ]))->toBe([
        ['title' => 'One', 'amount' => '5'],
    ])->and($field->store([['title' => '', 'amount' => '']]))->toBeNull()
        ->and($field->store(null))->toBeNull();
});

test('repeater field exposes a blank row for the add control', function () {
    $field = new RepeaterField('rows');
    $field->configure(['fields' => [
        ['name' => 'title'],
        ['name' => 'amount'],
    ]]);

    expect($field->blankRow())->toBe(['title' => '', 'amount' => ''])
        ->and($field->viewData()['blankRow'])->toBe(['title' => '', 'amount' => '']);
});

test('select multiple field has correct type and view', function () {
    $field = new SelectMultipleField('roles');

    expect($field->type())->toBe('select_multiple')
        ->and($field->render())->toBe('tardis::formfields.select-multiple')
        ->and($field->name)->toBe('roles');
});

test('select multiple field inherits options from select', function () {
    $field = (new SelectMultipleField('roles'))->options(['a' => 'A', 'b' => 'B']);

    expect($field->options())->toBe(['a' => 'A', 'b' => 'B'])
        ->and($field->viewData()['options'])->toBe(['a' => 'A', 'b' => 'B']);
});

test('select multiple field normalises stored values to a list', function () {
    $field = new SelectMultipleField('roles');

    expect($field->edit(['a', 'b']))->toBe(['a', 'b'])
        ->and($field->edit('a'))->toBe(['a'])
        ->and($field->edit(null))->toBe([])
        ->and($field->add(null))->toBe([]);
});

test('select multiple field drops blanks and stores null when empty', function () {
    $field = new SelectMultipleField('roles');

    expect($field->store(['a', '', 'b']))->toBe(['a', 'b'])
        ->and($field->store([]))->toBeNull()
        ->and($field->store(null))->toBeNull();
});
