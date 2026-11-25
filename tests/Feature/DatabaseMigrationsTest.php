<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tardis\Database\MigrationWriter;

function migrationDir(): string
{
    return (string) config('tardis.database.migrations.path');
}

/** @return array<int, string> */
function writtenMigrations(): array
{
    return is_dir(migrationDir()) ? array_values(array_map('basename', glob(migrationDir().'/*.php') ?: [])) : [];
}

function createGadgetsThroughScreen(): Testable
{
    return Livewire::test('tardis::pages.database.create')
        ->set('newTableName', 'gadgets')
        ->set('newTableColumns', [['name' => 'title', 'type' => 'string', 'length' => '120', 'nullable' => false, 'default' => '', 'primary' => false, 'key' => '']])
        ->set('createModel', false)
        ->call('createTable');
}

beforeEach(function () {
    Schema::dropIfExists('gadgets');
    File::deleteDirectory(migrationDir());
});

afterEach(function () {
    Schema::dropIfExists('gadgets');
});

test('creating a table writes a migration, runs it and records it', function () {
    createGadgetsThroughScreen()->assertSet('error', null);

    $files = writtenMigrations();

    expect($files)->toHaveCount(1)
        ->and($files[0])->toEndWith('_create_gadgets_table.php')
        ->and(Schema::hasColumns('gadgets', ['id', 'title', 'created_at']))->toBeTrue()
        ->and(DB::table('migrations')->where('migration', substr($files[0], 0, -4))->exists())->toBeTrue()
        ->and(File::get(migrationDir().'/'.$files[0]))->toContain("\$table->string('title', 120);");

    // The file is a real migration: rolling it back drops the table.
    Artisan::call('migrate:rollback', ['--path' => migrationDir().'/'.$files[0], '--realpath' => true, '--force' => true]);
    expect(Schema::hasTable('gadgets'))->toBeFalse();
});

test('adding, changing and dropping a column each write a reversible migration', function () {
    createGadgetsThroughScreen();

    Livewire::test('tardis::pages.database.edit', ['table' => 'gadgets'])
        ->call('openAddColumn')
        ->set('newColumn', ['name' => 'sku', 'type' => 'string', 'length' => '40', 'nullable' => true, 'default' => '', 'primary' => false])
        ->call('addColumn')
        ->assertSet('error', null)
        ->call('openEditColumn', 'sku')
        ->set('editColumn.name', 'code')
        ->call('saveEditColumn')
        ->assertSet('error', null)
        ->call('requestDropColumn', 'code')
        ->call('dropColumn')
        ->assertSet('error', null);

    $files = writtenMigrations();

    expect($files)->toHaveCount(4)
        ->and($files[1])->toContain('_add_sku_to_gadgets_table')
        ->and($files[2])->toContain('_change_sku_on_gadgets_table')
        ->and($files[3])->toContain('_drop_code_from_gadgets_table')
        ->and(Schema::hasColumn('gadgets', 'code'))->toBeFalse();

    // Roll back the drop and the rename: the column is back under its first name.
    Artisan::call('migrate:rollback', ['--path' => migrationDir().'/'.$files[3], '--realpath' => true, '--force' => true]);
    expect(Schema::hasColumn('gadgets', 'code'))->toBeTrue();

    Artisan::call('migrate:rollback', ['--path' => migrationDir().'/'.$files[2], '--realpath' => true, '--force' => true]);
    expect(Schema::hasColumn('gadgets', 'sku'))->toBeTrue();
});

test('dropping a table writes a migration', function () {
    createGadgetsThroughScreen();

    Livewire::test('tardis::pages.database.edit', ['table' => 'gadgets'])
        ->call('requestDropTable')
        ->call('dropTable');

    expect(Schema::hasTable('gadgets'))->toBeFalse()
        ->and(writtenMigrations()[1])->toContain('_drop_gadgets_table');
});

test('in write mode only the file is written and the screen says so', function () {
    config()->set('tardis.database.migrations.mode', 'write');

    createGadgetsThroughScreen();

    expect(Schema::hasTable('gadgets'))->toBeFalse()
        ->and(writtenMigrations())->toHaveCount(1)
        ->and(session('message'))->toContain('php artisan migrate');

    Artisan::call('migrate', ['--path' => migrationDir(), '--realpath' => true, '--force' => true]);
    expect(Schema::hasTable('gadgets'))->toBeTrue();
});

test('with migrations off the schema is altered directly and no file is written', function () {
    config()->set('tardis.database.migrations.mode', 'off');

    createGadgetsThroughScreen();

    expect(Schema::hasTable('gadgets'))->toBeTrue()
        ->and(writtenMigrations())->toBe([]);
});

test('changes made in the same second still run in the order they were made', function () {
    $this->travelTo(now()->setTime(12, 0, 0));
    $writer = app(MigrationWriter::class);

    $writer->createTable('gadgets', [['name' => 'title', 'type' => 'string', 'length' => '', 'nullable' => false, 'default' => '', 'primary' => false]]);
    $writer->addColumn('gadgets', ['name' => 'sku', 'type' => 'string', 'length' => '', 'nullable' => true, 'default' => '', 'primary' => false]);

    $files = writtenMigrations();
    sort($files);

    expect($files[0])->toContain('_create_gadgets_table')
        ->and($files[1])->toContain('_add_sku_to_gadgets_table');

    Artisan::call('migrate', ['--path' => migrationDir(), '--realpath' => true, '--force' => true]);
    expect(Schema::hasColumn('gadgets', 'sku'))->toBeTrue();
});

test('editor values become exported strings, never code', function () {
    $file = app(MigrationWriter::class)->createTable('gadgets', [
        ['name' => 'title', 'type' => 'string', 'length' => '', 'nullable' => false, 'default' => "x'); echo 'pwned", 'primary' => false],
    ]);

    $contents = File::get($file);

    expect($contents)->toContain("->default('x\\'); echo \\'pwned')")
        ->and(fn () => token_get_all($contents, TOKEN_PARSE))->not->toThrow(ParseError::class);

    app(MigrationWriter::class)->run($file);

    DB::table('gadgets')->insert(['created_at' => now()]);
    expect(DB::table('gadgets')->value('title'))->toBe("x'); echo 'pwned");
});

test('a migration that fails is removed and the error is shown', function () {
    createGadgetsThroughScreen();

    Livewire::test('tardis::pages.database.edit', ['table' => 'gadgets'])
        ->call('openAddColumn')
        ->set('newColumn', ['name' => 'title', 'type' => 'string', 'length' => '', 'nullable' => false, 'default' => '', 'primary' => false])
        ->call('addColumn')
        ->assertSet('error', fn (?string $error) => $error !== null);

    expect(writtenMigrations())->toHaveCount(1);
});

test('the screen tells where changes go', function () {
    Livewire::test('tardis::pages.database.create')->assertSee(__('tardis::database.migrations_notice_migrate'));

    config()->set('tardis.database.migrations.mode', 'off');
    Livewire::test('tardis::pages.database.create')->assertDontSee(__('tardis::database.migrations_notice_migrate'));
});
