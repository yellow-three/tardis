<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;

beforeEach(function () {
    Schema::dropIfExists('widgets');
});

afterEach(function () {
    Schema::dropIfExists('widgets');
});

test('database page mounts and lists tables', function () {
    Schema::create('widgets', function ($table) {
        $table->id();
        $table->string('title');
    });

    Livewire::test('tardis::pages.database')
        ->assertSet('selectedTable', null)
        ->assertSet('error', null);
});

test('selectTable loads columns and row count', function () {
    Schema::create('widgets', function ($table) {
        $table->id();
        $table->string('title');
    });

    Livewire::test('tardis::pages.database')
        ->call('selectTable', 'widgets')
        ->assertSet('selectedTable', 'widgets')
        ->assertSet('totalRows', 0)
        ->assertSet('columns', function (array $columns) {
            $names = array_column($columns, 'name');

            return in_array('title', $names, true);
        });
});

test('createTable creates a table with columns and selects it', function () {
    Livewire::test('tardis::pages.database')
        ->call('openCreateTable')
        ->assertSet('showCreateTableModal', true)
        ->set('newTableName', 'widgets')
        ->set('newTableColumns', [
            ['name' => 'title', 'type' => 'string', 'length' => '255', 'nullable' => false, 'default' => '', 'primary' => false],
            ['name' => 'qty', 'type' => 'integer', 'length' => '', 'nullable' => false, 'default' => '0', 'primary' => false],
        ])
        ->call('createTable')
        ->assertSet('showCreateTableModal', false)
        ->assertSet('selectedTable', 'widgets')
        ->assertHasNoErrors();

    expect(Schema::hasTable('widgets'))->toBeTrue();

    $columns = Schema::getColumns('widgets');
    $names = array_column($columns, 'name');

    expect($names)->toContain('id', 'title', 'qty');
});

test('createTable auto id and timestamps toggles are honoured', function () {
    Livewire::test('tardis::pages.database')
        ->call('openCreateTable')
        ->set('newTableName', 'widgets')
        ->set('createAutoId', false)
        ->set('createTimestamps', false)
        ->set('newTableColumns', [
            ['name' => 'title', 'type' => 'string', 'length' => '255', 'nullable' => false, 'default' => '', 'primary' => true],
        ])
        ->call('createTable')
        ->assertHasNoErrors();

    $names = array_column(Schema::getColumns('widgets'), 'name');

    expect($names)->toContain('title')
        ->and($names)->not->toContain('id', 'created_at', 'updated_at');
});

test('createTable rejects invalid table names without hitting the database', function () {
    Livewire::test('tardis::pages.database')
        ->call('openCreateTable')
        ->set('newTableName', 'Invalid Name')
        ->set('newTableColumns', [
            ['name' => 'title', 'type' => 'string', 'length' => '', 'nullable' => false, 'default' => '', 'primary' => false],
        ])
        ->call('createTable')
        ->assertSet('error', 'Table name must start with a letter and contain only lowercase letters, numbers and underscores.')
        ->assertSet('showCreateTableModal', true);

    expect(Schema::hasTable('Invalid Name'))->toBeFalse();
});

test('createTable rejects zero columns', function () {
    Livewire::test('tardis::pages.database')
        ->call('openCreateTable')
        ->set('newTableName', 'widgets')
        ->set('newTableColumns', [])
        ->call('createTable')
        ->assertSet('error', 'Add at least one column.');

    expect(Schema::hasTable('widgets'))->toBeFalse();
});

test('addColumn appends a column to the selected table', function () {
    Schema::create('widgets', function ($table) {
        $table->id();
        $table->string('title');
    });

    Livewire::test('tardis::pages.database')
        ->call('selectTable', 'widgets')
        ->call('openAddColumn')
        ->set('newColumn', ['name' => 'sku', 'type' => 'string', 'length' => '100', 'nullable' => true, 'default' => ''])
        ->call('addColumn')
        ->assertSet('showAddColumnModal', false)
        ->assertHasNoErrors();

    $names = array_column(Schema::getColumns('widgets'), 'name');

    expect($names)->toContain('sku');
});

test('addColumn rejects unsupported column types', function () {
    Schema::create('widgets', function ($table) {
        $table->id();
        $table->string('title');
    });

    Livewire::test('tardis::pages.database')
        ->call('selectTable', 'widgets')
        ->call('openAddColumn')
        ->set('newColumn', ['name' => 'hack', 'type' => 'evil; drop table widgets', 'length' => '', 'nullable' => false, 'default' => ''])
        ->call('addColumn')
        ->assertSet('error', 'Unsupported column type [evil; drop table widgets].')
        ->assertSet('showAddColumnModal', true);

    expect(Schema::hasColumn('widgets', 'hack'))->toBeFalse();
});

test('editColumn renames a column and changes its type', function () {
    Schema::create('widgets', function ($table) {
        $table->id();
        $table->string('title');
    });

    Livewire::test('tardis::pages.database')
        ->call('selectTable', 'widgets')
        ->call('openEditColumn', 'title')
        ->set('editColumn.name', 'heading')
        ->set('editColumn.type', 'text')
        ->call('saveEditColumn')
        ->assertSet('showEditColumnModal', false)
        ->assertSet('error', null)
        ->assertHasNoErrors();

    $columns = Schema::getColumns('widgets');
    $names = array_column($columns, 'name');

    expect($names)->toContain('heading')
        ->and($names)->not->toContain('title');
});

test('dropColumn removes the column from the selected table', function () {
    Schema::create('widgets', function ($table) {
        $table->id();
        $table->string('title');
        $table->string('obsolete');
    });

    Livewire::test('tardis::pages.database')
        ->call('selectTable', 'widgets')
        ->call('requestDropColumn', 'obsolete')
        ->assertSet('confirmDropColumn', 'obsolete')
        ->call('dropColumn')
        ->assertSet('confirmDropColumn', null)
        ->assertHasNoErrors();

    expect(Schema::hasColumn('widgets', 'obsolete'))->toBeFalse();
});

test('dropTable drops the selected table', function () {
    Schema::create('widgets', function ($table) {
        $table->id();
        $table->string('title');
    });

    Livewire::test('tardis::pages.database')
        ->call('selectTable', 'widgets')
        ->call('requestDropTable')
        ->assertSet('confirmDropTable', true)
        ->call('dropTable')
        ->assertSet('confirmDropTable', false)
        ->assertSet('selectedTable', null);

    expect(Schema::hasTable('widgets'))->toBeFalse();
});
