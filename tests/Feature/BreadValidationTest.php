<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tardis\Bread\Sources\JsonBreadSource;

class BreadValidationTestModel extends Model
{
    protected $table = 'bread_page_validation';

    protected $guarded = [];

    public $timestamps = true;
}

beforeEach(function () {
    Schema::create('bread_page_validation', function (Blueprint $table) {
        $table->id();
        $table->string('title');
        $table->string('email');
        $table->text('notes')->nullable();
        $table->timestamps();
    });

    app()->instance(JsonBreadSource::class, new JsonBreadSource(__DIR__.'/../Fixtures/bread'));
});

test('the create page enforces a field max length rule', function () {
    Livewire::test('tardis::pages.bread.create', ['slug' => 'bread-page-validation'])
        ->set('form.title', 'far too long for a five character limit')
        ->set('form.email', 'valid@example.com')
        ->call('save')
        ->assertHasErrors(['form.title']);

    expect(BreadValidationTestModel::count())->toBe(0);
});

test('the create page enforces a field email rule', function () {
    Livewire::test('tardis::pages.bread.create', ['slug' => 'bread-page-validation'])
        ->set('form.title', 'ok')
        ->set('form.email', 'not-an-email-address')
        ->call('save')
        ->assertHasErrors(['form.email']);

    expect(BreadValidationTestModel::count())->toBe(0);
});

test('the create page enforces a max length rule on a nullable field', function () {
    Livewire::test('tardis::pages.bread.create', ['slug' => 'bread-page-validation'])
        ->set('form.title', 'ok')
        ->set('form.email', 'valid@example.com')
        ->set('form.notes', 'much longer than eight')
        ->call('save')
        ->assertHasErrors(['form.notes']);

    expect(BreadValidationTestModel::count())->toBe(0);
});

test('the create page still saves a record when every field satisfies its rules', function () {
    Livewire::test('tardis::pages.bread.create', ['slug' => 'bread-page-validation'])
        ->set('form.title', 'ok')
        ->set('form.email', 'valid@example.com')
        ->set('form.notes', 'short')
        ->call('save')
        ->assertHasNoErrors();

    expect(BreadValidationTestModel::where('email', 'valid@example.com')->exists())->toBeTrue();
});

test('a nullable field left empty is not measured against its other rules', function () {
    Livewire::test('tardis::pages.bread.create', ['slug' => 'bread-page-validation'])
        ->set('form.title', 'ok')
        ->set('form.email', 'valid@example.com')
        ->set('form.notes', null)
        ->call('save')
        ->assertHasNoErrors();

    expect(BreadValidationTestModel::count())->toBe(1);
});

test('the edit page enforces a field max length rule', function () {
    $record = BreadValidationTestModel::create([
        'title' => 'ok',
        'email' => 'valid@example.com',
        'notes' => null,
    ]);

    Livewire::test('tardis::pages.bread.edit', [
        'slug' => 'bread-page-validation',
        'id' => $record->getKey(),
    ])
        ->set('form.title', 'far too long for a five character limit')
        ->call('save')
        ->assertHasErrors(['form.title']);

    expect($record->fresh()->title)->toBe('ok');
});

test('the edit page enforces a field email rule', function () {
    $record = BreadValidationTestModel::create([
        'title' => 'ok',
        'email' => 'valid@example.com',
        'notes' => null,
    ]);

    Livewire::test('tardis::pages.bread.edit', [
        'slug' => 'bread-page-validation',
        'id' => $record->getKey(),
    ])
        ->set('form.email', 'not-an-email-address')
        ->call('save')
        ->assertHasErrors(['form.email']);

    expect($record->fresh()->email)->toBe('valid@example.com');
});
