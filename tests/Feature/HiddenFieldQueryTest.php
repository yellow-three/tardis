<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tardis\Bread\BreadDefinition;
use Tardis\Bread\BreadQuery;
use Tardis\Bread\Sources\JsonBreadSource;
use Tardis\Contracts\Plugins\AuthorizationPlugin;
use Tardis\Http\Controllers\Api\BreadApiController;
use Tardis\Plugins\PluginManager;

class HiddenQueryReport extends Model
{
    protected $table = 'hidden_query_reports';

    protected $guarded = [];
}

/** Grants the abilities in $allowed, nothing else. */
class HiddenQueryAuthorization implements AuthorizationPlugin
{
    /** @var list<string> */
    public static array $allowed = [];

    public function name(): string
    {
        return 'hidden-query-authz';
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

function allowHiddenQueryAbilities(array $abilities): void
{
    HiddenQueryAuthorization::$allowed = $abilities;
}

function hiddenQueryBread(array $overrides = []): array
{
    return $overrides + [
        'slug' => 'reports',
        'model' => HiddenQueryReport::class,
        'name' => 'Report',
        'name_plural' => 'Reports',
        'order_column' => 'title',
        'order_direction' => 'asc',
        'fields' => [
            ['name' => 'title', 'type' => 'text', 'label' => 'Title', 'searchable' => true],
            ['name' => 'budget', 'type' => 'number', 'label' => 'Budget', 'ability' => 'see budgets', 'searchable' => true],
        ],
        'layout' => ['list' => ['title', ['name' => 'budget', 'label' => 'Budget']]],
    ];
}

beforeEach(function () {
    Schema::create('hidden_query_reports', function ($table) {
        $table->id();
        $table->string('title');
        $table->integer('budget')->nullable();
        $table->timestamps();
    });

    $this->breadPath = sys_get_temp_dir().'/tardis-hidden-query-'.uniqid();
    $source = new JsonBreadSource($this->breadPath);
    app()->instance(JsonBreadSource::class, $source);
    $source->save(hiddenQueryBread());

    $manager = app(PluginManager::class);
    $manager->register('hidden-query-authz', HiddenQueryAuthorization::class);
    $manager->enable('hidden-query-authz');

    allowHiddenQueryAbilities(['browse reports']);

    HiddenQueryReport::create(['title' => 'Alpha', 'budget' => 300]);
    HiddenQueryReport::create(['title' => 'Beta', 'budget' => 111]);
    HiddenQueryReport::create(['title' => 'Gamma', 'budget' => 200]);
});

afterEach(function () {
    HiddenQueryAuthorization::$allowed = [];
    File::deleteDirectory($this->breadPath);
});

test('global search ignores a hidden searchable field until its ability is granted', function () {
    Livewire::test('tardis::pages.bread.index', ['slug' => 'reports'])
        ->set('search', '111')
        ->assertDontSee(['Alpha', 'Beta', 'Gamma']);

    Livewire::test('tardis::pages.bread.index', ['slug' => 'reports'])
        ->set('search', 'Beta')
        ->assertSee('Beta')->assertDontSee('Alpha')->assertDontSee('Gamma');

    allowHiddenQueryAbilities(['browse reports', 'see budgets']);

    Livewire::test('tardis::pages.bread.index', ['slug' => 'reports'])
        ->set('search', '111')
        ->assertSee('Beta')->assertDontSee('Alpha')->assertDontSee('Gamma');
});

test('column search on a hidden field is ignored, while visible columns still filter', function () {
    Livewire::test('tardis::pages.bread.index', ['slug' => 'reports'])
        ->set('columnSearch.budget', '111')
        ->assertSee('Alpha')->assertSee('Beta')->assertSee('Gamma');

    Livewire::test('tardis::pages.bread.index', ['slug' => 'reports'])
        ->set('columnSearch.title', 'Beta')
        ->assertSee('Beta')->assertDontSee('Alpha')->assertDontSee('Gamma');

    allowHiddenQueryAbilities(['browse reports', 'see budgets']);

    Livewire::test('tardis::pages.bread.index', ['slug' => 'reports'])
        ->set('columnSearch.budget', '111')
        ->assertSee('Beta')->assertDontSee('Alpha')->assertDontSee('Gamma');
});

test('sorting by a hidden field falls back to the default order until its ability is granted', function () {
    Livewire::test('tardis::pages.bread.index', ['slug' => 'reports'])
        ->call('sortBy', 'budget')
        ->assertSet('sort', '')
        ->assertSeeInOrder(['Alpha', 'Beta', 'Gamma']);

    allowHiddenQueryAbilities(['browse reports', 'see budgets']);

    Livewire::test('tardis::pages.bread.index', ['slug' => 'reports'])
        ->call('sortBy', 'budget')
        ->assertSet('sort', 'budget')
        ->assertSeeInOrder(['Beta', 'Gamma', 'Alpha']);
});

test('BreadQuery itself ignores hidden fields in search, column search and sort', function () {
    $query = new BreadQuery(BreadDefinition::fromArray(hiddenQueryBread()));

    expect($query->searchable())->toBe(['title'])
        ->and($query->orderable())->toBe(['title'])
        ->and($query->build('111')->pluck('title')->all())->toBe([])
        ->and($query->build('', null, 'asc', 'without', ['budget' => '111'])->pluck('title')->all())->toBe(['Alpha', 'Beta', 'Gamma'])
        ->and($query->build(sort: 'budget')->pluck('title')->all())->toBe(['Alpha', 'Beta', 'Gamma']);

    allowHiddenQueryAbilities(['browse reports', 'see budgets']);

    expect($query->searchable())->toBe(['title', 'budget'])
        ->and($query->orderable())->toBe(['budget'])
        ->and($query->build('111')->pluck('title')->all())->toBe(['Beta'])
        ->and($query->build('', null, 'asc', 'without', ['budget' => '111'])->pluck('title')->all())->toBe(['Beta'])
        ->and($query->build(sort: 'budget')->pluck('title')->all())->toBe(['Beta', 'Gamma', 'Alpha']);
});

test('a legacy search key pointing at a hidden field is not searched', function () {
    $bread = hiddenQueryBread();
    $bread['fields'] = array_map(fn (array $field) => array_diff_key($field, ['searchable' => 1]), $bread['fields']);
    $bread['search_key'] = 'budget';
    (new JsonBreadSource($this->breadPath))->save($bread);

    $query = new BreadQuery(BreadDefinition::fromArray($bread));

    expect($query->searchable())->toBe([])
        ->and($query->build('111')->pluck('title')->all())->toBe(['Alpha', 'Beta', 'Gamma']);

    allowHiddenQueryAbilities(['browse reports', 'see budgets']);

    expect($query->searchable())->toBe(['budget'])
        ->and($query->build('111')->pluck('title')->all())->toBe(['Beta']);
});

test('the JSON API uses the same hidden-field guard through BreadQuery::build', function () {
    $api = app(BreadApiController::class);
    $request = Request::create('/api/tardis/reports?search=111');

    expect($api->index(clone $request, 'reports')->getData(true)['meta']['total'])->toBe(0);

    allowHiddenQueryAbilities(['browse reports', 'see budgets']);

    expect($api->index($request, 'reports')->getData(true)['meta']['total'])->toBe(1);
});
