<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tardis\Bread\Sources\JsonBreadSource;
use Tardis\Classes\MenuItem;

class BreadPageTestModel extends Model
{
    protected $table = 'bread_page_posts';

    protected $guarded = [];

    public $timestamps = true;
}

class BreadPageCreateTestModel extends Model
{
    protected $table = 'bread_page_posts_create';

    protected $guarded = [];

    public $timestamps = true;
}

class BreadPageEditTestModel extends Model
{
    protected $table = 'bread_page_posts_edit';

    protected $guarded = [];

    public $timestamps = true;
}

test('bread index page renders records from a configured model', function () {
    Schema::create('bread_page_posts', function (Blueprint $table) {
        $table->id();
        $table->string('title');
        $table->text('content')->nullable();
        $table->timestamps();
    });

    BreadPageTestModel::create([
        'title' => 'Hello from index',
        'content' => 'Data content',
    ]);

    app()->instance(JsonBreadSource::class, new JsonBreadSource(__DIR__.'/../Fixtures/bread'));

    Livewire::test('tardis::pages.bread.index', ['slug' => 'bread-page-posts'])
        ->assertSee('Posts')
        ->assertSee('Hello from index');
});

test('bread create page can save a record', function () {
    Schema::create('bread_page_posts_create', function (Blueprint $table) {
        $table->id();
        $table->string('title');
        $table->text('content')->nullable();
        $table->timestamps();
    });

    app()->instance(JsonBreadSource::class, new JsonBreadSource(__DIR__.'/../Fixtures/bread'));

    Livewire::test('tardis::pages.bread.create', ['slug' => 'bread-page-posts-create'])
        ->set('form.title', 'New title')
        ->set('form.content', 'Body copy')
        ->call('save')
        ->assertHasNoErrors();

    expect(BreadPageCreateTestModel::where('title', 'New title')->exists())->toBeTrue();
});

test('sidebar menu stays active for nested admin routes', function () {
    Route::middleware('web')->get('/admin/bread/manage', fn () => 'manage')->name('tardis.bread.manage');
    Route::middleware('web')->get('/admin/bread/create', fn () => 'create')->name('tardis.bread.create');

    $request = Request::create('/admin/bread/create');
    $request->setRouteResolver(fn () => app('router')->getRoutes()->match($request));
    app()->instance('request', $request);

    $menuItem = (new MenuItem('BREAD', 'heroicon-o-table-cells'))
        ->route('tardis.bread.manage')
        ->activeMode('prefix');

    expect($menuItem->isActive())->toBeTrue();
});

test('bread route names resolve to the dedicated management and builder paths', function () {
    expect(route('tardis.bread.manage'))->toEndWith('/admin/bread');
    expect(route('tardis.bread.create'))->toEndWith('/admin/bread/create');
    expect(route('tardis.bread.index', ['slug' => 'posts']))->toEndWith('/admin/posts');
});

test('the BREAD management table links every definition to the builder edit page', function () {
    $path = sys_get_temp_dir().'/tardis-bread-manage-'.uniqid();
    app()->instance(JsonBreadSource::class, new JsonBreadSource($path));

    (new JsonBreadSource($path))->save([
        'slug' => 'posts',
        'model' => BreadPageCreateTestModel::class,
        'name' => 'Posts',
        'fields' => [
            'title' => ['name' => 'title', 'type' => 'text', 'label' => 'Title', 'required' => true, 'browse' => true, 'read' => true, 'edit' => true, 'add' => true, 'validation' => []],
        ],
        'relationships' => [],
    ]);

    $html = Livewire::test('tardis::pages.bread.manage')->html();

    // Without this the builder's edit mode is unreachable: the route exists but
    // nothing on the management page ever pointed at it.
    expect($html)->toContain(route('tardis.bread.edit', ['slug' => 'posts']));

    File::deleteDirectory($path);
});

test('dynamic bread URLs resolve to Livewire page routes', function () {
    $routes = app('router')->getRoutes();

    expect($routes->match(Request::create('/admin/posts', 'GET'))->getName())
        ->toBe('tardis.bread.index');
    expect($routes->match(Request::create('/admin/posts/create', 'GET'))->getName())
        ->toBe('tardis.bread.add');
    expect($routes->match(Request::create('/admin/posts/1', 'GET'))->getName())
        ->toBe('tardis.bread.read');
    expect($routes->match(Request::create('/admin/posts/1/edit', 'GET'))->getName())
        ->toBe('tardis.bread.edit.item');
});

test('a markdown field renders as a multi-line editor instead of a single-line input', function () {
    $path = sys_get_temp_dir().'/tardis-bread-md-'.uniqid();
    app()->instance(JsonBreadSource::class, new JsonBreadSource($path));

    (new JsonBreadSource($path))->save([
        'slug' => 'posts',
        'model' => BreadPageCreateTestModel::class,
        'name' => 'Posts',
        'fields' => [
            ['name' => 'title', 'type' => 'text', 'label' => 'Title', 'add' => true, 'validation' => []],
            ['name' => 'desc', 'type' => 'markdown', 'label' => 'Desc', 'add' => true, 'validation' => []],
        ],
        'relationships' => [],
    ]);

    $html = Livewire::test('tardis::pages.bread.create', ['slug' => 'posts'])->html();

    // Markdown was falling through to the catch-all branch, which emits
    // <input type="text"> — a single line, so markdown source is unusable.
    expect($html)->toContain('<textarea')
        ->and($html)->toContain('wire:model="form.desc"')
        ->and($html)->not->toContain('<input type="text" wire:model="form.desc"');

    File::deleteDirectory($path);
});

test('an uploaded file field stores the upload and persists its path', function () {
    Storage::fake('public');

    $path = sys_get_temp_dir().'/tardis-bread-file-'.uniqid();
    app()->instance(JsonBreadSource::class, new JsonBreadSource($path));

    Schema::create('bread_page_posts_create', function (Blueprint $table) {
        $table->id();
        $table->string('title')->nullable();
        $table->string('image')->nullable();
        $table->timestamps();
    });

    (new JsonBreadSource($path))->save([
        'slug' => 'posts',
        'model' => BreadPageCreateTestModel::class,
        'name' => 'Posts',
        'fields' => [
            ['name' => 'title', 'type' => 'text', 'label' => 'Title', 'add' => true, 'validation' => []],
            ['name' => 'image', 'type' => 'file', 'label' => 'Image', 'add' => true, 'validation' => []],
        ],
        'relationships' => [],
    ]);

    Livewire::test('tardis::pages.bread.create', ['slug' => 'posts'])
        ->set('form.title', 'With image')
        ->set('form.image', UploadedFile::fake()->image('avatar.png'))
        ->call('save')
        ->assertHasNoErrors();

    $record = BreadPageCreateTestModel::query()->where('title', 'With image')->firstOrFail();

    // The file must land on the disk AND its path be what gets stored —
    // previously the UploadedFile object itself was written to the column.
    expect($record->image)->toBeString()->toStartWith('uploads/');
    Storage::disk('public')->assertExists($record->image);

    File::deleteDirectory($path);
});

test('a blank field the database refuses to null is reported instead of crashing the save', function () {
    $path = sys_get_temp_dir().'/tardis-bread-required-'.uniqid();
    app()->instance(JsonBreadSource::class, new JsonBreadSource($path));

    Schema::create('bread_page_posts_create', function (Blueprint $table) {
        $table->id();
        $table->string('title');
        // NOT NULL with no default — the exact shape that made the real
        // posts table reject a partially filled form.
        $table->string('image');
        $table->timestamps();
    });

    (new JsonBreadSource($path))->save([
        'slug' => 'posts',
        'model' => BreadPageCreateTestModel::class,
        'name' => 'Posts',
        'fields' => [
            ['name' => 'title', 'type' => 'text', 'label' => 'Title', 'add' => true, 'validation' => []],
            ['name' => 'image', 'type' => 'file', 'label' => 'Image', 'add' => true, 'validation' => []],
        ],
        'relationships' => [],
    ]);

    $component = Livewire::test('tardis::pages.bread.create', ['slug' => 'posts'])
        ->set('form.title', 'No image')
        ->call('save');

    // The BREAD definition marks the field optional, so validation passes and
    // the NULL reaches the driver. That used to surface as SQLSTATE 23000.
    $component->assertHasErrors('form');

    expect(BreadPageCreateTestModel::query()->where('title', 'No image')->exists())->toBeFalse();

    File::deleteDirectory($path);
});

test('a markdown field renders as a multi-line editor on the edit page too', function () {
    $path = sys_get_temp_dir().'/tardis-bread-md-edit-'.uniqid();
    app()->instance(JsonBreadSource::class, new JsonBreadSource($path));

    Schema::create('bread_page_posts_edit', function (Blueprint $table) {
        $table->id();
        $table->string('title')->nullable();
        $table->text('desc')->nullable();
        $table->timestamps();
    });

    $record = BreadPageEditTestModel::create(['title' => 'Editable', 'desc' => '# Heading']);

    (new JsonBreadSource($path))->save([
        'slug' => 'posts',
        'model' => BreadPageEditTestModel::class,
        'name' => 'Posts',
        'fields' => [
            ['name' => 'title', 'type' => 'text', 'label' => 'Title', 'edit' => true, 'validation' => []],
            ['name' => 'desc', 'type' => 'markdown', 'label' => 'Desc', 'edit' => true, 'validation' => []],
        ],
        'relationships' => [],
    ]);

    $html = Livewire::test('tardis::pages.bread.edit', ['slug' => 'posts', 'id' => $record->id])->html();

    expect($html)->toContain('<textarea')
        ->and($html)->toContain('wire:model="form.desc"')
        ->and($html)->not->toContain('<input type="text" wire:model="form.desc"');

    File::deleteDirectory($path);
});

test('clearing a field the database refuses to null keeps the stored value on edit', function () {
    $path = sys_get_temp_dir().'/tardis-bread-required-edit-'.uniqid();
    app()->instance(JsonBreadSource::class, new JsonBreadSource($path));

    Schema::create('bread_page_posts_edit', function (Blueprint $table) {
        $table->id();
        $table->string('title');
        $table->string('image');
        $table->timestamps();
    });

    $record = BreadPageEditTestModel::create(['title' => 'Keep', 'image' => 'uploads/original.png']);

    (new JsonBreadSource($path))->save([
        'slug' => 'posts',
        'model' => BreadPageEditTestModel::class,
        'name' => 'Posts',
        'fields' => [
            ['name' => 'title', 'type' => 'text', 'label' => 'Title', 'edit' => true, 'validation' => []],
            ['name' => 'image', 'type' => 'file', 'label' => 'Image', 'edit' => true, 'validation' => []],
        ],
        'relationships' => [],
    ]);

    Livewire::test('tardis::pages.bread.edit', ['slug' => 'posts', 'id' => $record->id])
        ->set('form.image', '')
        ->call('save')
        ->assertHasErrors('form');

    // The update must be refused outright, not applied with a NULL that the
    // driver would have thrown on.
    expect($record->fresh()->image)->toBe('uploads/original.png');

    File::deleteDirectory($path);
});
