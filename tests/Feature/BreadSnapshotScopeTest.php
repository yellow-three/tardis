<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tardis\Bread\Sources\JsonBreadSource;

class SnapshotScopeNote extends Model
{
    protected $table = 'snapshot_scope_notes';

    protected $guarded = [];
}

beforeEach(function () {
    Schema::create('snapshot_scope_notes', function ($table) {
        $table->id();
        $table->string('title');
        $table->string('internal_note')->nullable();
        $table->timestamps();
    });

    $this->breadPath = sys_get_temp_dir().'/tardis-snapshot-scope-'.uniqid();
    File::ensureDirectoryExists($this->breadPath);
    File::put($this->breadPath.'/notes.json', json_encode([
        'slug' => 'notes',
        'model' => SnapshotScopeNote::class,
        'name' => 'Note',
        'name_plural' => 'Notes',
        'fields' => [['name' => 'title', 'type' => 'text', 'label' => 'Title']],
    ]));
    app()->instance(JsonBreadSource::class, new JsonBreadSource($this->breadPath));

    $this->note = SnapshotScopeNote::create(['title' => 'Public title', 'internal_note' => 'never-show-this']);
});

afterEach(function () {
    File::deleteDirectory($this->breadPath);
    Schema::dropIfExists('snapshot_scope_notes');
});

/**
 * Livewire sends every public property to the browser. A column the BREAD
 * does not show (and the model does not $hide) must not ride along in the
 * snapshot of the edit or read page.
 */
test('the edit and read pages only carry the columns the definition shows', function (string $component) {
    $page = Livewire::test($component, ['slug' => 'notes', 'id' => $this->note->id])->assertOk();

    expect($page->html())->not->toContain('never-show-this')
        ->and(json_encode($page->snapshot))->not->toContain('never-show-this')
        ->and(json_encode($page->snapshot))->toContain('Public title');
})->with(['tardis::pages.bread.edit', 'tardis::pages.bread.read']);

test('saving the edit page still leaves the other columns alone', function () {
    Livewire::test('tardis::pages.bread.edit', ['slug' => 'notes', 'id' => $this->note->id])
        ->set('form.title', 'New title')
        ->call('save')
        ->assertHasNoErrors();

    expect($this->note->fresh())
        ->title->toBe('New title')
        ->internal_note->toBe('never-show-this');
});

test('the definition stays on the server: only the slug is in the snapshot', function (string $component, array $params) {
    $page = Livewire::test($component, ['slug' => 'notes'] + ($params === [] ? [] : ['id' => $this->note->id]))->assertOk();

    expect($page->snapshot['data'])->not->toHaveKey('bread')
        ->and(json_encode($page->snapshot))->not->toContain(addslashes(SnapshotScopeNote::class))
        ->and($page->snapshot['data']['slug'])->toBe('notes');
})->with([
    'index' => ['tardis::pages.bread.index', []],
    'create' => ['tardis::pages.bread.create', []],
    'read' => ['tardis::pages.bread.read', ['id']],
    'edit' => ['tardis::pages.bread.edit', ['id']],
]);

test('an open page follows a definition changed in the meantime', function () {
    $page = Livewire::test('tardis::pages.bread.index', ['slug' => 'notes'])->assertSee('Notes');

    $definition = json_decode(File::get($this->breadPath.'/notes.json'), true);
    File::put($this->breadPath.'/notes.json', json_encode(['name_plural' => 'Memos'] + $definition));
    app()->instance(JsonBreadSource::class, new JsonBreadSource($this->breadPath));

    $page->call('$refresh')->assertSee('Memos')->assertDontSee('Notes');
});

test('a definition removed while the page is open is a 404 on the next request', function () {
    $page = Livewire::test('tardis::pages.bread.index', ['slug' => 'notes'])->assertOk();

    File::delete($this->breadPath.'/notes.json');
    app()->instance(JsonBreadSource::class, new JsonBreadSource($this->breadPath));

    $page->call('$refresh')->assertStatus(404);
});
