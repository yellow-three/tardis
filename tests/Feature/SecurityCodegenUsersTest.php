<?php

declare(strict_types=1);

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tardis\Auth\Abilities;
use Tardis\Auth\BreadAuthorization;
use Tardis\Auth\RoleAuthorizationPlugin;
use Tardis\Contracts\Plugins\AuthorizationPlugin;
use Tardis\Database\MigrationWriter;
use Tardis\Database\ModelGenerator;
use Tardis\Database\Seeders\PermissionSeeder;
use Tardis\Models\Permission;
use Tardis\Models\Role;
use Tardis\Plugins\PluginManager;

class CodegenUsersTestUser extends Authenticatable
{
    protected $table = 'users';

    protected $guarded = [];
}

/** Answers from an allow-list, so the test can revoke an ability mid-request. */
class CodegenAbilityPlugin implements AuthorizationPlugin
{
    /** @var list<string> */
    public static array $allowed = [Abilities::ACCESS, Abilities::USERS];

    public function name(): string
    {
        return 'codegen-abilities';
    }

    public function can(string $ability, mixed $model = null): bool
    {
        return in_array($ability, self::$allowed, true);
    }

    public function authorize(string $ability, mixed $model = null): void
    {
        $this->can($ability, $model) || abort(403);
    }
}

/** @return list<int> */
function codegenRolesOf(CodegenUsersTestUser $user): array
{
    return DB::table('tardis_role_user')->where('user_id', $user->id)->pluck('role_id')->map(fn ($id) => (int) $id)->sort()->values()->all();
}

/** @return list<int> */
function codegenPermissionsOf(Role $role): array
{
    return $role->permissions()->pluck('tardis_permissions.id')->map(fn ($id) => (int) $id)->sort()->values()->all();
}

beforeEach(function () {
    Schema::dropIfExists('users');
    Schema::create('users', function ($table) {
        $table->id();
        $table->string('name')->nullable();
        $table->string('email')->nullable();
        $table->string('password')->nullable();
        $table->rememberToken();
        $table->timestamps();
    });

    config()->set('auth.providers.users.model', CodegenUsersTestUser::class);
    $this->artisan('migrate');
    $this->seed(PermissionSeeder::class);

    $plugins = app(PluginManager::class);
    $plugins->register(RoleAuthorizationPlugin::KEY, RoleAuthorizationPlugin::class);
    $plugins->enableByDefault(RoleAuthorizationPlugin::KEY);

    $this->managerRole = Role::create(['name' => 'Managers', 'slug' => 'managers']);
    $this->managerRole->permissions()->sync(Permission::whereIn('slug', [Abilities::ACCESS, Abilities::USERS, Abilities::ROLES])->pluck('id'));
    $this->manager = CodegenUsersTestUser::create(['name' => 'Mia', 'email' => 'mia@example.test']);
    DB::table('tardis_role_user')->insert(['role_id' => $this->managerRole->id, 'user_id' => $this->manager->id]);

    $this->superRole = Role::where('slug', 'super-admin')->firstOrFail();
    $this->extraRole = Role::create(['name' => 'Ops', 'slug' => 'ops']);
    $this->extraRole->permissions()->sync(Permission::where('slug', Abilities::COMMANDS)->pluck('id'));
    $this->super = CodegenUsersTestUser::create(['name' => 'Sam', 'email' => 'sam@example.test']);
    DB::table('tardis_role_user')->insert([
        ['role_id' => $this->superRole->id, 'user_id' => $this->super->id],
        ['role_id' => $this->extraRole->id, 'user_id' => $this->super->id],
    ]);

    $this->outsidePath = (getenv('HOME') ?: $_SERVER['HOME']).'/.cache/tardis-codegen-outside';
});

afterEach(function () {
    Schema::dropIfExists('codegen_injected');
    Schema::dropIfExists('codegen_widgets');

    foreach (['CodegenInjected', 'CodegenWidget', 'InjectedModel', 'Widget'] as $class) {
        File::delete(app_path('Models/'.$class.'.php'));
    }

    File::deleteDirectory($this->outsidePath);
});

// S2: database identifiers and command options become code, so only plain
// identifiers may reach a generated model or a migration file.

test('S2 an injected column name is skipped instead of becoming executable PHP', function () {
    $injected = "x']; public function __construct(){system('id');} protected \$dummy = ['x";

    DB::statement('CREATE TABLE codegen_injected (id integer primary key autoincrement, "'.$injected.'" text)');

    $generator = app(ModelGenerator::class);
    $file = $generator->generate('codegen_injected', Schema::getColumns('codegen_injected'));
    $content = (string) File::get($file);

    $calls = array_filter(
        token_get_all($content),
        fn ($token) => is_array($token) && $token[0] === T_STRING
            && in_array(strtolower($token[1]), ['system', 'exec', 'shell_exec'], true),
    );

    exec('php -l '.escapeshellarg($file).' 2>&1', $output, $status);

    expect($content)->not->toContain($injected)
        ->and($calls)->toBe([])
        ->and($content)->toContain("#[Table('codegen_injected')]")
        ->and($generator->warnings())->not->toBe([])
        ->and($status)->toBe(0);
});

test('S2 an injected namespace is refused', function () {
    expect(fn () => app(ModelGenerator::class)->generate('codegen_widgets', [
        ['name' => 'title', 'type' => 'string'],
    ], ['namespace' => "App\Models; system('id'); //"]))->toThrow(InvalidArgumentException::class);

    expect(File::exists(app_path('Models/CodegenWidget.php')))->toBeFalse();
});

test('S2 make:model refuses a path outside the application', function () {
    Schema::create('codegen_widgets', function ($table) {
        $table->id();
        $table->string('title');
    });
    File::ensureDirectoryExists($this->outsidePath);

    $this->artisan('tardis:make:model', ['table' => 'codegen_widgets', '--path' => $this->outsidePath])
        ->assertExitCode(1);

    expect(File::exists($this->outsidePath.'/CodegenWidget.php'))->toBeFalse();
});

test('S2 a normal table still generates the same model', function () {
    $content = (string) File::get(app(ModelGenerator::class)->generate('codegen_widgets', [
        ['name' => 'id', 'type' => 'integer', 'primary' => 1],
        ['name' => 'title', 'type' => 'string'],
        ['name' => 'created_at', 'type' => 'datetime'],
        ['name' => 'updated_at', 'type' => 'datetime'],
    ]));

    expect($content)
        ->toContain('namespace App\Models;')
        ->toContain('class CodegenWidget extends Model')
        ->toContain('protected $fillable = [')
        ->toContain("'title',")
        ->not->toContain("'id',")
        ->not->toContain('created_at')
        ->not->toContain('updated_at');
});

test('S2 migration writer refuses identifiers that could become code', function () {
    $injected = "widgets'); system('id'); //";

    expect(fn () => app(MigrationWriter::class)->createTable($injected, [
        ['name' => 'title', 'type' => 'string'],
    ]))->toThrow(InvalidArgumentException::class)
        ->and(fn () => app(MigrationWriter::class)->addColumn('widgets', [
            'name' => $injected, 'type' => 'string',
        ]))->toThrow(InvalidArgumentException::class);
});

// S5: managing users and roles must not be a route around the super-admin
// restriction, and the fixed permissions are what grant those abilities.

test('S5 a manager cannot open a super administrator for role editing', function () {
    $this->actingAs($this->manager);

    $page = Livewire::test('tardis::pages.users');
    $page->call('openEdit', $this->super->id)->assertForbidden();

    expect($page->get('editUserId'))->toBeNull()
        ->and($page->get('showEditModal'))->toBeFalsy()
        ->and(codegenRolesOf($this->super))->toEqualCanonicalizing([$this->extraRole->id, $this->superRole->id]);
});

test('S5 a manager cannot save the roles of a super administrator', function () {
    $before = codegenRolesOf($this->super);
    $this->actingAs($this->manager);

    Livewire::test('tardis::pages.users')
        ->set('editUserId', $this->super->id)
        ->set('editRoleIds', [$this->superRole->id])
        ->call('saveRoles')
        ->assertForbidden();

    expect(codegenRolesOf($this->super))->toBe($before);
});

test('S5 only a super administrator may empty the super-admin role', function () {
    $before = codegenPermissionsOf($this->superRole);
    $this->actingAs($this->manager);

    Livewire::test('tardis::pages.roles')
        ->call('editRole', $this->superRole->id)
        ->assertForbidden();

    // A forged edit state must not get past the save action either.
    Livewire::test('tardis::pages.roles')
        ->set('editRoleId', $this->superRole->id)
        ->set('editRolePermissions', [])
        ->call('saveRolePermissions')
        ->assertForbidden();

    expect(codegenPermissionsOf($this->superRole))->toBe($before);
});

test('S5 a manager cannot delete the super-admin role', function () {
    $this->actingAs($this->manager);

    Livewire::test('tardis::pages.roles')
        ->call('confirmDelete', $this->superRole->id)
        ->assertForbidden();

    Livewire::test('tardis::pages.roles')
        ->set('deleteId', $this->superRole->id)
        ->call('deleteRole')
        ->assertForbidden();

    expect(Role::whereKey($this->superRole->id)->exists())->toBeTrue();
});

test('S5 a seeded core permission cannot be deleted', function () {
    $permission = Permission::where('slug', Abilities::ACCESS)->firstOrFail();
    $this->actingAs($this->manager);

    Livewire::test('tardis::pages.permissions')
        ->set('deleteId', $permission->id)
        ->call('deletePermission')
        ->assertForbidden();

    expect(Permission::whereKey($permission->id)->exists())->toBeTrue();
});

test('S5 creating a permission needs the roles ability on the action itself', function () {
    CodegenAbilityPlugin::$allowed = [Abilities::ACCESS, Abilities::USERS, Abilities::ROLES];
    $this->actingAs($this->manager);

    $component = Livewire::test('tardis::pages.permissions')->instance();
    $component->newName = 'Extra';
    $component->newSlug = 'extra';

    // The page may have been opened before the ability was revoked; the action
    // itself still has to check it, without relying on boot().
    CodegenAbilityPlugin::$allowed = [Abilities::ACCESS, Abilities::USERS];
    $plugins = new PluginManager($this->outsidePath.'/plugins.json');
    $plugins->register('codegen-abilities', CodegenAbilityPlugin::class);
    $plugins->enableByDefault('codegen-abilities');
    app()->instance(PluginManager::class, $plugins);
    app()->forgetInstance(BreadAuthorization::class);

    expect(fn () => $component->createPermission())
        ->toThrow(HttpException::class);

    expect(Permission::where('slug', 'extra')->exists())->toBeFalse();
});

test('S5 a super administrator keeps the user and role controls', function () {
    $this->actingAs($this->super);

    Livewire::test('tardis::pages.users')
        ->call('openEdit', $this->manager->id)
        ->set('editRoleIds', [$this->managerRole->id, $this->superRole->id])
        ->call('saveRoles')
        ->assertSet('error', null);

    expect(codegenRolesOf($this->manager))->toEqualCanonicalizing([$this->managerRole->id, $this->superRole->id]);

    Livewire::test('tardis::pages.roles')
        ->call('editRole', $this->superRole->id)
        ->set('editRolePermissions', [])
        ->call('saveRolePermissions')
        ->assertSet('error', null);

    expect(codegenPermissionsOf($this->superRole))->toBe([]);

    Livewire::test('tardis::pages.permissions')
        ->set('newName', 'Extra')
        ->set('newSlug', 'extra')
        ->call('createPermission')
        ->assertSet('error', null);

    expect(Permission::where('slug', 'extra')->exists())->toBeTrue();
});
