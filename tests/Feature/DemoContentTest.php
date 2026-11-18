<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tardis\Bread\BreadManager;
use Tardis\Demo\Models\DemoPost;
use Tardis\Menu\SiteMenuStore;

beforeEach(function () {
    $this->artisan('migrate');
    $this->path = sys_get_temp_dir().'/tardis-demo-'.uniqid();
    config()->set('tardis.bread.path', $this->path);
});

afterEach(fn () => File::deleteDirectory($this->path));

test('the demo adds tables, BREADs, records and a site menu', function () {
    $this->artisan('tardis:demo')->expectsOutputToContain('Demo content ready')->assertSuccessful();

    expect(Schema::hasTable('tardis_demo_posts'))->toBeTrue()
        ->and(app(BreadManager::class)->all()->keys()->sort()->values()->all())->toBe(['categories', 'pages', 'posts'])
        ->and(DemoPost::count())->toBe(4)
        ->and(DemoPost::first()->category->name)->toBe('News')
        ->and(array_column(app(SiteMenuStore::class)->items('main'), 'title'))->toBe(['Home', 'Blog', 'About']);
});

test('the demo BREADs open in the panel', function () {
    $this->artisan('tardis:demo');

    Livewire::test('tardis::pages.bread.index', ['slug' => 'posts'])->assertOk()->assertSee('Welcome to Tardis')->assertSee('News');
    Livewire::test('tardis::pages.bread.create', ['slug' => 'posts'])->assertOk();
    Livewire::test('tardis::pages.bread.order', ['slug' => 'pages'])->assertOk()->assertSee('Contact');
});

test('running it twice adds nothing twice, and existing BREADs and menus are kept', function () {
    app(BreadManager::class)->save(['slug' => 'posts', 'model' => 'App\\Models\\Post', 'name' => 'Mine', 'name_plural' => 'Mine', 'fields' => []]);
    app(SiteMenuStore::class)->create('main');

    $this->artisan('tardis:demo')->expectsOutputToContain('Kept your existing [posts]')->assertSuccessful();
    $this->artisan('tardis:demo')->assertSuccessful();

    expect(app(BreadManager::class)->find('posts')->model)->toBe('App\\Models\\Post')
        ->and(app(SiteMenuStore::class)->items('main'))->toBe([])
        ->and(DemoPost::count())->toBe(4);
});

test('remove takes away only what the demo added', function () {
    app(SiteMenuStore::class)->create('footer');
    $this->artisan('tardis:demo');

    $this->artisan('tardis:demo', ['--remove' => true])->assertSuccessful();

    expect(Schema::hasTable('tardis_demo_posts'))->toBeFalse()
        ->and(app(BreadManager::class)->all())->toBeEmpty()
        ->and(app(SiteMenuStore::class)->names())->toBe(['footer']);
});

test('the demo refuses to run in production without --force', function () {
    app()->detectEnvironment(fn () => 'production');

    $this->artisan('tardis:demo')->assertFailed();

    expect(Schema::hasTable('tardis_demo_posts'))->toBeFalse();
});

test('tardis:install --demo adds the demo content', function () {
    $this->artisan('tardis:install', ['--force' => true, '--demo' => true])->assertSuccessful();

    expect(app(BreadManager::class)->has('posts'))->toBeTrue();
});
