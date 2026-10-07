<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tardis\Bread\BreadDefinition;
use Tardis\Bread\BreadQuery;
use Tardis\Bread\Sources\JsonBreadSource;

class ListingArticle extends Model
{
    use SoftDeletes;

    protected $table = 'listing_articles';

    protected $guarded = [];

    public function comments()
    {
        return $this->hasMany(ListingComment::class, 'listing_article_id');
    }

    public function scopeFresh($query)
    {
        return $query->where('views', '<', 5);
    }
}

class ListingComment extends Model
{
    protected $table = 'listing_comments';

    protected $guarded = [];
}

function listingBread(array $overrides = []): array
{
    return $overrides + [
        'slug' => 'articles',
        'model' => ListingArticle::class,
        'name' => 'Article',
        'name_plural' => 'Articles',
        'soft_delete' => true,
        'fields' => [
            ['name' => 'title', 'type' => 'text', 'label' => 'Title', 'browse' => true, 'searchable' => true],
            ['name' => 'body', 'type' => 'textarea', 'label' => 'Body', 'browse' => true, 'searchable' => true, 'orderable' => false],
            ['name' => 'views', 'type' => 'number', 'label' => 'Views', 'browse' => true],
            ['name' => 'tags', 'type' => 'belongs_to_many', 'label' => 'Tags', 'browse' => true, 'searchable' => true],
            ['name' => 'comments', 'type' => 'has_many', 'label' => 'Comments', 'browse' => true, 'relation' => 'comments', 'label_column' => 'body', 'display_limit' => 3, 'link_to' => 'comments'],
            ['name' => 'secret', 'type' => 'text', 'label' => 'Secret', 'browse' => false],
        ],
        'relationships' => [],
    ];
}

beforeEach(function () {
    Schema::create('listing_articles', function (Blueprint $table) {
        $table->id();
        $table->string('title');
        $table->text('body')->nullable();
        $table->integer('views')->default(0);
        $table->timestamps();
        $table->softDeletes();
    });

    Schema::create('listing_comments', function (Blueprint $table) {
        $table->id();
        $table->foreignId('listing_article_id');
        $table->string('body');
        $table->timestamps();
    });

    ListingArticle::create(['title' => 'Alpha', 'body' => 'first post', 'views' => 5]);
    ListingArticle::create(['title' => 'Beta', 'body' => 'second', 'views' => 20]);
    ListingArticle::create(['title' => 'Gamma 100%', 'body' => 'third', 'views' => 1]);
    ListingArticle::create(['title' => 'Gone', 'body' => 'x', 'views' => 9])->delete();

    $this->path = sys_get_temp_dir().'/tardis-listing-'.uniqid();
    app()->instance(JsonBreadSource::class, new JsonBreadSource($this->path));
});

afterEach(fn () => File::deleteDirectory($this->path));

function listingTitles(BreadQuery $query, array $args = []): array
{
    return $query->build(...$args)->pluck('title')->all();
}

test('only searchable fields with a column are searched, and the legacy search key is the fallback', function () {
    $query = new BreadQuery(BreadDefinition::fromArray(listingBread()));

    expect($query->searchable())->toBe(['title', 'body']);

    $legacy = listingBread(['search_key' => 'title']);
    $legacy['fields'] = array_map(fn ($f) => array_diff_key($f, ['searchable' => 1]), $legacy['fields']);

    expect((new BreadQuery(BreadDefinition::fromArray($legacy)))->searchable())->toBe(['title']);
});

test('search matches any searchable column and treats wildcards literally', function () {
    $query = new BreadQuery(BreadDefinition::fromArray(listingBread()));

    expect(listingTitles($query, ['second']))->toBe(['Beta'])
        ->and(listingTitles($query, ['100%']))->toBe(['Gamma 100%'])
        ->and(listingTitles($query, ['%']))->toBe(['Gamma 100%']);
});

test('sorting is limited to visible orderable columns', function () {
    $query = new BreadQuery(BreadDefinition::fromArray(listingBread()));

    expect($query->orderable())->toBe(['title', 'views'])
        ->and(listingTitles($query, ['', 'views', 'desc']))->toBe(['Beta', 'Alpha', 'Gamma 100%'])
        ->and(listingTitles($query, ['', 'views', 'asc']))->toBe(['Gamma 100%', 'Alpha', 'Beta']);

    // an unknown or hidden column is ignored instead of reaching the SQL
    expect(listingTitles($query, ['', 'secret; drop table x', 'asc']))->toHaveCount(3)
        ->and(listingTitles($query, ['', 'secret', 'asc']))->toHaveCount(3);
});

test('soft-deleted records can be hidden, included or isolated', function () {
    $query = new BreadQuery(BreadDefinition::fromArray(listingBread()));

    expect($query->supportsTrashed())->toBeTrue()
        ->and(listingTitles($query, ['', null, 'asc', 'without']))->toHaveCount(3)
        ->and(listingTitles($query, ['', null, 'asc', 'with']))->toHaveCount(4)
        ->and(listingTitles($query, ['', null, 'asc', 'only']))->toBe(['Gone']);
});

test('a BREAD that does not soft delete ignores the trashed filter', function () {
    $query = new BreadQuery(BreadDefinition::fromArray(listingBread(['soft_delete' => false])));

    expect($query->supportsTrashed())->toBeFalse()
        ->and(listingTitles($query, ['', null, 'asc', 'only']))->toHaveCount(3);
});

test('the page size is limited to the offered choices', function () {
    $query = new BreadQuery(BreadDefinition::fromArray(listingBread()));

    expect($query->listing(perPage: 10)->rows->perPage())->toBe(10)
        ->and($query->listing(perPage: 100000)->rows->perPage())->toBe(15);
});

test('the browse page sorts on a header click and flips the direction', function () {
    (new JsonBreadSource($this->path))->save(listingBread());

    Livewire::test('tardis::pages.bread.index', ['slug' => 'articles'])
        ->call('sortBy', 'views')
        ->assertSet('sort', 'views')->assertSet('direction', 'asc')
        ->assertSeeInOrder(['Gamma 100%', 'Alpha', 'Beta'])
        ->call('sortBy', 'views')
        ->assertSet('direction', 'desc')
        ->assertSeeInOrder(['Beta', 'Alpha', 'Gamma 100%'])
        ->call('sortBy', 'secret')
        ->assertSet('sort', 'views');
});

test('the browse page restores and permanently deletes soft-deleted records', function () {
    (new JsonBreadSource($this->path))->save(listingBread());
    $gone = ListingArticle::withTrashed()->where('title', 'Gone')->first();

    $page = Livewire::test('tardis::pages.bread.index', ['slug' => 'articles'])
        ->set('trashed', 'only')
        ->assertSee('Gone')
        ->call('restore', $gone->id);

    expect(ListingArticle::find($gone->id))->not->toBeNull();

    $page->call('delete', $gone->id);
    $page->set('trashed', 'only')->call('forceDelete', $gone->id);

    expect(ListingArticle::withTrashed()->find($gone->id))->toBeNull();
});

test('named filters apply a column comparison or a model scope', function () {
    $bread = listingBread();
    $bread['layout'] = ['options' => ['filters' => [
        ['key' => 'popular', 'label' => 'Popular', 'column' => 'views', 'operator' => '>', 'value' => 4],
        ['key' => 'fresh', 'label' => 'Fresh', 'scope' => 'fresh'],
    ]]];
    $query = new BreadQuery(BreadDefinition::fromArray($bread));

    expect($query->namedFilters())->toHaveKeys(['popular', 'fresh'])
        ->and(listingTitles($query, ['', null, 'asc', 'without', [], ['popular' => true]]))->toBe(['Alpha', 'Beta'])
        ->and(listingTitles($query, ['', null, 'asc', 'without', [], ['fresh']]))->toBe(['Gamma 100%']);
});

test('named filters ignore unknown names, unknown columns and bad operators', function () {
    $bread = listingBread();
    $bread['layout'] = ['options' => ['filters' => [
        ['key' => 'equal', 'column' => 'views', 'operator' => 'drop', 'value' => 5],
        ['key' => 'injected', 'column' => 'title); drop table x; --', 'operator' => '=', 'value' => 'x'],
    ]]];
    $query = new BreadQuery(BreadDefinition::fromArray($bread));

    // `drop` falls back to `=`, so only Alpha (views 5) matches; the unknown
    // column and the missing filter never reach the query.
    expect(listingTitles($query, ['', null, 'asc', 'without', [], ['equal' => true, 'injected' => true, 'missing' => true]]))
        ->toBe(['Alpha']);
});

test('inline column search only honours searchable columns', function () {
    $query = new BreadQuery(BreadDefinition::fromArray(listingBread()));

    expect(listingTitles($query, ['', null, 'asc', 'without', ['title' => 'alp']]))->toBe(['Alpha'])
        ->and(listingTitles($query, ['', null, 'asc', 'without', ['body' => 'second']]))->toBe(['Beta'])
        ->and(listingTitles($query, ['', null, 'asc', 'without', ['secret' => 'x']]))->toHaveCount(3)
        ->and(listingTitles($query, ['', null, 'asc', 'without', ['title' => '%']]))->toBe(['Gamma 100%']);
});

test('relation columns are eager loaded', function () {
    $query = new BreadQuery(BreadDefinition::fromArray(listingBread()));

    expect($query->eagerLoads())->toBe(['comments'])
        ->and($query->build()->getEagerLoads())->toHaveKey('comments');
});

test('the browse page toggles named filters declared by the layout', function () {
    $bread = listingBread();
    $bread['layout'] = ['options' => ['filters' => [
        ['key' => 'popular', 'label' => 'Popular', 'column' => 'views', 'operator' => '>', 'value' => 4],
    ]]];
    (new JsonBreadSource($this->path))->save($bread);

    Livewire::test('tardis::pages.bread.index', ['slug' => 'articles'])
        ->assertSee('Popular')
        ->call('toggleFilter', 'popular')
        ->assertSet('filters.popular', true)
        ->assertSeeInOrder(['Alpha', 'Beta'])
        ->assertDontSee('Gamma 100%')
        ->call('toggleFilter', 'popular')
        ->assertSet('filters.popular', false)
        ->assertSee('Gamma 100%')
        ->call('toggleFilter', 'does-not-exist')
        ->assertSet('filters', ['popular' => false]);
});

test('the browse page shows related labels with a "+n more" overflow and links', function () {
    $alpha = ListingArticle::where('title', 'Alpha')->first();
    foreach (range(1, 5) as $i) {
        ListingComment::create(['listing_article_id' => $alpha->id, 'body' => "Comment {$i}"]);
    }

    (new JsonBreadSource($this->path))->save(listingBread());

    Livewire::test('tardis::pages.bread.index', ['slug' => 'articles'])
        ->assertSeeInOrder(['Comment 1', 'Comment 2', 'Comment 3'])
        ->assertSee('+2')
        ->assertDontSee('Comment 4')
        ->assertSee('admin/comments/'.$alpha->id, false);
});

test('clearFilters resets every listing refinement', function () {
    (new JsonBreadSource($this->path))->save(listingBread());

    Livewire::test('tardis::pages.bread.index', ['slug' => 'articles'])
        ->set('search', 'Beta')
        ->set('columnSearch.title', 'Beta')
        ->set('trashed', 'only')
        ->call('sortBy', 'views')
        ->assertSet('search', 'Beta')
        ->assertSet('hasFilters', true)
        ->call('clearFilters')
        ->assertSet('search', '')
        ->assertSet('columnSearch', [])
        ->assertSet('filters', [])
        ->assertSet('trashed', 'without')
        ->assertSet('sort', '')
        ->assertSet('direction', 'asc')
        ->assertSet('hasFilters', false)
        ->assertSee('Alpha');
});
