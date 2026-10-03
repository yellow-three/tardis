<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Tardis\Auth\TardisAuthorizationPlugin;
use Tardis\Bread\Sources\JsonBreadSource;
use Tardis\Contracts\Plugins\AuthorizationPlugin;
use Tardis\Manager\PluginManager;

class BreadAuthzTestModel extends Model
{
    protected $table = 'bread_authz_posts';

    protected $guarded = [];

    public $timestamps = true;
}

class BreadAuthzTestUser extends Authenticatable
{
    protected $table = 'users';

    protected $guarded = [];
}

/**
 * Answers from a whitelist and records every ability it was asked about, so a
 * test can assert both the decision and the exact ability string the guard
 * derived from the BREAD slug.
 */
class RecordingAuthorizationPlugin implements AuthorizationPlugin
{
    /** @var array<int, string> */
    public static array $asked = [];

    /** @var array<int, string> */
    public static array $allowed = [];

    public function name(): string
    {
        return 'recording-authz';
    }

    public function can(string $ability, mixed $model = null): bool
    {
        static::$asked[] = $ability;

        return in_array($ability, static::$allowed, true);
    }

    public function authorize(string $ability, mixed $model = null): void
    {
        if (! $this->can($ability, $model)) {
            abort(403, 'Unauthorized.');
        }
    }
}

function authzSchema(): void
{
    Schema::create('bread_authz_posts', function (Blueprint $table) {
        $table->id();
        $table->string('title');
        $table->text('content')->nullable();
        $table->timestamps();
    });

    Schema::create('users', function (Blueprint $table) {
        $table->id();
        $table->string('name')->nullable();
        $table->string('email')->nullable();
        $table->timestamps();
    });

    Schema::create('tardis_permissions', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('slug')->unique();
        $table->string('group')->nullable();
        $table->timestamps();
    });

    Schema::create('tardis_roles', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('slug')->unique();
        $table->timestamps();
    });

    Schema::create('tardis_permission_role', function (Blueprint $table) {
        $table->unsignedBigInteger('permission_id');
        $table->unsignedBigInteger('role_id');
    });

    Schema::create('tardis_role_user', function (Blueprint $table) {
        $table->unsignedBigInteger('role_id');
        $table->unsignedBigInteger('user_id');
    });
}

function bindAuthzBread(): void
{
    app()->instance(JsonBreadSource::class, new JsonBreadSource(__DIR__.'/../Fixtures/bread'));
}

function allowAbilities(array $abilities): void
{
    RecordingAuthorizationPlugin::$asked = [];
    RecordingAuthorizationPlugin::$allowed = $abilities;

    $manager = app(PluginManager::class);
    $manager->register('recording-authz', RecordingAuthorizationPlugin::class);
    $manager->enable('recording-authz');
}

function authzUser(): BreadAuthzTestUser
{
    return BreadAuthzTestUser::create(['name' => 'Ada', 'email' => 'ada@example.test']);
}

/**
 * A host user carrying Spatie's HasRoles trait. TARDIS does not depend on
 * spatie/laravel-permission — it probes for the trait at runtime — so this
 * stands in for whatever the host installed, without needing the package here.
 */
class SpatieStyleAuthzTestUser extends BreadAuthzTestUser
{
    protected $table = 'users';

    /** @var array<int, string> */
    public array $askedAbilities = [];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->exists = true;
    }

    public function hasPermissionTo($ability, $guardName = null): bool
    {
        $this->askedAbilities[] = $ability;

        return true;
    }
}

test('the bread index page asks the authorization plugin for the browse ability', function () {
    authzSchema();
    bindAuthzBread();
    allowAbilities(['browse bread-page-authz']);

    $this->actingAs(authzUser());

    BreadAuthzTestModel::create(['title' => 'Visible record']);

    Livewire::test('tardis::pages.bread.index', ['slug' => 'bread-page-authz'])
        ->assertOk()
        ->assertSee('Visible record');

    expect(RecordingAuthorizationPlugin::$asked)->toContain('browse bread-page-authz');
});

test('the bread create page asks the authorization plugin for the add ability', function () {
    authzSchema();
    bindAuthzBread();
    allowAbilities(['add bread-page-authz']);

    $this->actingAs(authzUser());

    Livewire::test('tardis::pages.bread.create', ['slug' => 'bread-page-authz'])
        ->assertOk();

    expect(RecordingAuthorizationPlugin::$asked)->toContain('add bread-page-authz');
});

test('the bread edit page asks the authorization plugin for the edit ability', function () {
    authzSchema();
    bindAuthzBread();
    allowAbilities(['edit bread-page-authz']);

    $post = BreadAuthzTestModel::create(['title' => 'Editable']);

    $this->actingAs(authzUser());

    Livewire::test('tardis::pages.bread.edit', ['slug' => 'bread-page-authz', 'id' => $post->id])
        ->assertOk();

    expect(RecordingAuthorizationPlugin::$asked)->toContain('edit bread-page-authz');
});

test('the bread read page asks the authorization plugin for the read ability', function () {
    authzSchema();
    bindAuthzBread();
    allowAbilities(['read bread-page-authz']);

    $post = BreadAuthzTestModel::create(['title' => 'Readable']);

    $this->actingAs(authzUser());

    Livewire::test('tardis::pages.bread.read', ['slug' => 'bread-page-authz', 'id' => $post->id])
        ->assertOk()
        ->assertSee('Readable');

    expect(RecordingAuthorizationPlugin::$asked)->toContain('read bread-page-authz');
});

test('the bread index delete action asks the authorization plugin for the delete ability', function () {
    authzSchema();
    bindAuthzBread();
    allowAbilities(['browse bread-page-authz', 'delete bread-page-authz']);

    $post = BreadAuthzTestModel::create(['title' => 'Deletable']);

    $this->actingAs(authzUser());

    Livewire::test('tardis::pages.bread.index', ['slug' => 'bread-page-authz'])
        ->assertOk()
        ->call('delete', $post->id)
        ->assertOk();

    expect(RecordingAuthorizationPlugin::$asked)->toContain('delete bread-page-authz')
        ->and(BreadAuthzTestModel::find($post->id))->toBeNull();
});

test('a denied browse ability forbids the bread index page', function () {
    authzSchema();
    bindAuthzBread();
    allowAbilities([]);

    $this->actingAs(authzUser());

    Livewire::test('tardis::pages.bread.index', ['slug' => 'bread-page-authz'])
        ->assertForbidden();
});

test('a denied add ability forbids the bread create page', function () {
    authzSchema();
    bindAuthzBread();
    allowAbilities([]);

    $this->actingAs(authzUser());

    Livewire::test('tardis::pages.bread.create', ['slug' => 'bread-page-authz'])
        ->assertForbidden();
});

test('a denied edit ability forbids the bread edit page', function () {
    authzSchema();
    bindAuthzBread();
    allowAbilities([]);

    $post = BreadAuthzTestModel::create(['title' => 'Locked']);

    $this->actingAs(authzUser());

    Livewire::test('tardis::pages.bread.edit', ['slug' => 'bread-page-authz', 'id' => $post->id])
        ->assertForbidden();
});

test('a denied read ability forbids the bread read page', function () {
    authzSchema();
    bindAuthzBread();
    allowAbilities([]);

    $post = BreadAuthzTestModel::create(['title' => 'Hidden']);

    $this->actingAs(authzUser());

    Livewire::test('tardis::pages.bread.read', ['slug' => 'bread-page-authz', 'id' => $post->id])
        ->assertForbidden();
});

test('a denied delete ability stops the bread index delete action', function () {
    authzSchema();
    bindAuthzBread();
    allowAbilities(['browse bread-page-authz']);

    $post = BreadAuthzTestModel::create(['title' => 'Survivor']);

    $this->actingAs(authzUser());

    Livewire::test('tardis::pages.bread.index', ['slug' => 'bread-page-authz'])
        ->assertOk()
        ->call('delete', $post->id)
        ->assertForbidden();

    expect(BreadAuthzTestModel::find($post->id))->not->toBeNull();
});

test('bread pages stay reachable when no authorization plugin is registered', function () {
    authzSchema();
    bindAuthzBread();

    $post = BreadAuthzTestModel::create(['title' => 'Unguarded']);

    Livewire::test('tardis::pages.bread.index', ['slug' => 'bread-page-authz'])
        ->assertOk()
        ->assertSee('Unguarded');

    Livewire::test('tardis::pages.bread.create', ['slug' => 'bread-page-authz'])
        ->assertOk();

    Livewire::test('tardis::pages.bread.read', ['slug' => 'bread-page-authz', 'id' => $post->id])
        ->assertOk();

    Livewire::test('tardis::pages.bread.edit', ['slug' => 'bread-page-authz', 'id' => $post->id])
        ->assertOk();
});

test('the authorization plugin grants abilities held by a tardis role', function () {
    authzSchema();

    $user = authzUser();

    $permission = DB::table('tardis_permissions')->insertGetId([
        'name' => 'Browse Authz',
        'slug' => 'browse bread-page-authz',
        'group' => 'BREAD',
    ]);

    $role = DB::table('tardis_roles')->insertGetId([
        'name' => 'Editor',
        'slug' => 'editor',
    ]);

    DB::table('tardis_permission_role')->insert([
        'permission_id' => $permission,
        'role_id' => $role,
    ]);

    DB::table('tardis_role_user')->insert([
        'role_id' => $role,
        'user_id' => $user->id,
    ]);

    $this->actingAs($user);

    expect(app(TardisAuthorizationPlugin::class)->can('browse bread-page-authz'))->toBeTrue();
});

test('a host HasRoles user answers for itself without touching the tardis tables', function () {
    // The spatie/laravel-permission dependency was dropped; this is the path
    // that makes that safe. A host's own role trait must keep winning, and must
    // short-circuit before any schema probe, so a host on Spatie never gets a
    // half-truth from TARDIS' native permission tables.
    authzSchema();

    $user = new SpatieStyleAuthzTestUser(['name' => 'Grace', 'email' => 'grace@example.test']);
    $user->save();

    $this->actingAs($user);

    expect(app(TardisAuthorizationPlugin::class)->can('anything at all'))->toBeTrue()
        ->and($user->askedAbilities)->toBe(['anything at all']);
});

test('the authorization plugin denies abilities the tardis role does not hold', function () {
    authzSchema();

    $user = authzUser();

    $permission = DB::table('tardis_permissions')->insertGetId([
        'name' => 'Browse Authz',
        'slug' => 'browse bread-page-authz',
        'group' => 'BREAD',
    ]);

    $role = DB::table('tardis_roles')->insertGetId([
        'name' => 'Reader',
        'slug' => 'reader',
    ]);

    DB::table('tardis_permission_role')->insert([
        'permission_id' => $permission,
        'role_id' => $role,
    ]);

    DB::table('tardis_role_user')->insert([
        'role_id' => $role,
        'user_id' => $user->id,
    ]);

    $this->actingAs($user);

    expect(app(TardisAuthorizationPlugin::class)->can('browse bread-page-authz'))->toBeTrue()
        ->and(app(TardisAuthorizationPlugin::class)->can('delete bread-page-authz'))->toBeFalse();
});

test('a user without any tardis role is denied every bread ability', function () {
    authzSchema();

    $this->actingAs(authzUser());

    expect(app(TardisAuthorizationPlugin::class)->can('browse bread-page-authz'))->toBeFalse();
});

test('the authorization plugin grants every ability to a super admin role', function () {
    authzSchema();

    $user = authzUser();

    $role = DB::table('tardis_roles')->insertGetId([
        'name' => 'Super Admin',
        'slug' => 'super-admin',
    ]);

    DB::table('tardis_role_user')->insert([
        'role_id' => $role,
        'user_id' => $user->id,
    ]);

    $this->actingAs($user);

    expect(app(TardisAuthorizationPlugin::class)->can('browse bread-page-authz'))->toBeTrue()
        ->and(app(TardisAuthorizationPlugin::class)->can('delete bread-page-authz'))->toBeTrue();
});

test('the client cannot rewrite the BREAD definition a page was authorised against', function (string $page, array $params) {
    authzSchema();
    bindAuthzBread();
    allowAbilities(['browse bread-page-authz', 'read bread-page-authz', 'edit bread-page-authz', 'add bread-page-authz', 'delete bread-page-authz']);

    $post = BreadAuthzTestModel::create(['title' => 'Mine']);
    $params = array_map(fn ($value) => $value === ':id' ? $post->id : $value, $params);

    $this->actingAs(authzUser());

    // Livewire public properties are client-writable. A tampered `bread.model`
    // would point an authorised ability at an unrelated model, and a tampered
    // `slug` would be checked against the wrong permission.
    $component = Livewire::test($page, $params);

    foreach (['bread.model', 'slug'] as $property) {
        expect(fn () => $component->set($property, 'App\\Models\\User'))
            ->toThrow(CannotUpdateLockedPropertyException::class);
    }
})->with([
    'index' => ['tardis::pages.bread.index', ['slug' => 'bread-page-authz']],
    'create' => ['tardis::pages.bread.create', ['slug' => 'bread-page-authz']],
    'read' => ['tardis::pages.bread.read', ['slug' => 'bread-page-authz', 'id' => ':id']],
    'edit' => ['tardis::pages.bread.edit', ['slug' => 'bread-page-authz', 'id' => ':id']],
]);

test('the edit page does not let the client swap the record that was authorised', function () {
    authzSchema();
    bindAuthzBread();
    allowAbilities(['edit bread-page-authz']);

    $post = BreadAuthzTestModel::create(['title' => 'Mine']);

    $this->actingAs(authzUser());

    $component = Livewire::test('tardis::pages.bread.edit', ['slug' => 'bread-page-authz', 'id' => $post->id]);

    expect(fn () => $component->set('id', 999))->toThrow(CannotUpdateLockedPropertyException::class);
});

test('search only lists resources the user may browse', function () {
    authzSchema();
    bindAuthzBread();
    allowAbilities([]);

    BreadAuthzTestModel::create(['title' => 'Confidential report']);

    $this->actingAs(authzUser());

    Livewire::test('tardis::pages.search')
        ->set('query', 'Confidential')
        ->assertDontSee('Confidential report');

    allowAbilities(['browse bread-page-authz']);

    Livewire::test('tardis::pages.search')
        ->set('query', 'Confidential')
        ->assertSee('Confidential report');
});
