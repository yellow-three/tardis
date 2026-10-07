<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Tardis\Bread\Sources\JsonBreadSource;
use Tardis\Models\Permission;

class MakeBreadGuardArticle extends Model
{
    protected $table = 'make_bread_articles';

    protected $guarded = [];

    protected $fillable = ['title', 'summary', 'published'];
}

beforeEach(function () {
    Schema::dropIfExists('make_bread_articles');
    Schema::create('make_bread_articles', function ($table) {
        $table->id();
        $table->string('title');
        $table->string('summary')->nullable();
        $table->boolean('published')->default(false);
    });
});

afterEach(function () {
    Schema::dropIfExists('make_bread_articles');
});

test('make-bread writes a definition with the slug derived from the model name', function () {
    $this->artisan('tardis:make-bread', ['model' => MakeBreadGuardArticle::class])
        ->assertSuccessful();

    $definition = app(JsonBreadSource::class)->find('make_bread_guard_articles');

    expect($definition)->not->toBeNull()
        ->and($definition->model)->toBe(MakeBreadGuardArticle::class);
});

test('make-bread honours an explicit slug', function () {
    $this->artisan('tardis:make-bread', ['model' => MakeBreadGuardArticle::class, 'slug' => 'articles'])
        ->assertSuccessful();

    expect(app(JsonBreadSource::class)->has('articles'))->toBeTrue();
});

test('make-bread derives the required flag from column nullability', function () {
    $this->artisan('tardis:make-bread', ['model' => MakeBreadGuardArticle::class, 'slug' => 'articles'])
        ->assertSuccessful();

    $fields = app(JsonBreadSource::class)->find('articles')->toArray()['fields'];
    $fields = collect($fields)->keyBy('name');

    expect($fields['title']['required'])->toBeTrue()
        ->and($fields['summary']['required'])->toBeFalse();
});

test('make-bread fails for a model class that does not exist', function () {
    $this->artisan('tardis:make-bread', ['model' => 'App\\Models\\DoesNotExist'])
        ->assertFailed();
});

test('make-bread provisions the five BREAD permissions for the new resource', function () {
    $this->artisan('migrate');

    $this->artisan('tardis:make-bread', ['model' => MakeBreadGuardArticle::class, 'slug' => 'articles'])
        ->assertSuccessful();

    expect(Permission::where('group', 'BREAD')->pluck('slug')->sort()->values()->all())
        ->toBe(['add articles', 'browse articles', 'delete articles', 'edit articles', 'read articles']);
});
