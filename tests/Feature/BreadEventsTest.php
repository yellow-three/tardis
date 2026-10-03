<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tardis\Bread\BreadManager;
use Tardis\Bread\Sources\JsonBreadSource;
use Tardis\Events\BreadRecordCreated;
use Tardis\Events\BreadRecordDeleted;
use Tardis\Events\BreadRecordUpdated;
use Tardis\Events\BreadRemoved;
use Tardis\Events\BreadSaved;
use Tardis\Models\Permission;

class EventThing extends Model
{
    protected $table = 'event_things';

    protected $guarded = [];

    protected $hidden = ['api_token'];

    public $timestamps = false;
}

class EventUser extends Authenticatable
{
    protected $table = 'users';

    protected $guarded = [];
}

function thingDefinition(array $extra = []): array
{
    return $extra + [
        'slug' => 'things', 'model' => EventThing::class, 'name' => 'Thing', 'name_plural' => 'Things',
        'fields' => [
            ['name' => 'title', 'type' => 'text', 'label' => 'Title', 'browse' => true, 'read' => true, 'edit' => true, 'add' => true, 'validation' => ['required']],
            ['name' => 'secret', 'type' => 'password', 'label' => 'Secret', 'browse' => false, 'read' => false, 'edit' => true, 'add' => true],
            ['name' => 'api_token', 'type' => 'text', 'label' => 'Token', 'browse' => false, 'read' => false, 'edit' => true, 'add' => true],
        ],
    ];
}

beforeEach(function () {
    Schema::dropIfExists('users');
    Schema::create('users', function (Blueprint $table) {
        $table->id();
        $table->string('name')->nullable();
        $table->string('email')->nullable();
        $table->string('password')->nullable();
        $table->rememberToken();
        $table->timestamps();
    });
    Schema::create('event_things', function (Blueprint $table) {
        $table->id();
        $table->string('title');
        $table->string('secret')->nullable();
        $table->string('api_token')->nullable();
    });

    $this->artisan('migrate');

    $this->eventsPath = sys_get_temp_dir().'/tardis-bread-events-'.uniqid();
    app()->instance(JsonBreadSource::class, new JsonBreadSource($this->eventsPath));

    $this->actingAs($this->user = EventUser::create(['name' => 'Ada', 'email' => 'ada@example.test', 'password' => 'x']));
});

afterEach(function () {
    Schema::dropIfExists('event_things');
    Schema::dropIfExists('users');
    File::deleteDirectory($this->eventsPath);
});

test('saving a definition dispatches BreadSaved and says whether it was new', function () {
    Event::fake([BreadSaved::class]);

    $manager = app(BreadManager::class);
    $manager->save(thingDefinition());
    $manager->save(thingDefinition());

    Event::assertDispatchedTimes(BreadSaved::class, 2);
    Event::assertDispatched(BreadSaved::class, fn (BreadSaved $event) => $event->definition->slug === 'things' && $event->wasNew === true);
    Event::assertDispatched(BreadSaved::class, fn (BreadSaved $event) => $event->wasNew === false);
});

test('deleting a definition dispatches BreadRemoved', function () {
    $manager = app(BreadManager::class);
    $manager->save(thingDefinition());

    Event::fake([BreadRemoved::class]);

    $manager->delete('things');

    Event::assertDispatched(BreadRemoved::class, fn (BreadRemoved $event) => $event->slug === 'things');
});

test('permissions are provisioned by a listener on BreadSaved, not by the manager', function () {
    Event::fake([BreadSaved::class]);

    app(BreadManager::class)->save(thingDefinition());

    expect(Permission::count())->toBe(0);

    Event::assertDispatched(BreadSaved::class);
});

test('creating, editing and deleting a record dispatch the record events', function () {
    app(BreadManager::class)->save(thingDefinition());
    Event::fake([BreadRecordCreated::class, BreadRecordUpdated::class, BreadRecordDeleted::class]);

    Livewire::test('tardis::pages.bread.create', ['slug' => 'things'])
        ->set('form.title', 'First')
        ->call('save');

    $thing = EventThing::first();

    Event::assertDispatched(BreadRecordCreated::class, fn ($event) => $event->slug === 'things' && $event->model->is($thing));

    Livewire::test('tardis::pages.bread.edit', ['slug' => 'things', 'id' => $thing->id])
        ->set('form.title', 'Second')
        ->call('save');

    Event::assertDispatched(BreadRecordUpdated::class, fn ($event) => $event->old['title'] === 'First' && $event->new['title'] === 'Second');

    Livewire::test('tardis::pages.bread.index', ['slug' => 'things'])->call('delete', $thing->id);

    Event::assertDispatched(BreadRecordDeleted::class, fn ($event) => $event->slug === 'things');
});

test('record events are written to the activity log with the acting user', function () {
    app(BreadManager::class)->save(thingDefinition());

    Livewire::test('tardis::pages.bread.create', ['slug' => 'things'])->set('form.title', 'First')->call('save');
    $thing = EventThing::first();
    Livewire::test('tardis::pages.bread.edit', ['slug' => 'things', 'id' => $thing->id])->set('form.title', 'Second')->call('save');
    Livewire::test('tardis::pages.bread.index', ['slug' => 'things'])->call('delete', $thing->id);

    $rows = DB::table('activity_logs')->orderBy('id')->get();

    expect($rows->pluck('action')->all())->toBe(['created', 'updated', 'deleted'])
        ->and($rows->pluck('user_id')->unique()->all())->toBe([$this->user->id])
        ->and($rows->pluck('model_type')->unique()->all())->toBe([EventThing::class])
        ->and(json_decode($rows[1]->old_values, true))->toBe(['title' => 'First'])
        ->and(json_decode($rows[1]->new_values, true))->toBe(['title' => 'Second']);
});

test('passwords and hidden attributes never reach the activity log', function () {
    app(BreadManager::class)->save(thingDefinition());

    Livewire::test('tardis::pages.bread.create', ['slug' => 'things'])
        ->set('form.title', 'Safe')
        ->set('form.secret', 'hunter2-secret')
        ->set('form.api_token', 'token-abc-123')
        ->call('save');

    $created = DB::table('activity_logs')->where('action', 'created')->first();

    expect($created->new_values)->toContain('Safe')
        ->not->toContain('hunter2')
        ->not->toContain('token-abc-123');
});

test('activity logging can be switched off or limited to some events', function () {
    app(BreadManager::class)->save(thingDefinition());

    config()->set('tardis.activity_log.enabled', false);
    Livewire::test('tardis::pages.bread.create', ['slug' => 'things'])->set('form.title', 'Quiet')->call('save');

    expect(DB::table('activity_logs')->count())->toBe(0);

    config()->set('tardis.activity_log.enabled', true);
    config()->set('tardis.activity_log.log_events', ['deleted']);

    Livewire::test('tardis::pages.bread.create', ['slug' => 'things'])->set('form.title', 'Loud')->call('save');

    expect(DB::table('activity_logs')->count())->toBe(0);

    $thing = EventThing::where('title', 'Loud')->first();
    Livewire::test('tardis::pages.bread.index', ['slug' => 'things'])->call('delete', $thing->id);

    expect(DB::table('activity_logs')->pluck('action')->all())->toBe(['deleted']);
});

test('an admin page request announces itself with the tardis.page event', function () {
    $seen = [];
    Event::listen('tardis.page', function ($request) use (&$seen) {
        $seen[] = $request->path();
    });

    $this->get('/admin/dashboard')->assertOk();

    expect($seen)->toBe(['admin/dashboard']);
});
