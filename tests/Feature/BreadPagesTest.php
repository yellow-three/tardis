<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tardis\Bread\Sources\ConfigBreadSource;
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

    app()->instance(ConfigBreadSource::class, new ConfigBreadSource(__DIR__.'/../Fixtures/bread'));

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

    app()->instance(ConfigBreadSource::class, new ConfigBreadSource(__DIR__.'/../Fixtures/bread'));

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
