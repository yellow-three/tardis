<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tardis\Bread\Sources\JsonBreadSource;
use Tardis\Contracts\Plugins\AuthorizationPlugin;
use Tardis\Plugins\PluginManager;

class SearchScopeArticle extends Model
{
    protected $table = 'search_scope_articles';

    protected $guarded = [];

    public function scopePublished(Builder $query): void
    {
        $query->where('published', true);
    }
}

class SearchScopeUser extends Authenticatable
{
    protected $table = 'users';

    protected $guarded = [];
}

class SearchScopeAuthorization implements AuthorizationPlugin
{
    /** @var list<string> */
    public static array $allowed = [];

    public function name(): string
    {
        return 'search-scope-authz';
    }

    public function can(string $ability, mixed $model = null): bool
    {
        return in_array($ability, static::$allowed, true);
    }

    public function authorize(string $ability, mixed $model = null): void
    {
        abort_unless($this->can($ability, $model), 403);
    }
}

beforeEach(function () {
    Schema::create('search_scope_articles', function ($table) {
        $table->id();
        $table->string('title');
        $table->boolean('published')->default(false);
        $table->timestamps();
    });
    Schema::dropIfExists('users');
    Schema::create('users', function ($table) {
        $table->id();
        $table->string('name')->nullable();
        $table->timestamps();
    });

    $this->breadPath = sys_get_temp_dir().'/tardis-search-scope-'.uniqid();
    File::ensureDirectoryExists($this->breadPath);
    File::put($this->breadPath.'/articles.json', json_encode([
        'slug' => 'articles',
        'model' => SearchScopeArticle::class,
        'name' => 'Article',
        'name_plural' => 'Articles',
        'search_key' => 'title',
        'scope' => 'published',
        'fields' => [['name' => 'title', 'type' => 'text']],
    ]));
    app()->instance(JsonBreadSource::class, new JsonBreadSource($this->breadPath));

    SearchScopeArticle::create(['title' => 'Moon landing (public)', 'published' => true]);
    SearchScopeArticle::create(['title' => 'Moon base (draft)', 'published' => false]);

    $manager = app(PluginManager::class);
    $manager->register('search-scope-authz', SearchScopeAuthorization::class);
    $manager->enable('search-scope-authz');

    $this->actingAs(SearchScopeUser::create(['name' => 'Ada']));
});

afterEach(function () {
    File::deleteDirectory($this->breadPath);
    Schema::dropIfExists('search_scope_articles');
});

test('search only finds records inside the BREAD scope', function () {
    SearchScopeAuthorization::$allowed = ['browse articles', 'read articles', 'edit articles'];

    $titles = collect(Livewire::test('tardis::pages.search')->set('query', 'Moon')->get('results'))
        ->flatMap(fn ($group) => array_column($group['items'], 'title'))
        ->all();

    expect($titles)->toBe(['Moon landing (public)']);
});

test('search links to the page the user may open', function (array $allowed, string $route) {
    SearchScopeAuthorization::$allowed = $allowed;
    $article = SearchScopeArticle::where('published', true)->first();

    $url = Livewire::test('tardis::pages.search')->set('query', 'Moon')->get('results')[0]['items'][0]['url'];

    expect($url)->toBe(route($route, ['slug' => 'articles', 'id' => $article->id]));
})->with([
    'edit when allowed' => [['browse articles', 'read articles', 'edit articles'], 'tardis.bread.edit'],
    'read otherwise' => [['browse articles', 'read articles'], 'tardis.bread.show'],
]);

test('with browse alone search links to the filtered listing', function () {
    SearchScopeAuthorization::$allowed = ['browse articles'];

    $url = Livewire::test('tardis::pages.search')->set('query', 'Moon')->get('results')[0]['items'][0]['url'];

    expect($url)->toBe(route('tardis.bread.index', ['slug' => 'articles', 'search' => 'Moon landing (public)']));
});

test('search treats % and _ in the query as text', function () {
    SearchScopeAuthorization::$allowed = ['browse articles', 'read articles'];

    expect(Livewire::test('tardis::pages.search')->set('query', '%%')->get('results'))->toBe([]);
});
