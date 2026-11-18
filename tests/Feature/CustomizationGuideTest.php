<?php

declare(strict_types=1);

namespace Tests\Feature\CustomizationGuide;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tardis\Auth\SessionAuthenticationPlugin;
use Tardis\Bread\Action;
use Tardis\Bread\ActionManager;
use Tardis\Bread\BreadManager;
use Tardis\Facades\Tardis;
use Tardis\Formfields\Formfield;
use Tardis\Formfields\FormfieldManager;
use Tardis\Plugins\PluginManager;

// The examples of docs/CUSTOMIZATION.md, run as written.

class GuidePost extends Model
{
    protected $table = 'guide_posts';

    public $timestamps = false;

    protected $guarded = [];
}

class PublishAction extends Action
{
    public string $icon = 'check-circle';

    public ?string $permission = 'edit';

    public bool $bulk = true;

    public function getTitle(): string
    {
        return __('Publish');
    }

    public function appliesTo(Model $record): bool
    {
        return $record->status !== 'published';
    }

    public function handle(Model $record, string $slug): mixed
    {
        return $record->update(['status' => 'published']);
    }
}

class RatingField extends Formfield
{
    protected array $configurable = ['max'];

    public int $max = 5;

    public function type(): string
    {
        return 'rating';
    }

    public function render(): string
    {
        return 'tardis::formfields.number';
    }

    public function browse(mixed $value): mixed
    {
        return str_repeat('★', (int) $value);
    }

    public function store(mixed $value): mixed
    {
        return max(0, min($this->max, (int) $value));
    }

    protected function extraViewData(): array
    {
        return ['max' => $this->max];
    }
}

class StaffAuthentication extends SessionAuthenticationPlugin
{
    public function guard(): string
    {
        return 'staff';
    }

    public function name(): string
    {
        return 'staff-authentication';
    }
}

beforeEach(function () {
    Schema::create('guide_posts', function (Blueprint $table) {
        $table->id();
        $table->string('title');
        $table->string('status')->default('draft');
        $table->unsignedTinyInteger('rating')->default(0);
    });

    $this->path = sys_get_temp_dir().'/tardis-guide-'.uniqid();
    config()->set('tardis.bread.path', $this->path);
});

afterEach(fn () => File::deleteDirectory($this->path));

test('an action registered with Tardis::addAction is offered and runs', function () {
    Tardis::addAction('posts', PublishAction::class);

    $action = app(ActionManager::class)->find('posts', 'publish');
    $post = GuidePost::create(['title' => 'Hello']);

    expect($action)->toBeInstanceOf(PublishAction::class)
        ->and($action->appliesTo($post))->toBeTrue();

    $action->handle($post, 'posts');

    expect($post->fresh()->status)->toBe('published')
        ->and($action->appliesTo($post->fresh()))->toBeFalse();
});

test('a built-in action can be removed with manipulateActions', function () {
    Tardis::manipulateActions('posts', fn ($actions) => $actions->reject(fn ($action) => $action->name() === 'force-delete'));

    expect(app(ActionManager::class)->for('posts')->keys()->all())->not->toContain('force-delete')
        ->and(app(ActionManager::class)->for('pages')->keys()->all())->toContain('force-delete');
});

test('a registered field type works in a definition', function () {
    Tardis::formfields()->registerType('rating', RatingField::class);

    app(BreadManager::class)->save([
        'slug' => 'posts', 'model' => GuidePost::class, 'name' => 'Post', 'name_plural' => 'Posts',
        'fields' => [
            ['name' => 'title', 'type' => 'text'],
            ['name' => 'rating', 'type' => 'rating', 'max' => 3],
        ],
    ]);

    $field = app(FormfieldManager::class)->fields([['name' => 'rating', 'type' => 'rating', 'max' => 3]])[0];
    GuidePost::create(['title' => 'Hello', 'rating' => 2]);

    expect($field->store(9))->toBe(3)
        ->and($field->browse(2))->toBe('★★');

    Livewire::test('tardis::pages.bread.index', ['slug' => 'posts'])->assertSee('★★');
});

test('a listing column reads an accessor path', function () {
    app(BreadManager::class)->save([
        'slug' => 'posts', 'model' => GuidePost::class, 'name' => 'Post', 'name_plural' => 'Posts',
        'fields' => [['name' => 'shout', 'type' => 'text', 'accessor' => 'title', 'add' => false, 'edit' => false]],
    ]);
    GuidePost::create(['title' => 'Through the accessor']);

    Livewire::test('tardis::pages.bread.index', ['slug' => 'posts'])->assertSee('Through the accessor');
});

test('a component override is routed under the stock name plus the slug', function () {
    app(BreadManager::class)->save([
        'slug' => 'posts', 'model' => GuidePost::class, 'name' => 'Post', 'name_plural' => 'Posts',
        'fields' => [['name' => 'title', 'type' => 'text']],
        'components' => ['browse' => 'tardis::pages.dashboard'],
    ]);
    reloadAdminRoutes();

    $route = app('router')->getRoutes()->match(request()->create('/admin/posts'));

    expect($route->getName())->toBe('tardis.bread.index.posts');
});

test('an authentication plugin with another guard takes over when registered last', function () {
    $plugins = app(PluginManager::class);
    $plugins->register('staff-authentication', StaffAuthentication::class);
    $plugins->enableByDefault('staff-authentication');

    expect($plugins->authenticationPlugin())->toBeInstanceOf(StaffAuthentication::class)
        ->and($plugins->authenticationPlugin()->guard())->toBe('staff');
});
