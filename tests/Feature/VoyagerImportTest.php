<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Tardis\Auth\Abilities;
use Tardis\Bread\BreadManager;
use Tardis\Import\Voyager\FieldMapper;
use Tardis\Import\Voyager\PermissionMapper;
use Tardis\Menu\SiteMenuStore;
use Tardis\Models\Role;
use Tardis\Settings\SettingsManager;

class VoyagerImportPost extends Model
{
    protected $table = 'posts';
}

class VoyagerImportCategory extends Model
{
    protected $table = 'categories';
}

/**
 * A small Voyager 1.x install on its own connection: a posts BREAD with a
 * category relationship, the core users BREAD, an admin and a main menu,
 * settings, and two roles.
 */
function seedVoyager(): void
{
    $schema = Schema::connection('voyager');

    $schema->create('data_types', function (Blueprint $t) {
        $t->id();
        $t->string('name');
        $t->string('slug');
        $t->string('display_name_singular');
        $t->string('display_name_plural');
        $t->string('icon')->nullable();
        $t->string('model_name')->nullable();
        $t->text('description')->nullable();
        $t->text('details')->nullable();
    });
    $schema->create('data_rows', function (Blueprint $t) {
        $t->id();
        $t->integer('data_type_id');
        $t->string('field');
        $t->string('type');
        $t->string('display_name');
        $t->boolean('required')->default(false);
        foreach (['browse', 'read', 'edit', 'add', 'delete'] as $flag) {
            $t->boolean($flag)->default(true);
        }
        $t->text('details')->nullable();
        $t->integer('order')->default(1);
    });
    $schema->create('menus', fn (Blueprint $t) => $t->id() && $t->string('name'));
    $schema->create('menu_items', function (Blueprint $t) {
        $t->id();
        $t->integer('menu_id');
        $t->string('title');
        $t->string('url')->default('');
        $t->string('target')->default('_self');
        $t->integer('parent_id')->nullable();
        $t->integer('order')->default(1);
        $t->string('route')->nullable();
        $t->text('parameters')->nullable();
    });
    $schema->create('settings', function (Blueprint $t) {
        $t->id();
        $t->string('key');
        $t->string('display_name');
        $t->text('value')->nullable();
        $t->text('details')->nullable();
        $t->string('type');
        $t->integer('order')->default(1);
        $t->string('group')->nullable();
    });
    $schema->create('roles', fn (Blueprint $t) => $t->id() && $t->string('name') && $t->string('display_name'));
    $schema->create('permissions', fn (Blueprint $t) => $t->id() && $t->string('key') && $t->string('table_name')->nullable());
    $schema->create('permission_role', fn (Blueprint $t) => $t->integer('permission_id') && $t->integer('role_id'));
    $schema->create('user_roles', fn (Blueprint $t) => $t->integer('user_id') && $t->integer('role_id'));
    $schema->create('users', fn (Blueprint $t) => $t->id() && $t->integer('role_id')->nullable());

    $db = DB::connection('voyager');

    $db->table('data_types')->insert([
        ['id' => 1, 'name' => 'posts', 'slug' => 'posts', 'display_name_singular' => 'Post', 'display_name_plural' => 'Posts', 'model_name' => VoyagerImportPost::class, 'details' => json_encode(['order_column' => 'position', 'order_direction' => 'desc', 'default_search_key' => 'title'])],
        ['id' => 2, 'name' => 'users', 'slug' => 'users', 'display_name_singular' => 'User', 'display_name_plural' => 'Users', 'model_name' => 'TCG\\Voyager\\Models\\User', 'details' => null],
    ]);
    $db->table('data_rows')->insert([
        ['data_type_id' => 1, 'field' => 'title', 'type' => 'text', 'display_name' => 'Title', 'required' => true, 'details' => json_encode(['validation' => ['rule' => 'max:120']]), 'order' => 2],
        ['data_type_id' => 1, 'field' => 'body', 'type' => 'rich_text_box', 'display_name' => 'Body', 'required' => false, 'details' => null, 'order' => 3],
        ['data_type_id' => 1, 'field' => 'status', 'type' => 'select_dropdown', 'display_name' => 'Status', 'required' => false, 'details' => json_encode(['default' => 'draft', 'options' => ['draft' => 'Draft', 'published' => 'Published']]), 'order' => 4],
        ['data_type_id' => 1, 'field' => 'image', 'type' => 'image', 'display_name' => 'Image', 'required' => false, 'details' => json_encode(['resize' => ['width' => '1000', 'height' => null], 'quality' => '70%', 'thumbnails' => [['name' => 'cropped', 'crop' => ['width' => '300', 'height' => '250']]]]), 'order' => 5],
        ['data_type_id' => 1, 'field' => 'gallery', 'type' => 'multiple_images', 'display_name' => 'Gallery', 'required' => false, 'details' => null, 'order' => 6],
        ['data_type_id' => 1, 'field' => 'category_id', 'type' => 'number', 'display_name' => 'Category', 'required' => false, 'details' => null, 'order' => 1],
        ['data_type_id' => 1, 'field' => 'post_belongsto_category_relationship', 'type' => 'relationship', 'display_name' => 'Category', 'required' => false, 'details' => json_encode(['model' => VoyagerImportCategory::class, 'table' => 'categories', 'type' => 'belongsTo', 'column' => 'category_id', 'key' => 'id', 'label' => 'name']), 'order' => 7],
    ]);
    $db->table('menus')->insert([['id' => 1, 'name' => 'admin'], ['id' => 2, 'name' => 'main']]);
    $db->table('menu_items')->insert([
        ['id' => 1, 'menu_id' => 1, 'title' => 'Dashboard', 'url' => '', 'route' => 'voyager.dashboard', 'parent_id' => null, 'order' => 1, 'target' => '_self'],
        ['id' => 2, 'menu_id' => 2, 'title' => 'Blog', 'url' => '/blog', 'route' => null, 'parent_id' => null, 'order' => 2, 'target' => '_self'],
        ['id' => 3, 'menu_id' => 2, 'title' => 'Home', 'url' => '/', 'route' => null, 'parent_id' => null, 'order' => 1, 'target' => '_self'],
        ['id' => 4, 'menu_id' => 2, 'title' => 'News', 'url' => 'blog/news', 'route' => null, 'parent_id' => 2, 'order' => 1, 'target' => '_blank'],
        ['id' => 5, 'menu_id' => 2, 'title' => 'Broken', 'url' => '', 'route' => 'does.not.exist', 'parent_id' => null, 'order' => 3, 'target' => '_self'],
    ]);
    $db->table('settings')->insert([
        ['key' => 'site.title', 'display_name' => 'Site Title', 'value' => 'My Site', 'type' => 'text', 'group' => 'Site', 'details' => ''],
        ['key' => 'site.layout', 'display_name' => 'Layout', 'value' => 'wide', 'type' => 'select_dropdown', 'group' => 'Site', 'details' => json_encode(['options' => ['wide' => 'Wide', 'boxed' => 'Boxed']])],
        ['key' => 'admin.bg_image', 'display_name' => 'Background', 'value' => '', 'type' => 'image', 'group' => 'Admin', 'details' => ''],
    ]);
    $db->table('roles')->insert([['id' => 1, 'name' => 'admin', 'display_name' => 'Administrator'], ['id' => 2, 'name' => 'user', 'display_name' => 'Normal User']]);
    $db->table('permissions')->insert([
        ['id' => 1, 'key' => 'browse_admin', 'table_name' => null],
        ['id' => 2, 'key' => 'browse_posts', 'table_name' => 'posts'],
        ['id' => 3, 'key' => 'edit_posts', 'table_name' => 'posts'],
        ['id' => 4, 'key' => 'browse_hooks', 'table_name' => null],
        ['id' => 5, 'key' => 'edit_settings', 'table_name' => 'settings'],
    ]);
    $db->table('permission_role')->insert([
        ['permission_id' => 1, 'role_id' => 1], ['permission_id' => 2, 'role_id' => 1], ['permission_id' => 3, 'role_id' => 1],
        ['permission_id' => 4, 'role_id' => 1], ['permission_id' => 5, 'role_id' => 1], ['permission_id' => 2, 'role_id' => 2],
    ]);
    $db->table('users')->insert([['id' => 7, 'role_id' => 1], ['id' => 8, 'role_id' => null]]);
    $db->table('user_roles')->insert([['user_id' => 8, 'role_id' => 2]]);
}

beforeEach(function () {
    config()->set('database.connections.voyager', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
    $this->artisan('migrate');
    seedVoyager();

    $this->path = sys_get_temp_dir().'/tardis-voyager-'.uniqid();
    config()->set('tardis.bread.path', $this->path);
});

afterEach(fn () => File::deleteDirectory($this->path));

test('a dry run reports and writes nothing', function () {
    $this->artisan('tardis:import:voyager', ['--connection' => 'voyager', '--dry-run' => true])
        ->expectsOutputToContain('Dry run')
        ->expectsOutputToContain('would import')
        ->assertSuccessful();

    expect(app(BreadManager::class)->has('posts'))->toBeFalse()
        ->and(app(SiteMenuStore::class)->names())->toBe([])
        ->and(Role::where('slug', 'admin')->exists())->toBeFalse();
});

test('data types become BREAD definitions, with unmapped fields reported', function () {
    $this->artisan('tardis:import:voyager', ['--connection' => 'voyager', '--only' => ['bread']])
        ->expectsOutputToContain('gallery')
        ->assertSuccessful();

    $posts = app(BreadManager::class)->find('posts');
    $fields = collect($posts->fields)->keyBy('name');

    expect($posts->orderColumn)->toBe('position')
        ->and($posts->orderDirection)->toBe('desc')
        ->and($posts->searchKey)->toBe('title')
        ->and($fields->keys()->all())->toBe(['category_id', 'title', 'body', 'status', 'image'])
        ->and($fields['category_id'])->toMatchArray(['type' => 'belongs_to', 'model' => VoyagerImportCategory::class, 'label_column' => 'name'])
        ->and($fields['title']['validation'])->toBe(['required', 'max:120'])
        ->and($fields['body']['type'])->toBe('rich_text')
        ->and($fields['status'])->toMatchArray(['type' => 'select', 'default' => 'draft', 'options' => ['draft' => 'Draft', 'published' => 'Published']])
        ->and($fields['image'])->toMatchArray(['type' => 'image', 'quality' => 70, 'thumbnails' => [['name' => 'cropped', 'width' => '300', 'height' => '250', 'crop' => true]]])
        ->and(app(BreadManager::class)->has('users'))->toBeFalse();
});

test('an existing BREAD is kept unless forced', function () {
    $this->artisan('tardis:import:voyager', ['--connection' => 'voyager', '--only' => ['bread']]);
    $this->artisan('tardis:import:voyager', ['--connection' => 'voyager', '--only' => ['bread']])
        ->expectsOutputToContain('use --force')
        ->assertSuccessful();
});

test('menus other than admin become site menus', function () {
    $this->artisan('tardis:import:voyager', ['--connection' => 'voyager', '--only' => ['menus']])->assertSuccessful();

    $items = app(SiteMenuStore::class)->items('main');

    expect(app(SiteMenuStore::class)->names())->toBe(['main'])
        ->and(array_column($items, 'title'))->toBe(['Home', 'Blog'])
        ->and($items[1]['children'][0])->toMatchArray(['title' => 'News', 'url' => '/blog/news', 'new_tab' => true]);
});

test('settings are created with their group, type and options', function () {
    $this->artisan('tardis:import:voyager', ['--connection' => 'voyager', '--only' => ['settings']])->assertSuccessful();

    $settings = app(SettingsManager::class);

    expect($settings->get('site.title'))->toBe('My Site')
        ->and($settings->findByKey('site.layout')->type)->toBe('select')
        ->and($settings->findByKey('site.layout')->options)->toBe(['wide' => 'Wide', 'boxed' => 'Boxed'])
        ->and($settings->findByKey('admin.bg_image')->type)->toBe('image');
});

test('roles, their permissions and user assignments are imported', function () {
    $this->artisan('tardis:import:voyager', ['--connection' => 'voyager', '--only' => ['roles']])
        ->expectsOutputToContain('browse_hooks')
        ->assertSuccessful();

    $admin = Role::where('slug', 'admin')->firstOrFail();

    expect($admin->name)->toBe('Administrator')
        ->and($admin->permissions()->pluck('slug')->sort()->values()->all())->toBe([Abilities::ACCESS, 'browse posts', 'edit posts', Abilities::SETTINGS])
        ->and(DB::table('tardis_role_user')->where('user_id', 7)->pluck('role_id')->all())->toBe([$admin->id])
        ->and(DB::table('tardis_role_user')->where('user_id', 8)->count())->toBe(1);
});

test('permission keys map onto Tardis abilities', function () {
    $mapper = new PermissionMapper(['blog_posts' => 'articles']);

    expect($mapper->map('browse_admin'))->toBe(Abilities::ACCESS)
        ->and($mapper->map('delete_blog_posts'))->toBe('delete articles')
        ->and($mapper->map('add_media'))->toBe(Abilities::MEDIA_UPLOAD)
        ->and($mapper->map('browse_bread'))->toBe(Abilities::BREAD)
        ->and($mapper->map('browse_unknown'))->toBeNull()
        ->and($mapper->map('weird'))->toBeNull();
});

test('a relationship Voyager cannot express is reported, not guessed', function () {
    $mapper = new FieldMapper;

    expect($mapper->map(['field' => 'x', 'type' => 'relationship', 'details' => ['type' => 'morphTo', 'model' => 'App\\X']]))->toBeNull()
        ->and($mapper->problem)->toContain('morphTo');
});

test('an unknown part is refused', function () {
    $this->artisan('tardis:import:voyager', ['--only' => ['pages']])->assertFailed();
});
