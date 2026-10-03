<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tardis\Formfields\Types\BelongsToManyField;
use Tardis\Formfields\Types\DateField;
use Tardis\Formfields\Types\FileField;
use Tardis\Formfields\Types\HasManyField;
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

test('file field defaults to public disk and uploads directory', function () {
    $field = new FileField('avatar');

    expect($field->disk)->toBe('public')
        ->and($field->directory)->toBe('uploads');
});

test('file field fluent disk and directory methods', function () {
    $field = (new FileField('avatar'))->disk('private')->directory('avatars');

    expect($field->disk)->toBe('private')
        ->and($field->directory)->toBe('avatars');
});

test('file field transform keeps non-upload values untouched', function () {
    $field = new FileField('avatar');

    expect($field->transform('uploads/existing.jpg'))->toBe('uploads/existing.jpg')
        ->and($field->transform(null))->toBeNull();
});

test('file field transform stores uploaded files on the configured disk', function () {
    Storage::fake('public');

    $field = (new FileField('avatar'))->disk('public')->directory('avatars');

    $path = $field->transform(UploadedFile::fake()->image('avatar.jpg'));

    expect($path)->toBeString()
        ->and($path)->toStartWith('avatars/')
        ->and(Storage::disk('public')->exists($path))->toBeTrue();
});

test('toggle field transform casts values to boolean', function () {
    $field = new ToggleField('active');

    expect($field->transform('1'))->toBeTrue()
        ->and($field->transform(1))->toBeTrue()
        ->and($field->transform(true))->toBeTrue()
        ->and($field->transform('0'))->toBeFalse()
        ->and($field->transform(''))->toBeFalse()
        ->and($field->transform(null))->toBeFalse();
});

test('number field transform casts values to float and blank to null', function () {
    $field = new NumberField('quantity');

    expect($field->transform('42'))->toBe(42.0)
        ->and($field->transform(7))->toBe(7.0)
        ->and($field->transform(''))->toBeNull()
        ->and($field->transform(null))->toBeNull();
});

test('password field transform hashes non-blank values and returns null for blank', function () {
    $field = new PasswordField('secret');

    $hash = $field->transform('plain-text');

    expect($hash)->not->toBe('plain-text')
        ->and(Hash::check('plain-text', $hash))->toBeTrue()
        ->and($field->transform(''))->toBeNull()
        ->and($field->transform(null))->toBeNull();
});

test('password field skips blank values', function () {
    $field = new PasswordField('secret');

    expect($field->skipWhenBlank())->toBeTrue();
});

test('base formfield transform is pass-through', function () {
    $field = new TextField('title');

    expect($field->transform('value'))->toBe('value')
        ->and($field->transform(null))->toBeNull();
});

test('base formfield does not skip blank values by default', function () {
    $field = new TextField('title');

    expect($field->skipWhenBlank())->toBeFalse();
});

test('base formfield is not a relation field by default', function () {
    $field = new TextField('title');

    expect($field->isRelation())->toBeFalse();
});

test('belongs to many field is a relation field', function () {
    $field = new BelongsToManyField('tags');

    expect($field->isRelation())->toBeTrue();
});

test('belongs to many field fluent relation model and label column methods', function () {
    $field = (new BelongsToManyField('tags'))
        ->relation('tags')
        ->model('App\Models\Tag')
        ->labelColumn('name');

    expect($field->relation)->toBe('tags')
        ->and($field->model)->toBe('App\Models\Tag')
        ->and($field->labelColumn)->toBe('name');
});

test('has many field is a relation field', function () {
    $field = new HasManyField('comments');

    expect($field->isRelation())->toBeTrue();
});

test('has many field fluent relation and model methods', function () {
    $field = (new HasManyField('comments'))
        ->relation('comments')
        ->model('App\Models\Comment');

    expect($field->relation)->toBe('comments')
        ->and($field->model)->toBe('App\Models\Comment');
});

test('field keeps a locale map label as stored', function () {
    $field = (new TextField('title', ['en' => 'Title', 'tr' => 'Başlık']))->locales(['en', 'tr']);

    expect($field->label)->toBe(['en' => 'Title', 'tr' => 'Başlık']);
});

test('field resolves a locale map label for the active locale', function () {
    $field = (new TextField('title', ['en' => 'Title', 'tr' => 'Başlık']))->locales(['en', 'tr']);

    expect($field->resolvedLabel('tr'))->toBe('Başlık')
        ->and($field->resolvedLabel('en'))->toBe('Title');
});

test('field label falls back to a filled locale when the active one is empty', function () {
    $field = (new TextField('title', ['en' => 'Title', 'tr' => '']))->locales(['en', 'tr']);

    expect($field->resolvedLabel('tr'))->toBe('Title');
});

test('field leaves a plain string label untouched', function () {
    expect((new TextField('title', 'Title'))->resolvedLabel('tr'))->toBe('Title');
});

test('field with no label resolves to its own name', function () {
    expect((new TextField('title'))->resolvedLabel('tr'))->toBe('title');
});

test('field view data carries the resolved label rather than the raw map', function () {
    app()->setLocale('tr');

    $field = (new TextField('title', ['en' => 'Title', 'tr' => 'Başlık']))->locales(['en', 'tr']);

    expect($field->viewData()['label'])->toBe('Başlık');
});
