<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tardis\Bread\Action;
use Tardis\Bread\Actions\ForceDeleteAction;
use Tardis\Bread\Sources\JsonBreadSource;
use Tardis\Manager\ActionManager;

class ActionItem extends Model
{
    protected $table = 'action_items';

    protected $guarded = [];
}

class PublishAction extends Action
{
    public ?string $permission = 'edit';

    public bool $bulk = true;

    public function handle(Model $record, string $slug): mixed
    {
        return $record->update(['status' => 'published']);
    }
}

class ArchiveAction extends Action
{
    public function handle(Model $record, string $slug): mixed
    {
        return $record->update(['status' => 'archived']);
    }
}

beforeEach(function () {
    Schema::create('action_items', function (Blueprint $table) {
        $table->id();
        $table->string('title');
        $table->string('status')->default('draft');
        $table->timestamps();
    });

    $this->path = sys_get_temp_dir().'/tardis-actions-'.uniqid();
    app()->instance(JsonBreadSource::class, new JsonBreadSource($this->path));
    app()->instance(ActionManager::class, new ActionManager);

    (new JsonBreadSource($this->path))->save([
        'slug' => 'items',
        'model' => ActionItem::class,
        'name' => 'Item',
        'name_plural' => 'Items',
        'fields' => [['name' => 'title', 'type' => 'text', 'label' => 'Title', 'browse' => true]],
        'relationships' => [],
    ]);

    $this->items = collect(['A', 'B', 'C'])->map(fn ($t) => ActionItem::create(['title' => $t]));
});

afterEach(fn () => File::deleteDirectory($this->path));

test('an action name is derived from its class', function () {
    expect((new PublishAction)->name())->toBe('publish')
        ->and((new ForceDeleteAction)->name())->toBe('force-delete');
});

test('every BREAD gets the stock actions and a slug can add its own', function () {
    $manager = new ActionManager;
    $manager->add('items', PublishAction::class);

    expect($manager->for('items')->keys()->all())->toBe(['delete', 'restore', 'force-delete', 'publish'])
        ->and($manager->for('other')->keys()->all())->toBe(['delete', 'restore', 'force-delete']);
});

test('replace swaps a named action and manipulate can drop one', function () {
    $manager = new ActionManager;
    $manager->replace('items', 'delete', ArchiveAction::class);
    $manager->manipulate('items', fn (Collection $actions) => $actions->reject(fn (Action $a) => $a->name() === 'restore'));

    $names = $manager->for('items')->map(fn (Action $a) => $a::class)->all();

    expect($names)->toHaveKey('archive')->not->toHaveKey('restore')->not->toHaveKey('delete');
});

test('registering something that is not an action is refused', function () {
    (new ActionManager)->add('items', stdClass::class);
})->throws(InvalidArgumentException::class);

test('a custom row action runs through the browse page', function () {
    app(ActionManager::class)->add('items', ArchiveAction::class);

    Livewire::test('tardis::pages.bread.index', ['slug' => 'items'])
        ->call('runAction', 'archive', $this->items[0]->id);

    expect($this->items[0]->refresh()->status)->toBe('archived');
});

test('an unknown action is a 404', function () {
    Livewire::test('tardis::pages.bread.index', ['slug' => 'items'])
        ->call('runAction', 'nope', $this->items[0]->id)
        ->assertNotFound();
});

test('a bulk action touches only the ticked rows', function () {
    app(ActionManager::class)->add('items', PublishAction::class);

    Livewire::test('tardis::pages.bread.index', ['slug' => 'items'])
        ->set('selected', [$this->items[0]->id, $this->items[2]->id])
        ->call('runBulk', 'publish')
        ->assertSet('selected', []);

    expect(ActionItem::pluck('status', 'title')->all())->toBe(['A' => 'published', 'B' => 'draft', 'C' => 'published']);
});

test('a row action that is not bulk cannot be run in bulk', function () {
    app(ActionManager::class)->add('items', ArchiveAction::class);

    Livewire::test('tardis::pages.bread.index', ['slug' => 'items'])
        ->set('selected', [$this->items[0]->id])
        ->call('runBulk', 'archive')
        ->assertNotFound();
});

test('the stock delete action is a bulk action', function () {
    Livewire::test('tardis::pages.bread.index', ['slug' => 'items'])
        ->set('selected', [$this->items[1]->id])
        ->call('runBulk', 'delete');

    expect(ActionItem::count())->toBe(2);
});
