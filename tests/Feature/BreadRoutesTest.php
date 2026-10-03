<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tardis\Bread\BreadDefinition;
use Tardis\Bread\Sources\JsonBreadSource;

beforeEach(function () {
    $this->routesPath = sys_get_temp_dir().'/tardis-bread-routes-'.uniqid();
    $this->source = new JsonBreadSource($this->routesPath);
    app()->instance(JsonBreadSource::class, $this->source);
});

afterEach(function () {
    File::deleteDirectory($this->routesPath);
});

function loadAdminRoutes(): void
{
    reloadAdminRoutes();
}

function routedMatch(string $uri): ?Illuminate\Routing\Route
{
    try {
        return app('router')->getRoutes()->match(Request::create($uri));
    } catch (NotFoundHttpException) {
        return null;
    }
}

function storeRoutedBread(JsonBreadSource $source, string $slug, array $extra = []): void
{
    $source->save($extra + ['slug' => $slug, 'model' => 'App\\Models\\Thing', 'name' => ucfirst($slug), 'name_plural' => ucfirst($slug).'s', 'fields' => []]);
}

test('a BREAD definition gets its own browse, add, read and edit routes', function () {
    storeRoutedBread($this->source, 'posts');
    loadAdminRoutes();

    expect(routedMatch('/admin/posts')?->getName())->toBe('tardis.bread.index')
        ->and(routedMatch('/admin/posts/create')?->getName())->toBe('tardis.bread.add')
        ->and(routedMatch('/admin/posts/7')?->getName())->toBe('tardis.bread.read')
        ->and(routedMatch('/admin/posts/7/edit')?->getName())->toBe('tardis.bread.edit.item');
});

test('an unknown slug is not swallowed by a wildcard', function () {
    storeRoutedBread($this->source, 'posts');
    loadAdminRoutes();

    expect(routedMatch('/admin/nothing'))->toBeNull()
        ->and(routedMatch('/admin/nothing/3/edit'))->toBeNull();
});

test('a plugin route registered after the package is not shadowed by BREAD routes', function () {
    // Two segments under the admin prefix used to match /{slug}/{id}.
    storeRoutedBread($this->source, 'posts');
    loadAdminRoutes();

    Route::get('/admin/blog/posts', fn () => 'plugin')->name('plugin.blog.posts');
    app('router')->getRoutes()->refreshNameLookups();

    expect(routedMatch('/admin/blog/posts')?->getName())->toBe('plugin.blog.posts');
});

test('route names resolve before any BREAD exists so links never break', function () {
    loadAdminRoutes();

    expect(route('tardis.bread.index', ['slug' => 'anything']))->toEndWith('/admin/anything')
        ->and(routedMatch('/admin/anything'))->toBeNull();
});

test('create is never captured by the read route', function () {
    storeRoutedBread($this->source, 'posts', ['components' => ['read' => 'acme::pages.post-read']]);
    loadAdminRoutes();

    expect(routedMatch('/admin/posts/create')?->getName())->toBe('tardis.bread.add')
        ->and(routedMatch('/admin/posts/12')?->getName())->toBe('tardis.bread.read.posts');
});

test('a definition can replace the component behind one action', function () {
    storeRoutedBread($this->source, 'posts', ['components' => ['browse' => 'acme::pages.post-list', 'edit' => 'acme::pages.post-edit']]);
    storeRoutedBread($this->source, 'pages');
    loadAdminRoutes();

    expect(routedMatch('/admin/posts')?->getName())->toBe('tardis.bread.index.posts')
        ->and(routedMatch('/admin/posts/4/edit')?->getName())->toBe('tardis.bread.edit.item.posts')
        // Other actions and other BREADs keep the stock pages.
        ->and(routedMatch('/admin/posts/create')?->getName())->toBe('tardis.bread.add')
        ->and(routedMatch('/admin/pages')?->getName())->toBe('tardis.bread.index')
        // URL generation by the stock name still produces the same address.
        ->and(route('tardis.bread.index', ['slug' => 'posts']))->toEndWith('/admin/posts');
});

test('reserved slugs never become BREAD routes', function () {
    // A file written by hand (or by an older version) can still hold one.
    File::ensureDirectoryExists($this->routesPath);
    File::put($this->routesPath.'/settings.json', json_encode(['slug' => 'settings', 'model' => 'X', 'name' => 'S', 'name_plural' => 'S', 'fields' => []]));
    loadAdminRoutes();

    expect(routedMatch('/admin/settings')?->getName())->toBe('tardis.settings.index');
});

test('a reserved slug cannot be saved', function (string $slug) {
    expect(fn () => storeRoutedBread($this->source, $slug))->toThrow(InvalidArgumentException::class, 'reserved');
})->with(['settings', 'users', 'bread', 'media', 'database', 'login', 'plugins']);

test('the definition keeps components, policy and scope', function () {
    $definition = BreadDefinition::fromArray([
        'slug' => 'posts', 'model' => 'App\\Models\\Post', 'name' => 'Post', 'name_plural' => 'Posts',
        'components' => ['browse' => 'acme::pages.list'], 'policy' => 'articles', 'scope' => 'published',
    ]);

    expect($definition->components)->toBe(['browse' => 'acme::pages.list'])
        ->and($definition->policy)->toBe('articles')
        ->and($definition->scope)->toBe('published')
        ->and($definition->toArray())->toMatchArray(['components' => ['browse' => 'acme::pages.list'], 'policy' => 'articles', 'scope' => 'published'])
        ->and($definition->permissionKey())->toBe('articles');
});

test('the permission key defaults to the slug', function () {
    expect(BreadDefinition::fromArray(['slug' => 'posts', 'model' => 'M', 'name' => 'P'])->permissionKey())->toBe('posts');
});
