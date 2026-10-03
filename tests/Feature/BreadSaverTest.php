<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Tardis\Bread\BreadSaver;
use Tardis\Bread\MissingColumnsException;
use Tardis\Events\BreadRecordCreated;
use Tardis\Events\BreadRecordUpdated;

class SaverNote extends Model
{
    protected $table = 'saver_notes';

    protected $guarded = [];

    protected $casts = ['pinned' => 'boolean'];
}

beforeEach(function () {
    Schema::create('saver_notes', function (Blueprint $table) {
        $table->id();
        $table->string('title');
        $table->boolean('pinned')->default(false);
        $table->timestamps();
    });

    $this->fields = [
        ['name' => 'title', 'type' => 'text'],
        ['name' => 'pinned', 'type' => 'toggle'],
    ];
});

test('create stores the transformed form and announces the record', function () {
    Event::fake([BreadRecordCreated::class]);

    $note = app(BreadSaver::class)->create('notes', SaverNote::class, $this->fields, ['title' => 'Hi', 'pinned' => 1]);

    expect($note->exists)->toBeTrue()
        ->and($note->title)->toBe('Hi')
        ->and($note->pinned)->toBeTrue();

    Event::assertDispatched(BreadRecordCreated::class);
});

test('a column the table needs but the form left blank is reported by name', function () {
    app(BreadSaver::class)->create('notes', SaverNote::class, $this->fields, ['title' => '']);
})->throws(MissingColumnsException::class, 'title');

test('update returns only what changed and announces it once', function () {
    $note = SaverNote::create(['title' => 'Old'])->refresh();
    Event::fake([BreadRecordUpdated::class]);

    $changes = app(BreadSaver::class)->update('notes', $note, $this->fields, ['title' => 'New', 'pinned' => false]);

    expect($changes)->toHaveKey('title')->not->toHaveKey('pinned');
    Event::assertDispatchedTimes(BreadRecordUpdated::class, 1);

    $none = app(BreadSaver::class)->update('notes', SaverNote::find($note->id), $this->fields, ['title' => 'New', 'pinned' => false]);
    expect($none)->toBe([]);
    Event::assertDispatchedTimes(BreadRecordUpdated::class, 1);
});
