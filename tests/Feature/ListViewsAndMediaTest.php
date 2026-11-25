<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tardis\Bread\Sources\JsonBreadSource;
use Tardis\Facades\Tardis;
use Tardis\Media\MediaManager;
use Tardis\Media\MediaUsage;
use Tardis\Media\SvgSanitizer;
use Tardis\Support\UserPreferences;

class ListArticle extends Model
{
    protected $table = 'list_articles';

    protected $guarded = [];
}

class ListUser extends Authenticatable
{
    protected $table = 'users';

    protected $guarded = [];
}

beforeEach(function () {
    Schema::create('users', function ($table) {
        $table->id();
        $table->string('name')->nullable();
        $table->timestamps();
    });
    Schema::create('list_articles', function ($table) {
        $table->id();
        $table->string('title');
        $table->string('slug')->nullable();
        $table->integer('views')->nullable();
        $table->string('cover')->nullable();
        $table->timestamps();
    });
    config()->set('auth.providers.users.model', ListUser::class);

    $this->breadPath = sys_get_temp_dir().'/tardis-list-'.uniqid();
    app()->instance(JsonBreadSource::class, $source = new JsonBreadSource($this->breadPath));
    $source->save([
        'slug' => 'articles',
        'model' => ListArticle::class,
        'name' => 'Article',
        'name_plural' => 'Articles',
        'search_key' => 'title',
        'fields' => [
            ['name' => 'title', 'type' => 'text', 'label' => 'Title', 'inline' => true, 'validation' => ['required', 'max:20']],
            ['name' => 'slug', 'type' => 'text', 'label' => 'Slug'],
            ['name' => 'views', 'type' => 'number', 'label' => 'Views', 'inline' => true],
            ['name' => 'cover', 'type' => 'image', 'label' => 'Cover'],
        ],
    ]);

    $this->ada = ListUser::create(['name' => 'Ada']);
    $this->actingAs($this->ada);
    $this->first = ListArticle::create(['title' => 'First', 'slug' => 'first', 'views' => 3, 'cover' => 'media/cover.jpg']);
});

afterEach(fn () => File::deleteDirectory($this->breadPath));

test('a list view is saved per user, applied and deleted', function () {
    Livewire::test('tardis::pages.bread.index', ['slug' => 'articles'])
        ->set('search', 'Fir')
        ->call('sortBy', 'title')
        ->set('viewName', 'Mine')
        ->call('saveView')
        ->assertHasNoErrors();

    expect(app(UserPreferences::class)->get($this->ada->id, 'list_views')['articles']['Mine'])
        ->toMatchArray(['search' => 'Fir', 'sort' => 'title']);

    Livewire::test('tardis::pages.bread.index', ['slug' => 'articles'])
        ->assertSee('Mine')
        ->call('applyView', 'Mine')
        ->assertSet('search', 'Fir')
        ->assertSet('sort', 'title')
        ->call('deleteView', 'Mine')
        ->assertDontSee('Mine');

    expect(app(UserPreferences::class)->get($this->ada->id, 'list_views'))->toBe([]);
});

test('a saved view cannot smuggle in a sort or page size the list does not offer', function () {
    app(UserPreferences::class)->set($this->ada->id, 'list_views', ['articles' => ['Bad' => ['sort' => 'password', 'perPage' => 100000, 'trashed' => 'everything']]]);

    Livewire::test('tardis::pages.bread.index', ['slug' => 'articles'])
        ->call('applyView', 'Bad')
        ->assertSet('sort', '')
        ->assertSet('perPage', 15)
        ->assertSet('trashed', 'without');
});

test('an inline field saves one cell with its own rules', function () {
    Livewire::test('tardis::pages.bread.index', ['slug' => 'articles'])
        ->assertSee(__('tardis::bread.edit_inline', ['field' => 'Title']))
        ->call('updateInline', $this->first->id, 'title', 'Renamed')
        ->assertReturned(true)
        ->call('updateInline', $this->first->id, 'title', str_repeat('x', 40))
        ->assertReturned(false)
        ->call('updateInline', $this->first->id, 'slug', 'not-inline')
        ->assertReturned(false);

    expect($this->first->refresh()->title)->toBe('Renamed')->and($this->first->slug)->toBe('first');
});

test('duplicate opens the create form filled with a copy, without uploads', function () {
    Livewire::test('tardis::pages.bread.index', ['slug' => 'articles'])
        ->call('runAction', 'duplicate', $this->first->id)
        ->assertRedirect(url('admin/articles/create').'?duplicate='.$this->first->id);

    Livewire::withQueryParams(['duplicate' => (string) $this->first->id])
        ->test('tardis::pages.bread.create', ['slug' => 'articles'])
        ->assertSet('form.title', 'First')
        ->assertSet('form.views', 3)
        ->assertSet('copiedFrom', (string) $this->first->id)
        ->assertSee(__('tardis::bread.duplicate_notice', ['id' => $this->first->id]))
        ->assertSet('form.cover', fn ($value) => $value === null || $value === '');
});

test('media usage finds the records and settings that use a file', function () {
    Tardis::settings()->create(['group' => 'site', 'key' => 'logo', 'type' => 'image', 'value' => 'media/cover.jpg']);

    $usage = app(MediaUsage::class)->find('media/cover.jpg');

    expect(array_column($usage, 'label'))->toContain('#'.$this->first->id, 'site.logo')
        ->and(app(MediaUsage::class)->find('media/unused.jpg'))->toBe([]);

    Livewire::test('tardis::pages.media-browser')
        ->call('confirmDelete', 'cover.jpg')
        ->assertSet('fileUsage', fn (array $found) => count($found) === 2)
        ->assertSee(trans_choice('tardis::media.still_used', 2, ['count' => 2]));
});

test('scripts, handlers and outside links are removed from an SVG', function () {
    $dirty = '<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" onload="alert(1)">'
        .'<script>alert(2)</script><foreignObject><div>x</div></foreignObject>'
        .'<a href="javascript:alert(3)"><circle r="4" onclick="alert(4)" fill="red"/></a>'
        .'<a xlink:href="https://example.test/ok"><rect width="2" height="2" style="fill:url(#g)"/></a>'
        .'<image href="data:text/html,evil"/></svg>';

    $clean = SvgSanitizer::clean($dirty);

    expect($clean)->not->toContain('script')
        ->not->toContain('onload')->not->toContain('onclick')
        ->not->toContain('javascript:')->not->toContain('foreignObject')
        ->not->toContain('data:text/html')
        ->toContain('<circle')->toContain('https://example.test/ok')->toContain('url(#g)');

    expect(SvgSanitizer::clean('<!DOCTYPE svg [<!ENTITY x "y">]><svg>&x;</svg>'))->toBeNull()
        ->and(SvgSanitizer::clean('<html><body/></html>'))->toBeNull();
});

test('an SVG uploaded to the media library is stored cleaned', function () {
    $this->artisan('migrate');
    Storage::fake('public');
    config()->set('tardis.media.allowed_mimes', ['svg']);

    $file = UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script><rect width="1" height="1"/></svg>');
    $media = app(MediaManager::class)->upload($file);

    expect(Storage::disk('public')->get($media->path))->not->toContain('script')->toContain('<rect');
});
