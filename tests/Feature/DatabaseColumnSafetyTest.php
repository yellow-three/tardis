<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tardis\Models\ActivityLog;

beforeEach(function () {
    Schema::dropIfExists('gizmos');
    Schema::create('gizmos', function ($table) {
        $table->id();
        $table->string('title');
        $table->boolean('active')->default(false);
        // A type the editor has no exact name for: a boolean is a tinyint on
        // SQLite and MySQL, but a real boolean on PostgreSQL, where a smallint
        // (int2) plays that part instead.
        Schema::getConnection()->getDriverName() === 'pgsql'
            ? $table->smallInteger('level')->default(0)
            : $table->boolean('level')->default(false);
    });
});

afterEach(function () {
    Schema::dropIfExists('gizmos');
});

function gizmoColumn(string $name): array
{
    return collect(Schema::getColumns('gizmos'))->firstWhere('name', $name);
}

function gizmoRow(string $name): int
{
    return array_search($name, array_column(Schema::getColumns('gizmos'), 'name'), true);
}

/**
 * The editor only knows a short list of types and guesses the closest one for
 * an existing column (tinyint(1) reads as "integer"). Saving used to re-declare
 * the column from that guess, so pressing Save on an untouched row changed it.
 */
test('saving an untouched column leaves its definition alone', function () {
    $before = gizmoColumn('active');

    Livewire::test('tardis::pages.database.edit', ['table' => 'gizmos'])
        ->call('saveColumn', gizmoRow('active'))
        ->assertSet('error', null);

    expect(gizmoColumn('active')['type'])->toBe($before['type']);
});

test('renaming a column the editor cannot describe exactly only renames it', function () {
    $before = gizmoColumn('active');

    Livewire::test('tardis::pages.database.edit', ['table' => 'gizmos'])
        ->set('editColumns.'.gizmoRow('active').'.name', 'enabled')
        ->call('saveColumn', gizmoRow('active'))
        ->assertSet('error', null);

    expect(gizmoColumn('enabled')['type'])->toBe($before['type']);
});

test('changing a column the editor cannot describe exactly is refused', function () {
    $before = gizmoColumn('level');

    Livewire::test('tardis::pages.database.edit', ['table' => 'gizmos'])
        ->set('editColumns.'.gizmoRow('level').'.nullable', true)
        ->call('saveColumn', gizmoRow('level'))
        ->assertSet('error', __('tardis::database.column_not_editable', ['column' => 'level']));

    expect(gizmoColumn('level'))->toBe($before);
});

test('the primary key cannot be modified from the editor', function () {
    $before = gizmoColumn('id');

    Livewire::test('tardis::pages.database.edit', ['table' => 'gizmos'])
        ->set('editColumns.'.gizmoRow('id').'.nullable', true)
        ->call('saveColumn', gizmoRow('id'))
        ->assertSet('error', __('tardis::database.column_not_editable', ['column' => 'id']));

    expect(gizmoColumn('id'))->toBe($before);
});

test('a column the editor describes exactly can still be changed', function () {
    Livewire::test('tardis::pages.database.edit', ['table' => 'gizmos'])
        ->set('editColumns.'.gizmoRow('title').'.nullable', true)
        ->call('saveColumn', gizmoRow('title'))
        ->assertSet('error', null);

    expect(gizmoColumn('title')['nullable'])->toBeTrue();
});

test('outside the allowed environments the explorer does not change the schema', function () {
    app()->detectEnvironment(fn () => 'production');

    Livewire::test('tardis::pages.database.edit', ['table' => 'gizmos'])
        ->assertSet('readOnly', true)
        ->set('newColumn', ['name' => 'sku', 'type' => 'string', 'length' => '', 'nullable' => true, 'default' => '', 'primary' => false])
        ->call('addColumn')
        ->assertSet('error', __('tardis::database.schema_changes_disabled'))
        ->call('requestDropTable')
        ->call('dropTable');

    Livewire::test('tardis::pages.database.create')
        ->set('newTableName', 'sprockets')
        ->set('newTableColumns', [['name' => 'title', 'type' => 'string', 'length' => '', 'nullable' => false, 'default' => '', 'primary' => false]])
        ->call('createTable')
        ->assertSet('error', __('tardis::database.schema_changes_disabled'));

    expect(Schema::hasTable('gizmos'))->toBeTrue()
        ->and(Schema::hasColumn('gizmos', 'sku'))->toBeFalse()
        ->and(Schema::hasTable('sprockets'))->toBeFalse();
});

test('schema changes are written to the activity log', function () {
    $this->artisan('migrate');

    Livewire::test('tardis::pages.database.edit', ['table' => 'gizmos'])
        ->set('newColumn', ['name' => 'sku', 'type' => 'string', 'length' => '', 'nullable' => true, 'default' => '', 'primary' => false])
        ->call('addColumn')
        ->assertSet('error', null);

    $entry = ActivityLog::query()->where('model_type', 'tardis.schema')->latest('id')->first();

    expect($entry)->not->toBeNull()
        ->and($entry->action)->toBe('column_added')
        ->and($entry->new_values)->toMatchArray(['table' => 'gizmos', 'column' => 'sku']);
});
