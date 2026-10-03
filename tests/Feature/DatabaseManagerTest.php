<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
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

test('viewTable opens the table info modal and closeViewTable dismisses it', function () {
    Schema::create('widgets', function ($table) {
        $table->id();
        $table->string('title')->unique();
    });

    Livewire::test('tardis::pages.database')
        ->call('viewTable', 'widgets')
        ->assertSet('showTableInfoModal', true)
        ->assertSet('selectedTable', 'widgets')
        ->assertSet('columns', function (array $columns) {
            $byName = array_column($columns, null, 'name');

            return ($byName['id']['key'] ?? '') === 'PRI'
                && ($byName['title']['key'] ?? '') === 'UNI';
        })
        ->call('closeViewTable')
        ->assertSet('showTableInfoModal', false);
});

test('create page creates a table and redirects to the explorer', function () {
    Livewire::test('tardis::pages.database.create')
        ->set('newTableName', 'widgets')
        ->set('newTableColumns', [
            ['name' => 'title', 'type' => 'string', 'length' => '255', 'nullable' => false, 'default' => '', 'primary' => false],
            ['name' => 'qty', 'type' => 'integer', 'length' => '', 'nullable' => false, 'default' => '0', 'primary' => false],
        ])
        ->call('createTable')
        ->assertRedirect(route('tardis.database.index'))
        ->assertHasNoErrors();

    expect(Schema::hasTable('widgets'))->toBeTrue();

    $names = array_column(Schema::getColumns('widgets'), 'name');

    expect($names)->toContain('id', 'title', 'qty');
});

test('create page honours the auto id and timestamps toggles', function () {
    Livewire::test('tardis::pages.database.create')
        ->set('newTableName', 'widgets')
        ->set('createAutoId', false)
        ->set('createTimestamps', false)
        ->set('newTableColumns', [
            ['name' => 'title', 'type' => 'string', 'length' => '255', 'nullable' => false, 'default' => '', 'primary' => true],
        ])
        ->call('createTable')
        ->assertRedirect(route('tardis.database.index'))
        ->assertHasNoErrors();

    $names = array_column(Schema::getColumns('widgets'), 'name');

    expect($names)->toContain('title')
        ->and($names)->not->toContain('id', 'created_at', 'updated_at');
});

test('create page rejects invalid table names without hitting the database', function () {
    Livewire::test('tardis::pages.database.create')
        ->set('newTableName', 'Invalid Name')
        ->set('newTableColumns', [
            ['name' => 'title', 'type' => 'string', 'length' => '', 'nullable' => false, 'default' => '', 'primary' => false],
        ])
        ->call('createTable')
        ->assertSet('error', 'Table name must start with a letter and contain only lowercase letters, numbers and underscores.');

    expect(Schema::hasTable('Invalid Name'))->toBeFalse();
});

test('create page rejects zero columns', function () {
    Livewire::test('tardis::pages.database.create')
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

    Livewire::test('tardis::pages.database.edit', ['table' => 'widgets'])
        ->call('openAddColumn')
        ->set('newColumn', ['name' => 'sku', 'type' => 'string', 'length' => '100', 'nullable' => true, 'default' => '', 'primary' => false])
        ->call('addColumn')
        ->assertSet('showAddColumnForm', false)
        ->assertHasNoErrors();

    $names = array_column(Schema::getColumns('widgets'), 'name');

    expect($names)->toContain('sku');
});

test('addColumn rejects unsupported column types', function () {
    Schema::create('widgets', function ($table) {
        $table->id();
        $table->string('title');
    });

    Livewire::test('tardis::pages.database.edit', ['table' => 'widgets'])
        ->call('openAddColumn')
        ->set('newColumn', ['name' => 'hack', 'type' => 'evil; drop table widgets', 'length' => '', 'nullable' => false, 'default' => '', 'primary' => false])
        ->call('addColumn')
        ->assertSet('error', 'Unsupported column type [evil; drop table widgets].')
        ->assertSet('showAddColumnForm', true);

    expect(Schema::hasColumn('widgets', 'hack'))->toBeFalse();
});

test('editColumn renames a column and changes its type', function () {
    Schema::create('widgets', function ($table) {
        $table->id();
        $table->string('title');
    });

    Livewire::test('tardis::pages.database.edit', ['table' => 'widgets'])
        ->call('openEditColumn', 'title')
        ->set('editColumn.name', 'heading')
        ->set('editColumn.type', 'text')
        ->call('saveEditColumn')
        ->assertSet('showEditColumnForm', false)
        ->assertSet('error', null)
        ->assertHasNoErrors();

    $names = array_column(Schema::getColumns('widgets'), 'name');

    expect($names)->toContain('heading')
        ->and($names)->not->toContain('title');
});

test('addEditColumnRow appends an empty editable row', function () {
    Schema::create('widgets', function ($table) {
        $table->id();
        $table->string('title');
    });

    Livewire::test('tardis::pages.database.edit', ['table' => 'widgets'])
        ->assertCount('editColumns', 2)
        ->call('addEditColumnRow')
        ->assertCount('editColumns', 3)
        ->assertSet('editColumns.2.name', '')
        ->assertSet('editColumns.2.original', '')
        ->assertSet('editColumns.2.type', 'string');
});

test('saveColumn persists a new column from an added row', function () {
    Schema::create('widgets', function ($table) {
        $table->id();
        $table->string('title');
    });

    Livewire::test('tardis::pages.database.edit', ['table' => 'widgets'])
        ->call('addEditColumnRow')
        ->set('editColumns.2.name', 'sku')
        ->set('editColumns.2.type', 'string')
        ->set('editColumns.2.length', '100')
        ->set('editColumns.2.nullable', true)
        ->call('saveColumn', 2)
        ->assertSet('message', 'Column added successfully.')
        ->assertSet('error', null)
        ->assertHasNoErrors();

    $names = array_column(Schema::getColumns('widgets'), 'name');

    expect($names)->toContain('sku');
});

test('saveColumn renames and updates an existing column', function () {
    Schema::create('widgets', function ($table) {
        $table->id();
        $table->string('title');
    });

    Livewire::test('tardis::pages.database.edit', ['table' => 'widgets'])
        ->set('editColumns.1.name', 'heading')
        ->set('editColumns.1.type', 'text')
        ->call('saveColumn', 1)
        ->assertSet('message', 'Column updated successfully.')
        ->assertSet('error', null)
        ->assertHasNoErrors();

    $names = array_column(Schema::getColumns('widgets'), 'name');

    expect($names)->toContain('heading')
        ->and($names)->not->toContain('title');
});

test('saveColumn rejects invalid column names without hitting the database', function () {
    Schema::create('widgets', function ($table) {
        $table->id();
        $table->string('title');
    });

    Livewire::test('tardis::pages.database.edit', ['table' => 'widgets'])
        ->call('addEditColumnRow')
        ->set('editColumns.2.name', 'Bad Name')
        ->call('saveColumn', 2)
        ->assertSet('error', 'Column #3 name must start with a letter and contain only lowercase letters, numbers and underscores.');

    $names = array_column(Schema::getColumns('widgets'), 'name');

    expect($names)->not->toContain('Bad Name');
});

test('requestRemoveColumnRow discards an unsaved row without touching the database', function () {
    Schema::create('widgets', function ($table) {
        $table->id();
        $table->string('title');
    });

    Livewire::test('tardis::pages.database.edit', ['table' => 'widgets'])
        ->call('addEditColumnRow')
        ->assertCount('editColumns', 3)
        ->call('requestRemoveColumnRow', 2)
        ->assertCount('editColumns', 2)
        ->assertSet('confirmDropColumn', null);

    $names = array_column(Schema::getColumns('widgets'), 'name');

    expect($names)->toContain('id', 'title');
});

test('requestRemoveColumnRow opens the drop confirmation for an existing column', function () {
    Schema::create('widgets', function ($table) {
        $table->id();
        $table->string('title');
        $table->string('obsolete');
    });

    Livewire::test('tardis::pages.database.edit', ['table' => 'widgets'])
        ->call('requestRemoveColumnRow', 2)
        ->assertSet('confirmDropColumn', 'obsolete');
});

test('dropColumn removes the column from the selected table', function () {
    Schema::create('widgets', function ($table) {
        $table->id();
        $table->string('title');
        $table->string('obsolete');
    });

    Livewire::test('tardis::pages.database.edit', ['table' => 'widgets'])
        ->call('requestDropColumn', 'obsolete')
        ->assertSet('confirmDropColumn', 'obsolete')
        ->call('dropColumn')
        ->assertSet('confirmDropColumn', null)
        ->assertHasNoErrors();

    expect(Schema::hasColumn('widgets', 'obsolete'))->toBeFalse();
});

test('dropTable drops the table and redirects to the explorer', function () {
    Schema::create('widgets', function ($table) {
        $table->id();
        $table->string('title');
    });

    Livewire::test('tardis::pages.database.edit', ['table' => 'widgets'])
        ->call('requestDropTable')
        ->assertSet('confirmDropTable', true)
        ->call('dropTable')
        ->assertRedirect(route('tardis.database.index'));

    expect(Schema::hasTable('widgets'))->toBeFalse();
});

test('framework and package tables are hidden from the explorer', function () {
    Schema::create('widgets', function ($table) {
        $table->id();
    });
    Schema::create('migrations', function ($table) {
        $table->id();
    });
    Schema::create('tardis_roles', function ($table) {
        $table->id();
    });

    $names = collect(Livewire::test('tardis::pages.database')->get('tables'))->pluck('name')->all();

    expect($names)->toContain('widgets')->not->toContain('migrations')->not->toContain('tardis_roles');

    Schema::dropIfExists('migrations');
    Schema::dropIfExists('tardis_roles');
});

test('a hidden table cannot be selected, viewed, edited or dropped', function () {
    Schema::create('migrations', function ($table) {
        $table->id();
    });

    Livewire::test('tardis::pages.database')
        ->call('selectTable', 'migrations')
        ->assertSet('selectedTable', null);

    Livewire::test('tardis::pages.database.edit', ['table' => 'migrations'])->assertNotFound();

    expect(Schema::hasTable('migrations'))->toBeTrue();

    Schema::dropIfExists('migrations');
});

test('the table being edited cannot be swapped by the client', function () {
    Schema::create('widgets', function ($table) {
        $table->id();
    });

    $component = Livewire::test('tardis::pages.database.edit', ['table' => 'widgets']);

    expect(fn () => $component->set('selectedTable', 'users'))
        ->toThrow(CannotUpdateLockedPropertyException::class);
});
