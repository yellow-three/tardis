<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tardis\Bread\Sources\JsonBreadSource;
use Tardis\Models\ActivityLog;

class HistoryNote extends Model
{
    protected $table = 'history_notes';

    protected $guarded = [];
}

beforeEach(function () {
    $this->artisan('migrate');
    Schema::create('history_notes', function ($table) {
        $table->id();
        $table->string('title');
        $table->string('status')->default('draft');
        $table->string('internal')->nullable();
        $table->timestamps();
    });

    $this->breadPath = sys_get_temp_dir().'/tardis-history-'.uniqid();
    app()->instance(JsonBreadSource::class, $source = new JsonBreadSource($this->breadPath));
    $source->save([
        'slug' => 'notes',
        'model' => HistoryNote::class,
        'name' => 'Note',
        'name_plural' => 'Notes',
        'fields' => [
            ['name' => 'title', 'type' => 'text', 'label' => 'Title'],
            ['name' => 'status', 'type' => 'text', 'label' => 'Status'],
            ['name' => 'internal', 'type' => 'text', 'label' => 'Internal', 'read' => false],
        ],
    ]);

    $this->note = HistoryNote::create(['title' => 'First', 'status' => 'draft', 'internal' => 'secret-a']);
});

afterEach(fn () => File::deleteDirectory($this->breadPath));

test('the record page lists its changes by label and can undo one', function () {
    Livewire::test('tardis::pages.bread.edit', ['slug' => 'notes', 'id' => $this->note->id])
        ->set('form.title', 'Second')->set('form.internal', 'secret-b')
        ->call('save')->assertHasNoErrors();

    $page = Livewire::test('tardis::pages.bread.read', ['slug' => 'notes', 'id' => $this->note->id])
        ->assertSee('History')->assertSee('First')->assertSee('Second')
        // Internal is not on this page, so its old value is not shown either.
        ->assertDontSee('secret-a');

    $entry = $page->instance()->history->first();

    expect($entry['action'])->toBe('updated')
        ->and(array_column($entry['changes'], 'label'))->toBe(['Title'])
        ->and($entry['revertible'])->toBeTrue();

    $page->call('revert', $entry['id'])->assertSee('The earlier values are back.');

    expect($this->note->fresh()->title)->toBe('First')
        ->and(ActivityLog::query()->where('model_type', HistoryNote::class)->where('action', 'updated')->count())->toBe(2)
        ->and($page->get('record')['title'])->toBe('First');
});

test('an entry of another record cannot be reverted through this one', function () {
    $other = HistoryNote::create(['title' => 'Other']);

    Livewire::test('tardis::pages.bread.edit', ['slug' => 'notes', 'id' => $other->id])
        ->set('form.title', 'Other changed')->call('save');

    $foreign = ActivityLog::query()->where('model_id', (string) $other->id)->where('action', 'updated')->value('id');

    Livewire::test('tardis::pages.bread.read', ['slug' => 'notes', 'id' => $this->note->id])
        ->call('revert', $foreign)->assertNotFound();
});

test('saving over a change made after the form opened asks first', function () {
    $form = Livewire::test('tardis::pages.bread.edit', ['slug' => 'notes', 'id' => $this->note->id])
        ->set('form.title', 'Mine');

    // Someone else saves in the meantime.
    $this->travel(5)->seconds();
    $this->note->fresh()->update(['title' => 'Theirs']);

    $form->call('save')
        ->assertHasErrors('form')
        ->assertSee('Someone else changed this record')
        ->assertSee('Save anyway');

    expect($this->note->fresh()->title)->toBe('Theirs');

    $form->call('save')->assertHasNoErrors();

    expect($this->note->fresh()->title)->toBe('Mine');
});

test('an untouched record saves without a warning', function () {
    Livewire::test('tardis::pages.bread.edit', ['slug' => 'notes', 'id' => $this->note->id])
        ->set('form.status', 'published')
        ->call('save')->assertHasNoErrors();

    expect($this->note->fresh()->status)->toBe('published');
});
