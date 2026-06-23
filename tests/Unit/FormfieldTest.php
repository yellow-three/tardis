<?php

declare(strict_types=1);

use Tardis\Formfields\Types\DateField;
use Tardis\Formfields\Types\FileField;
use Tardis\Formfields\Types\NumberField;
use Tardis\Formfields\Types\PasswordField;
use Tardis\Formfields\Types\SelectField;
use Tardis\Formfields\Types\TextareaField;
use Tardis\Formfields\Types\TextField;
use Tardis\Formfields\Types\ToggleField;

test('text field has correct type and view', function () {
    $field = new TextField('title');

    expect($field->type())->toBe('text')
        ->and($field->render())->toBe('tardis::formfields.text')
        ->and($field->name)->toBe('title');
});

test('number field has correct type and view', function () {
    $field = new NumberField('quantity');

    expect($field->type())->toBe('number')
        ->and($field->render())->toBe('tardis::formfields.number')
        ->and($field->name)->toBe('quantity');
});

test('textarea field has correct type and view', function () {
    $field = new TextareaField('description');

    expect($field->type())->toBe('textarea')
        ->and($field->render())->toBe('tardis::formfields.textarea')
        ->and($field->name)->toBe('description');
});

test('password field has correct type and view', function () {
    $field = new PasswordField('secret');

    expect($field->type())->toBe('password')
        ->and($field->render())->toBe('tardis::formfields.password')
        ->and($field->name)->toBe('secret');
});

test('toggle field has correct type and view', function () {
    $field = new ToggleField('active');

    expect($field->type())->toBe('toggle')
        ->and($field->render())->toBe('tardis::formfields.toggle')
        ->and($field->name)->toBe('active');
});

test('date field has correct type and view', function () {
    $field = new DateField('birthday');

    expect($field->type())->toBe('date')
        ->and($field->render())->toBe('tardis::formfields.date')
        ->and($field->name)->toBe('birthday');
});

test('select field has correct type and view', function () {
    $field = new SelectField('status');

    expect($field->type())->toBe('select')
        ->and($field->render())->toBe('tardis::formfields.select')
        ->and($field->name)->toBe('status');
});

test('file field has correct type and view', function () {
    $field = new FileField('avatar');

    expect($field->type())->toBe('file')
        ->and($field->render())->toBe('tardis::formfields.file')
        ->and($field->name)->toBe('avatar');
});

test('formfield label defaults to name', function () {
    $field = new TextField('title');

    expect($field->label)->toBe('title');
});

test('formfield label can be set', function () {
    $field = new TextField('title', 'Post Title');

    expect($field->label)->toBe('Post Title');
});

test('formfield fluent default method', function () {
    $field = (new TextField('status'))->default('active');

    expect($field->default)->toBe('active');
});

test('formfield fluent rules method with array', function () {
    $field = (new TextField('email'))->rules(['required', 'email']);

    expect($field->rules)->toBe(['required', 'email']);
});

test('formfield fluent rules method with string', function () {
    $field = (new TextField('name'))->rules('required');

    expect($field->rules)->toBe(['required']);
});

test('formfield fluent attributes method', function () {
    $field = (new TextField('name'))->attributes(['maxlength' => 255]);

    expect($field->attributes)->toBe(['maxlength' => 255]);
});

test('formfield fluent disabled method', function () {
    $field = (new TextField('name'))->disabled();

    expect($field->disabled)->toBeTrue();
});

test('formfield fluent readonly method', function () {
    $field = (new TextField('name'))->readonly();

    expect($field->readonly)->toBeTrue();
});

test('formfield fluent help method', function () {
    $field = (new TextField('name'))->help('Enter your name');

    expect($field->helpText)->toBe('Enter your name');
});

test('formfield fluent placeholder method', function () {
    $field = (new TextField('name'))->placeholder('John Doe');

    expect($field->placeholder)->toBe('John Doe');
});

test('formfield fluent wrapperClass method', function () {
    $field = (new TextField('name'))->wrapperClass('col-md-6');

    expect($field->wrapperClass)->toBe('col-md-6');
});

test('formfield fluent width method', function () {
    $field = (new TextField('name'))->width(6);

    expect($field->width)->toBe(6);
});

test('formfield has default width of 12', function () {
    $field = new TextField('name');

    expect($field->width)->toBe(12);
});

test('select field options getter and setter', function () {
    $field = new SelectField('status');

    $field->options(['active' => 'Active', 'inactive' => 'Inactive']);

    expect($field->options())->toBe(['active' => 'Active', 'inactive' => 'Inactive']);
});

test('file field mimes method', function () {
    $field = (new FileField('document'))->mimes(['pdf', 'doc']);

    expect($field->mimes)->toBe(['pdf', 'doc']);
});

test('file field maxSize method', function () {
    $field = (new FileField('document'))->maxSize(5120);

    expect($field->maxSize)->toBe(5120);
});
