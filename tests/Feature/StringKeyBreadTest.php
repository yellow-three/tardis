<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tardis\Bread\Sources\JsonBreadSource;
use Tardis\Models\ActivityLog;

class StringKeyTicket extends Model
{
    use HasUuids;

    protected $table = 'string_key_tickets';

    protected $guarded = [];
}

beforeEach(function () {
    $this->artisan('migrate');

    Schema::create('string_key_tickets', function ($table) {
        $table->uuid('id')->primary();
        $table->string('title');
        $table->timestamps();
    });

    $this->breadPath = sys_get_temp_dir().'/tardis-string-key-'.uniqid();
    File::ensureDirectoryExists($this->breadPath);
    File::put($this->breadPath.'/tickets.json', json_encode([
        'slug' => 'tickets',
        'model' => StringKeyTicket::class,
        'name' => 'Ticket',
        'name_plural' => 'Tickets',
        'fields' => [['name' => 'title', 'type' => 'text', 'label' => 'Title', 'browse' => true]],
    ]));
    app()->instance(JsonBreadSource::class, new JsonBreadSource($this->breadPath));
});

afterEach(function () {
    File::deleteDirectory($this->breadPath);
    Schema::dropIfExists('string_key_tickets');
});

test('row actions on a UUID-keyed BREAD pass the key as a string', function () {
    $ticket = StringKeyTicket::create(['title' => 'Printer on fire']);

    Livewire::test('tardis::pages.bread.index', ['slug' => 'tickets'])
        ->assertSeeHtml("runAction('delete', '".$ticket->id."')")
        ->call('runAction', 'delete', $ticket->id);

    expect(StringKeyTicket::query()->whereKey($ticket->id)->exists())->toBeFalse();
});

test('changes to a UUID-keyed record reach the activity log', function () {
    Livewire::test('tardis::pages.bread.create', ['slug' => 'tickets'])
        ->set('form.title', 'Printer on fire')
        ->call('save')
        ->assertHasNoErrors();

    $ticket = StringKeyTicket::query()->firstOrFail();
    $entry = ActivityLog::query()->where('model_type', StringKeyTicket::class)->first();

    expect($entry)->not->toBeNull()
        ->and((string) $entry->model_id)->toBe($ticket->id);
});
