<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Reader\XLSX\Reader;
use OpenSpout\Writer\XLSX\Writer;
use Tardis\Bread\BreadDefinition;
use Tardis\Bread\BreadQuery;
use Tardis\Bread\Sources\JsonBreadSource;
use Tardis\Models\ActivityLog;

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

test('restoring and permanently deleting are logged as their own actions', function () {
    $this->artisan('migrate');
    (new JsonBreadSource($this->path))->save(listingBread());
    $gone = ListingArticle::withTrashed()->where('title', 'Gone')->first();

    $page = Livewire::test('tardis::pages.bread.index', ['slug' => 'articles'])->call('restore', $gone->id);
    $page->call('delete', $gone->id)->set('trashed', 'only')->call('forceDelete', $gone->id);

    $actions = ActivityLog::query()->where('model_id', $gone->id)->orderBy('id')->pluck('action')->all();

    expect($actions)->toBe(['restored', 'deleted', 'force_deleted']);
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

test('the listing leaves the host query log as it found it', function () {
    $query = new BreadQuery(BreadDefinition::fromArray(listingBread()));

    // A host (or Debugbar/Telescope) that logs queries keeps logging.
    DB::enableQueryLog();
    $query->listing();
    expect(DB::logging())->toBeTrue();

    DB::disableQueryLog();
    $query->listing();
    expect(DB::logging())->toBeFalse();
});

test('only the listing own queries count towards its warnings', function () {
    DB::enableQueryLog();

    // Queries that ran earlier in the request are not the listing's doing.
    foreach (range(1, 20) as $i) {
        DB::select('select 1');
    }

    $listing = (new BreadQuery(BreadDefinition::fromArray(listingBread())))->listing();

    expect($listing->warnings)->toBe([]);

    DB::disableQueryLog();
});

test('sorting adds the key as a tie-breaker so pages do not overlap', function () {
    $query = new BreadQuery(BreadDefinition::fromArray(listingBread()));

    $orders = $query->build(sort: 'views', direction: 'desc')->getQuery()->orders;

    expect(array_column($orders, 'column'))->toBe(['views', 'listing_articles.id'])
        ->and(array_column($orders, 'direction'))->toBe(['desc', 'desc']);
});

test('a numeric column can be searched on every database', function () {
    $bread = listingBread();
    $bread['fields'] = array_map(fn (array $field) => $field['name'] === 'views' ? $field + ['searchable' => true] : $field, $bread['fields']);
    $query = new BreadQuery(BreadDefinition::fromArray($bread));

    // PostgreSQL has no LIKE for integers; the column is compared as text.
    expect(listingTitles($query, ['20']))->toBe(['Beta'])
        ->and(listingTitles($query, ['', null, 'asc', 'without', ['views' => '5']]))->toBe(['Alpha']);
});

test('the list exports what it shows as CSV, with the current search', function () {
    Carbon::setTestNow('2026-11-22 10:00:00');
    (new JsonBreadSource($this->path))->save(listingBread());
    ListingArticle::create(['title' => '=HYPERLINK("http://x")', 'body' => 'formula', 'views' => 3]);
    ListingArticle::find(1)->comments()->create(['body' => 'Nice']);

    $page = Livewire::test('tardis::pages.bread.index', ['slug' => 'articles']);
    $all = $page->call('export')->effects['download'] ?? null;

    expect($all['name'])->toBe('articles-2026-11-22-100000.csv');

    $csv = base64_decode($all['content']);
    $lines = array_map('str_getcsv', array_filter(explode("\n", trim(substr($csv, 3)))));

    expect(substr($csv, 0, 3))->toBe("\xEF\xBB\xBF")
        ->and($lines[0])->toBe(['Title', 'Body', 'Views', 'Tags', 'Comments'])
        ->and(array_column($lines, 0))->toContain('Alpha', 'Beta', 'Gamma 100%', "'=HYPERLINK(\"http://x\")")
        ->and(array_column($lines, 0))->not->toContain('Gone')
        ->and($lines[1][4])->toBe('Nice')
        ->and($csv)->not->toContain('Secret');

    $searched = base64_decode($page->set('search', 'Beta')->call('export')->effects['download']['content']);

    expect($searched)->toContain('Beta')->not->toContain('Alpha');
});

test('select all matching runs a bulk action on every match across pages', function () {
    (new JsonBreadSource($this->path))->save(listingBread());

    foreach (range(1, 20) as $n) {
        ListingArticle::create(['title' => "Bulk {$n}", 'views' => 50]);
    }

    $page = Livewire::test('tardis::pages.bread.index', ['slug' => 'articles'])
        ->set('search', 'Bulk')
        ->call('togglePage')
        ->assertSee('Select all 20 matching records');

    $page->call('selectMatching')->assertSet('selectAllMatching', true)
        ->assertSee('Every matching record is selected.');

    $page->call('runBulk', 'delete')->assertSet('selected', [])->assertSet('selectAllMatching', false);

    expect(ListingArticle::where('title', 'like', 'Bulk%')->count())->toBe(0)
        ->and(ListingArticle::whereIn('title', ['Alpha', 'Beta', 'Gamma 100%'])->count())->toBe(3);
});

test('select all matching refuses more records than the bulk limit', function () {
    config()->set('tardis.bread.bulk_limit', 2);
    (new JsonBreadSource($this->path))->save(listingBread());

    Livewire::test('tardis::pages.bread.index', ['slug' => 'articles'])
        ->call('togglePage')
        ->call('selectMatching')
        ->call('runBulk', 'delete')
        ->assertHasErrors('selected');

    expect(ListingArticle::count())->toBe(3);
});

function importCsv(string $csv): Testable
{
    return Livewire::test('tardis::pages.bread.index', ['slug' => 'articles'])
        ->set('importFile', UploadedFile::fake()->createWithContent('articles.csv', $csv))
        ->call('import');
}

test('a CSV adds new rows and updates rows that carry an existing key', function () {
    $this->artisan('migrate');
    (new JsonBreadSource($this->path))->save(listingBread());

    $page = importCsv("\xEF\xBB\xBFid,Title,Views,Nonsense\n1,Alpha edited,7,x\n,Delta,4,y\n,'=SUM(1),2,z\n");

    expect($page->get('importReport'))->toMatchArray(['created' => 2, 'updated' => 1, 'errors' => [], 'ignored' => ['Nonsense']])
        ->and(ListingArticle::find(1)->only('title', 'views'))->toBe(['title' => 'Alpha edited', 'views' => 7])
        ->and(ListingArticle::where('title', 'Delta')->value('views'))->toBe(4)
        // The quote the export puts before a formula is taken off again.
        ->and(ListingArticle::where('title', '=SUM(1)')->exists())->toBeTrue()
        ->and(ActivityLog::query()->where('action', 'imported')->exists())->toBeTrue();
});

test('one invalid row and nothing is imported, with the row numbers', function () {
    $bread = listingBread();
    $bread['fields'][2]['validation'] = ['required', 'integer', 'max:100'];
    (new JsonBreadSource($this->path))->save($bread);

    $page = importCsv("title,views\nGood,3\nBad,500\nWorse,\n");

    $report = $page->get('importReport');

    expect($report['created'])->toBe(0)
        ->and(array_keys($report['errors']))->toBe([3, 4])
        ->and(ListingArticle::where('title', 'Good')->exists())->toBeFalse();

    $page->assertSee('Row 3:')->call('closeImport')->assertSet('importReport', null);
});

test('a file whose columns match no field is refused', function () {
    (new JsonBreadSource($this->path))->save(listingBread());

    expect(importCsv("foo,bar\n1,2\n")->get('importReport')['errors'])->toBe([1 => [__('tardis::bread.import_no_columns')]])
        ->and(ListingArticle::count())->toBe(3);
});

test('an exported file reads back in unchanged', function () {
    (new JsonBreadSource($this->path))->save(listingBread());

    $csv = base64_decode(Livewire::test('tardis::pages.bread.index', ['slug' => 'articles'])->call('export')->effects['download']['content']);
    $before = ListingArticle::orderBy('id')->get(['title', 'body', 'views'])->toArray();

    $report = importCsv($csv)->get('importReport');

    // The export has no key column, so every row comes back as a new record
    // with the same values.
    expect($report['errors'])->toBe([])
        ->and($report['created'])->toBe(3)
        ->and(ListingArticle::orderBy('id')->skip(3)->take(3)->get(['title', 'body', 'views'])->toArray())->toBe($before);
});

/** @return list<list<mixed>> the first sheet of a workbook */
function xlsxRows(string $contents): array
{
    $path = (string) tempnam(sys_get_temp_dir(), 'xlsx-test-');
    file_put_contents($path, $contents);
    $reader = new Reader;
    $reader->open($path);
    $rows = [];

    foreach ($reader->getSheetIterator() as $sheet) {
        foreach ($sheet->getRowIterator() as $row) {
            $rows[] = $row->toArray();
        }
        break;
    }

    $reader->close();
    unlink($path);

    return $rows;
}

test('the list exports what it shows as Excel, with formulas kept as text', function () {
    Carbon::setTestNow('2026-11-22 10:00:00');
    (new JsonBreadSource($this->path))->save(listingBread());
    ListingArticle::create(['title' => '=HYPERLINK("http://x")', 'body' => 'formula', 'views' => 3]);

    $download = Livewire::test('tardis::pages.bread.index', ['slug' => 'articles'])
        ->assertSee(__('tardis::bread.export_xlsx'))
        ->call('export', 'xlsx')->effects['download'];

    $rows = xlsxRows(base64_decode($download['content']));

    expect($download['name'])->toBe('articles-2026-11-22-100000.xlsx')
        ->and($rows[0])->toBe(['Title', 'Body', 'Views', 'Tags', 'Comments'])
        ->and(array_column($rows, 0))->toContain('Alpha', '=HYPERLINK("http://x")')
        ->and(json_encode($rows))->not->toContain('Secret');
});

test('an Excel file is imported through the same rules as a CSV', function () {
    $this->artisan('migrate');
    (new JsonBreadSource($this->path))->save(listingBread());

    $path = (string) tempnam(sys_get_temp_dir(), 'xlsx-in-').'.xlsx';
    $writer = new Writer;
    $writer->openToFile($path);
    $writer->addRow(Row::fromValues(['Title', 'Views']));
    $writer->addRow(Row::fromValues(['From Excel', 9]));
    $writer->close();

    $page = Livewire::test('tardis::pages.bread.index', ['slug' => 'articles'])
        ->set('importFile', UploadedFile::fake()->createWithContent('articles.xlsx', (string) file_get_contents($path)))
        ->call('import');
    unlink($path);

    expect($page->get('importReport'))->toMatchArray(['created' => 1, 'errors' => []])
        ->and(ListingArticle::query()->where('title', 'From Excel')->value('views'))->toBe(9);
});

test('with Excel switched off the list offers and writes CSV only', function () {
    config()->set('tardis.bread.xlsx', false);
    (new JsonBreadSource($this->path))->save(listingBread());

    $download = Livewire::test('tardis::pages.bread.index', ['slug' => 'articles'])
        ->assertDontSee(__('tardis::bread.export_xlsx'))
        ->call('export', 'xlsx')->effects['download'];

    expect($download['name'])->toEndWith('.csv');
});
