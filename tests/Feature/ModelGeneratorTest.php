<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tardis\Database\ModelGenerator;

beforeEach(function () {
    Schema::dropIfExists('widgets');
    File::delete(app_path('Models/Widget.php'));
});

afterEach(function () {
    Schema::dropIfExists('widgets');
    File::delete(app_path('Models/Widget.php'));
});

test('generator writes a model with fillable excluding managed columns', function () {
    $generator = app(ModelGenerator::class);

    $file = $generator->generate('widgets', [
        ['name' => 'id', 'type' => 'integer', 'primary' => 1],
        ['name' => 'title', 'type' => 'string'],
        ['name' => 'created_at', 'type' => 'datetime'],
        ['name' => 'updated_at', 'type' => 'datetime'],
    ]);

    expect($file)->toBe(app_path('Models/Widget.php'))
        ->and(File::exists($file))->toBeTrue();

    $content = File::get($file);

    expect($content)
        ->toContain('namespace App\Models;')
        ->toContain('class Widget extends Model')
        ->toContain('protected $fillable = [')
        ->toContain("'title',")
        ->not->toContain("'id',")
        ->not->toContain('created_at')
        ->not->toContain('updated_at');
});

test('generator produces casts for casteable column types', function () {
    $generator = app(ModelGenerator::class);

    $content = File::get($generator->generate('widgets', [
        ['name' => 'id', 'type' => 'integer', 'primary' => 1],
        ['name' => 'is_admin', 'type' => 'boolean'],
        ['name' => 'price', 'type' => 'numeric'],
        ['name' => 'meta', 'type' => 'json'],
        ['name' => 'published_at', 'type' => 'datetime'],
    ]));

    expect($content)
        ->toContain('@var array<string, string>')
        ->toContain('protected $casts = [')
        ->toContain("'is_admin' => 'boolean',")
        ->toContain("'price' => 'decimal:2',")
        ->toContain("'meta' => 'array',")
        ->toContain("'published_at' => 'datetime',");
});

test('generator adds WithoutTimestamps when the table has no timestamps', function () {
    $generator = app(ModelGenerator::class);

    $content = File::get($generator->generate('widgets', [
        ['name' => 'id', 'type' => 'integer', 'primary' => 1],
        ['name' => 'sku', 'type' => 'string'],
    ]));

    expect($content)
        ->toContain('use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;')
        ->toContain('#[WithoutTimestamps]')
        ->toContain("#[Table('widgets')]");
});

test('generator keeps timestamps when both timestamp columns exist', function () {
    $generator = app(ModelGenerator::class);

    $content = File::get($generator->generate('widgets', [
        ['name' => 'id', 'type' => 'integer', 'primary' => 1],
        ['name' => 'title', 'type' => 'string'],
        ['name' => 'created_at', 'type' => 'datetime'],
        ['name' => 'updated_at', 'type' => 'datetime'],
    ]));

    expect($content)
        ->not->toContain('#[WithoutTimestamps]')
        ->not->toContain('WithoutTimestamps;');
});

test('generator uses key arguments for a non-standard primary key', function () {
    $generator = app(ModelGenerator::class);

    $content = File::get($generator->generate('widgets', [
        ['name' => 'sku', 'type' => 'varchar', 'primary' => 1],
        ['name' => 'title', 'type' => 'string'],
    ]));

    expect($content)
        ->toContain("#[Table('widgets', key: 'sku', keyType: 'string', incrementing: false)]");
});

test('generator refuses to overwrite an existing model without force', function () {
    $generator = app(ModelGenerator::class);
    $columns = [['name' => 'title', 'type' => 'string']];

    $generator->generate('widgets', $columns);

    expect(fn () => $generator->generate('widgets', $columns))
        ->toThrow(RuntimeException::class, 'Model [Widget] already exists.');

    expect(File::exists($generator->generate('widgets', $columns, ['force' => true])))->toBeTrue();
});

test('createTable generates a model when the create model toggle is on', function () {
    Livewire::test('tardis::pages.database')
        ->call('openCreateTable')
        ->set('newTableName', 'widgets')
        ->set('newTableColumns', [
            ['name' => 'title', 'type' => 'string', 'length' => '255', 'nullable' => false, 'default' => '', 'primary' => false],
        ])
        ->set('createModel', true)
        ->call('createTable')
        ->assertSet('selectedTable', 'widgets')
        ->assertSet('message', 'Table and model created successfully.')
        ->assertHasNoErrors();

    expect(File::exists(app_path('Models/Widget.php')))->toBeTrue();
});

test('generateModel creates a model for the selected table', function () {
    Schema::create('widgets', function ($table) {
        $table->id();
        $table->string('title');
        $table->timestamps();
    });

    Livewire::test('tardis::pages.database')
        ->call('selectTable', 'widgets')
        ->call('generateModel')
        ->assertSet('message', 'Model created successfully.')
        ->assertHasNoErrors();

    expect(File::exists(app_path('Models/Widget.php')))->toBeTrue();
});

test('artisan tardis:make-model creates a model for an existing table', function () {
    Schema::create('widgets', function ($table) {
        $table->id();
        $table->string('title');
    });

    $this->artisan('tardis:make-model', ['table' => 'widgets'])
        ->assertExitCode(0);

    expect(File::exists(app_path('Models/Widget.php')))->toBeTrue();
});

test('artisan tardis:make-model fails for a missing table', function () {
    $this->artisan('tardis:make-model', ['table' => 'nope'])
        ->assertExitCode(1);
});
