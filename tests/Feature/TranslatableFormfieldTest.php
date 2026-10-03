<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tardis\Bread\Sources\JsonBreadSource;
use Tardis\Classes\Translation;

class BreadTranslatablePlainTestModel extends Model
{
    protected $table = 'bread_translatable_plain';

    protected $guarded = [];

    public $timestamps = true;

    protected $casts = [
        'body' => 'array',
    ];
}

beforeEach(function () {
    Schema::create('bread_translatable_plain', function (Blueprint $table) {
        $table->id();
        $table->string('title');
        $table->json('body')->nullable();
        $table->timestamps();
    });

    app()->instance(JsonBreadSource::class, new JsonBreadSource(__DIR__.'/../Fixtures/bread'));
});

test('normalize keeps a plain string as the value of the first locale', function () {
    expect(Translation::normalize('Hello', ['en', 'tr']))->toBe(['en' => 'Hello', 'tr' => '']);
});

test('normalize keeps a numeric string as a value rather than decoding it', function () {
    expect(Translation::normalize('123', ['en', 'tr']))->toBe(['en' => '123', 'tr' => '']);
});

test('normalize still reads a JSON encoded locale map', function () {
    expect(Translation::normalize('{"en":"Hi","tr":"Selam"}', ['en', 'tr']))
        ->toBe(['en' => 'Hi', 'tr' => 'Selam']);
});

test('normalize still returns an empty map for null', function () {
    expect(Translation::normalize(null, ['en', 'tr']))->toBe(['en' => '', 'tr' => '']);
});

test('the edit page prefills a plain string value instead of showing it empty', function () {
    $post = BreadTranslatablePlainTestModel::create([
        'title' => 'Legacy row',
        'body' => 'Hello',
    ]);

    Livewire::test('tardis::pages.bread.edit', ['slug' => 'bread-page-translatable-plain', 'id' => $post->id])
        ->assertSet('form.body', ['en' => 'Hello', 'tr' => '']);
});

test('the edit page does not wipe a plain string value when the user saves', function () {
    $post = BreadTranslatablePlainTestModel::create([
        'title' => 'Legacy row',
        'body' => 'Hello',
    ]);

    Livewire::test('tardis::pages.bread.edit', ['slug' => 'bread-page-translatable-plain', 'id' => $post->id])
        ->set('form.title', 'Legacy row renamed')
        ->call('save')
        ->assertHasNoErrors();

    expect($post->fresh()->body)->toBe(['en' => 'Hello', 'tr' => '']);
});

test('value falls back to a plain string so read and browse pages render it', function () {
    expect(Translation::value('Hello', ['en', 'tr'], 'tr'))->toBe('Hello');
});

test('the create page stores a plain string body as a locale map instead of null', function () {
    Livewire::test('tardis::pages.bread.create', ['slug' => 'bread-page-translatable-plain'])
        ->set('form.title', 'Plain body post')
        ->set('form.body', 'Hello')
        ->call('save')
        ->assertHasNoErrors();

    $post = BreadTranslatablePlainTestModel::where('title', 'Plain body post')->first();

    expect($post)->not->toBeNull()
        ->and($post->body)->toBe(['en' => 'Hello', 'tr' => '']);
});

test('label resolves a locale map for the active locale', function () {
    expect(Translation::label(['en' => 'Title', 'tr' => 'Başlık'], ['en', 'tr'], 'tr'))->toBe('Başlık');
});

test('label leaves a plain string untouched instead of reading it as a map', function () {
    expect(Translation::label('Title', ['en', 'tr'], 'tr'))->toBe('Title');
});

test('label falls back to a filled locale when the active one is empty', function () {
    expect(Translation::label(['en' => 'Title', 'tr' => ''], ['en', 'tr'], 'tr'))->toBe('Title');
});

test('label resolves a JSON encoded locale map', function () {
    expect(Translation::label('{"en":"Title","tr":"Başlık"}', ['en', 'tr'], 'tr'))->toBe('Başlık');
});

test('sourceLocale is null when the locale has a translation of its own', function () {
    expect(Translation::sourceLocale(['en' => 'Hello', 'tr' => 'Merhaba'], ['en', 'tr'], 'tr'))->toBeNull();
});

test('sourceLocale names the locale a value was borrowed from', function () {
    expect(Translation::sourceLocale(['en' => 'Hello', 'tr' => ''], ['en', 'tr'], 'tr'))->toBe('en');
});

test('sourceLocale is null when there is nothing to fall back to', function () {
    expect(Translation::sourceLocale(['en' => '', 'tr' => ''], ['en', 'tr'], 'tr'))->toBeNull();
});

test('sourceLocale attributes a plain string to the first locale', function () {
    expect(Translation::sourceLocale('Hello', ['en', 'tr'], 'tr'))->toBe('en');
});

test('value and sourceLocale agree on which locale answered', function () {
    $value = ['en' => 'Hello', 'tr' => ''];

    expect(Translation::value($value, ['en', 'tr'], 'tr'))->toBe('Hello')
        ->and(Translation::sourceLocale($value, ['en', 'tr'], 'tr'))->toBe('en');
});
