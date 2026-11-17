<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tardis\Bread\BreadManager;
use Tardis\Bread\Sources\JsonBreadSource;
use Tardis\Contracts\Plugins\AuthorizationPlugin;
use Tardis\Manager\MenuManager;
use Tardis\Manager\PluginManager;
use Tardis\Models\Permission;

class PolicyKeyPlugin implements AuthorizationPlugin
{
    /** @var array<int, string> */
    public static array $allowed = [];

    public function name(): string
    {
        return 'policy-key';
    }

    public function can(string $ability, mixed $model = null): bool
    {
        return in_array($ability, static::$allowed, true);
    }

    public function authorize(string $ability, mixed $model = null): void
    {
        $this->can($ability, $model) || abort(403);
    }
}

function allowPolicyKeys(array $abilities): void
{
    PolicyKeyPlugin::$allowed = $abilities;

    $manager = app(PluginManager::class);
    $manager->register('policy-key', PolicyKeyPlugin::class);
    $manager->enableByDefault('policy-key');
}

class PolicyScopeModel extends Model
{
    protected $table = 'policy_scope_things';

    protected $guarded = [];

    public $timestamps = false;

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('published', true);
    }
}

beforeEach(function () {
    Schema::create('policy_scope_things', function (Blueprint $table) {
        $table->id();
        $table->string('title');
        $table->boolean('published')->default(false);
    });

    $this->scopePath = sys_get_temp_dir().'/tardis-policy-scope-'.uniqid();
    app()->instance(JsonBreadSource::class, new JsonBreadSource($this->scopePath));

    app(BreadManager::class)->save([
        'slug' => 'things',
        'model' => PolicyScopeModel::class,
        'name' => 'Thing',
        'name_plural' => 'Things',
        'scope' => 'published',
        'policy' => 'articles',
        'fields' => [
            ['name' => 'title', 'type' => 'text', 'label' => 'Title', 'browse' => true, 'read' => true, 'edit' => true, 'add' => true, 'validation' => ['required']],
        ],
    ]);

    PolicyScopeModel::create(['title' => 'Visible', 'published' => true]);
    PolicyScopeModel::create(['title' => 'Hidden draft', 'published' => false]);
});

afterEach(function () {
    Schema::dropIfExists('policy_scope_things');
    File::deleteDirectory($this->scopePath);
});

test('the listing applies the definition scope', function () {
    Livewire::test('tardis::pages.bread.index', ['slug' => 'things'])
        ->assertSee('Visible')
        ->assertDontSee('Hidden draft');
});

test('a record outside the scope cannot be read or edited', function () {
    $hidden = PolicyScopeModel::where('title', 'Hidden draft')->first();

    // findOrFail on the scoped query: a hidden record is "not found", the same
    // answer a nonexistent id gets. Livewire 4.3 rethrows it from mount, 4.4+ renders
    // it as the 404 response, so accept either.
    foreach (['read', 'edit'] as $page) {
        try {
            $component = Livewire::test("tardis::pages.bread.{$page}", ['slug' => 'things', 'id' => $hidden->id]);
        } catch (ModelNotFoundException) {
            continue;
        }

        $component->assertNotFound();
    }
});

test('a record outside the scope cannot be deleted from the listing', function () {
    $hidden = PolicyScopeModel::where('title', 'Hidden draft')->first();

    try {
        Livewire::test('tardis::pages.bread.index', ['slug' => 'things'])
            ->call('delete', $hidden->id)
            ->assertNotFound();
    } catch (ModelNotFoundException) {
        // Livewire 4.3 rethrows it; 4.4+ renders the 404 response.
    }

    expect(PolicyScopeModel::count())->toBe(2);
});

test('a record inside the scope still opens', function () {
    $visible = PolicyScopeModel::where('title', 'Visible')->first();

    Livewire::test('tardis::pages.bread.read', ['slug' => 'things', 'id' => $visible->id])->assertOk()->assertSee('Visible');
});

test('saving a definition provisions permissions under its policy key', function () {
    $this->artisan('migrate');

    app(BreadManager::class)->save([
        'slug' => 'things', 'model' => PolicyScopeModel::class, 'name' => 'Thing', 'name_plural' => 'Things',
        'policy' => 'articles', 'fields' => [],
    ]);

    $slugs = Permission::where('group', 'BREAD')->pluck('slug')->all();

    expect($slugs)->toContain('browse articles', 'edit articles')->not->toContain('browse things');
});

test('pages ask for abilities under the definition policy, not the slug', function () {
    allowPolicyKeys(['browse articles']);

    Livewire::test('tardis::pages.bread.index', ['slug' => 'things'])->assertOk();

    allowPolicyKeys(['browse things']);

    Livewire::test('tardis::pages.bread.index', ['slug' => 'things'])->assertForbidden();
});

test('the sidebar entry follows the policy key too', function () {
    allowPolicyKeys(['browse articles']);
    app()->forgetInstance(MenuManager::class);

    $menu = app(MenuManager::class);
    $menu->collectFromPlugins(app(PluginManager::class));

    expect($menu->all()->pluck('title')->all())->toContain('Things');

    allowPolicyKeys(['browse things']);
    app()->forgetInstance(MenuManager::class);

    $menu = app(MenuManager::class);
    $menu->collectFromPlugins(app(PluginManager::class));

    expect($menu->all()->pluck('title')->all())->not->toContain('Things');
});
