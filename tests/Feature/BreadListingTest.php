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
