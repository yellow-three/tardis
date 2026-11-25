<?php

declare(strict_types=1);

use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tardis\Auth\Abilities;
use Tardis\Auth\RoleAuthorizationPlugin;
use Tardis\Auth\TwoFactor\Totp;
use Tardis\Auth\TwoFactor\TwoFactorAuthentication;
use Tardis\Bread\BreadDefinition;
use Tardis\Bread\BreadManager;
use Tardis\Bread\BreadQuery;
use Tardis\Bread\Export\CsvExporter;
use Tardis\Bread\Sources\JsonBreadSource;
use Tardis\Contracts\Plugins\AuthenticationPlugin;
use Tardis\Contracts\Plugins\AuthorizationPlugin;
use Tardis\Formfields\Types\BelongsToManyField;
use Tardis\Formfields\Types\HasManyField;
use Tardis\Models\ActivityLog;
use Tardis\Models\Permission;
use Tardis\Models\Role;
use Tardis\Models\TwoFactor;
use Tardis\Plugins\PluginManager;

class SecArticle extends Model
{
    protected $table = 'sec_articles';

    protected $guarded = [];

    public $timestamps = false;

    public function tags()
    {
        return $this->belongsToMany(SecTag::class, 'sec_article_tag', 'article_id', 'tag_id');
    }

    public function comments()
    {
        return $this->hasMany(SecComment::class, 'article_id');
    }
}

class SecTag extends Model
{
    use SoftDeletes;

    protected $table = 'sec_tags';

    protected $guarded = [];

    public $timestamps = false;

    public function scopeVisible($query)
    {
        return $query->where('hidden', false);
    }
}

class SecComment extends Model
{
    protected $table = 'sec_comments';

    protected $fillable = ['body', 'secret'];

    public $timestamps = false;
}

class SecUser extends Authenticatable
{
    protected $table = 'sec_users';

    protected $guarded = [];
}

/** A plugin user that is not an Eloquent model, as a custom guard may return. */
class SecPlainUser implements AuthenticatableContract
{
    public function __construct(public mixed $id = null) {}

    public function getAuthIdentifierName(): string
    {
        return 'id';
    }

    public function getAuthIdentifier(): mixed
    {
        return $this->id;
    }

    public function getAuthPasswordName(): string
    {
        return 'password';
    }

    public function getAuthPassword(): string
    {
        return '';
    }

    public function getRememberToken(): ?string
    {
        return null;
    }

    public function setRememberToken($value): void {}

    public function getRememberTokenName(): string
    {
        return 'remember_token';
    }
}

/** Deliberately not a SessionAuthenticationPlugin: only the contract's guard() tells us its guard. */
class SecAuthPlugin implements AuthenticationPlugin
{
    public static mixed $user = null;

    public static string $guardName = 'staff';

    public function user(): mixed
    {
        return static::$user;
    }

    public function attempt(array $credentials, bool $remember = false): bool
    {
        $user = SecUser::where('email', (string) ($credentials['email'] ?? ''))->first();

        if (! $user || ! Hash::check((string) ($credentials['password'] ?? ''), (string) $user->password)) {
            return false;
        }

        auth()->guard(static::$guardName)->login($user, $remember);
        static::$user = $user;

        return true;
    }

    public function logout(Request $request): Request
    {
        auth()->guard(static::$guardName)->logout();

        return $request;
    }

    public function redirectTo(): string
    {
        return route('tardis.dashboard');
    }

    public function handleRequest(Request $request, Closure $next): mixed
    {
        return auth()->guard(static::$guardName)->check()
            ? $next($request)
            : redirect()->guest(route('tardis.login'));
    }

    public function name(): string
    {
        return 'sec-auth';
    }

    public function nameField(): string
    {
        return 'name';
    }

    public function avatar(mixed $user): ?string
    {
        return null;
    }

    public function guard(): string
    {
        return static::$guardName;
    }
}

class SecGatePlugin implements AuthorizationPlugin
{
    /** @var array<int, string> */
    public static array $allowed = [];

    public function name(): string
    {
        return 'sec-gate';
    }

    public function can(string $ability, mixed $model = null): bool
    {
        return in_array($ability, static::$allowed, true);
    }

    public function authorize(string $ability, mixed $model = null): void
    {
        if (! $this->can($ability, $model)) {
            abort(403, 'Unauthorized.');
        }
    }
}

function secArticleSchema(): void
{
    Schema::dropIfExists('sec_article_tag');
    Schema::dropIfExists('sec_comments');
    Schema::dropIfExists('sec_tags');
    Schema::dropIfExists('sec_articles');

    Schema::create('sec_articles', function (Blueprint $table) {
        $table->id();
        $table->string('title');
        $table->integer('budget')->default(0);
        $table->boolean('hidden')->default(false);
        $table->unsignedBigInteger('user_id')->nullable();
    });

    Schema::create('sec_tags', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->boolean('hidden')->default(false);
        $table->softDeletes();
    });

    Schema::create('sec_article_tag', function (Blueprint $table) {
        $table->unsignedBigInteger('article_id');
        $table->unsignedBigInteger('tag_id');
    });

    Schema::create('sec_comments', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('article_id');
        $table->string('body');
        $table->string('secret')->nullable();
    });
}

function secUserSchema(): void
{
    Schema::dropIfExists('sec_users');
    Schema::create('sec_users', function (Blueprint $table) {
        $table->id();
        $table->string('name')->nullable();
        $table->string('email')->nullable();
        $table->string('password')->nullable();
        $table->rememberToken();
        $table->timestamps();
    });
    config()->set('auth.providers.users.model', SecUser::class);
}

function secBindBread(): JsonBreadSource
{
    $source = new JsonBreadSource(storage_path('tardis/bread'));
    app()->instance(JsonBreadSource::class, $source);

    return $source;
}

function secGate(array $abilities): void
{
    SecGatePlugin::$allowed = $abilities;
    $plugins = app(PluginManager::class);
    $plugins->register('sec-gate', SecGatePlugin::class);
    $plugins->enableByDefault('sec-gate');
}

function secUseAuthPlugin(): void
{
    SecAuthPlugin::$user = null;
    SecAuthPlugin::$guardName = 'staff';
    config()->set('auth.guards.staff', ['driver' => 'session', 'provider' => 'users']);

    $plugins = app(PluginManager::class);
    $plugins->register('sec-auth', SecAuthPlugin::class);
    $plugins->enableByDefault('sec-auth');
}

afterEach(function () {
    SecAuthPlugin::$user = null;
    SecGatePlugin::$allowed = [];
});

test('P11 a custom authentication plugin finishes two factor on its own guard', function () {
    secUserSchema();
    $this->artisan('migrate');
    secUseAuthPlugin();

    $hashedPassword = Hash::make('correct-horse');
    $user = SecUser::create([
        'name' => 'Ada',
        'email' => 'ada@example.test',
        'password' => $hashedPassword,
    ]);
    $service = app(TwoFactorAuthentication::class);
    $seed = $service->start($user);
    $service->confirm($user, Totp::code($seed, Totp::currentStep() - 1));

    Livewire::test('tardis::pages.login')
        ->set('email', 'ada@example.test')
        ->set('password', 'correct-horse')
        ->call('login')
        ->assertRedirect(route('tardis.two-factor'));

    expect(auth()->guard('staff')->check())->toBeFalse()
        ->and(auth()->guard('web')->check())->toBeFalse()
        ->and(session('tardis.two_factor.guard'))->toBe('staff');

    $totpSecret = TwoFactor::query()->where('user_id', $user->id)->value('secret');

    Livewire::test('tardis::pages.two-factor-challenge')
        ->set('code', Totp::code($totpSecret, Totp::currentStep()))
        ->call('verify')
        ->assertRedirect(route('tardis.dashboard'));

    expect(auth()->guard('staff')->id())->toBe($user->id)
        ->and(auth()->guard('web')->check())->toBeFalse()
        ->and(session('tardis.two_factor'))->toBeNull();
});

test('P14 the builder refuses policies and protected models it cannot manage', function () {
    secArticleSchema();
    secUserSchema();
    $this->artisan('migrate');
    secBindBread();
    secGate([Abilities::BREAD]);

    foreach ([
        ['policy', 'shared-policy'],
        ['model', SecUser::class],
        ['model', Role::class],
        ['model', Permission::class],
        ['model', ActivityLog::class],
    ] as [$property, $value]) {
        Livewire::test('tardis::pages.bread-builder')
            ->set('model', SecArticle::class)
            ->call('detectFields')
            ->set('slug', 'sec-articles')
            ->set($property, $value)
            ->call('save')
            ->assertForbidden();
    }

    expect(app(BreadManager::class)->find('sec-articles'))->toBeNull();
});

test('P14 a super admin may save a definition with a foreign policy', function () {
    secArticleSchema();
    secUserSchema();
    $this->artisan('migrate');
    secBindBread();

    $roleId = DB::table('tardis_roles')->insertGetId(['name' => 'Super Admin', 'slug' => 'super-admin']);
    $user = SecUser::create(['name' => 'Root', 'email' => 'root@example.test']);
    DB::table('tardis_role_user')->insert(['role_id' => $roleId, 'user_id' => $user->id]);

    $plugins = app(PluginManager::class);
    $plugins->register(RoleAuthorizationPlugin::KEY, RoleAuthorizationPlugin::class);
    $plugins->enableByDefault(RoleAuthorizationPlugin::KEY);
    $this->actingAs($user);

    Livewire::test('tardis::pages.bread-builder')
        ->set('model', SecArticle::class)
        ->call('detectFields')
        ->set('slug', 'sec-articles')
        ->set('policy', 'shared-policy')
        ->call('save')
        ->assertHasNoErrors();

    expect(app(BreadManager::class)->find('sec-articles')->policy)->toBe('shared-policy');
});

test('P14 the builder revalidates the model class when saving', function () {
    secArticleSchema();
    secBindBread();
    secGate([Abilities::BREAD]);

    $component = Livewire::test('tardis::pages.bread-builder')
        ->set('model', SecArticle::class)
        ->call('detectFields')
        ->set('slug', 'sec-articles')
        ->set('model', BreadManager::class);

    expect(fn () => $component->call('save'))->not->toThrow(InvalidArgumentException::class);
    $component->assertHasErrors('model');

    expect(app(BreadManager::class)->find('sec-articles'))->toBeNull();
});

test('P1 a named filter on an ability hidden field does not filter the listing', function () {
    secArticleSchema();
    secBindBread();
    secGate(['browse sec-articles']);

    app(BreadManager::class)->save([
        'slug' => 'sec-articles',
        'model' => SecArticle::class,
        'name' => 'Article',
        'name_plural' => 'Articles',
        'fields' => [
            ['name' => 'title', 'type' => 'text', 'browse' => true],
            ['name' => 'budget', 'type' => 'number', 'browse' => true, 'ability' => 'see budgets'],
        ],
        'layout' => [
            'list' => ['title' => [], 'budget' => []],
            'options' => ['filters' => [
                'cheap' => ['key' => 'cheap', 'label' => 'Cheap', 'column' => 'budget', 'operator' => '=', 'value' => 10],
            ]],
        ],
    ]);

    SecArticle::create(['title' => 'Cheap', 'budget' => 10]);
    SecArticle::create(['title' => 'Expensive', 'budget' => 99]);

    $query = (new BreadQuery(app(BreadManager::class)->find('sec-articles')))
        ->build('', null, 'asc', 'without', [], ['cheap' => true]);

    expect($query->pluck('title')->all())->toEqualCanonicalizing(['Cheap', 'Expensive']);
});

test('P2 belongs to many ignores ids hidden by the related bread scope', function () {
    secArticleSchema();
    $source = secBindBread();

    $source->save([
        'slug' => 'sec-tags',
        'model' => SecTag::class,
        'name' => 'Tag',
        'name_plural' => 'Tags',
        'fields' => [],
        'scope' => 'visible',
    ]);

    $post = SecArticle::create(['title' => 'Parent']);
    $public = SecTag::create(['name' => 'Public']);
    $hidden = SecTag::create(['name' => 'Restricted', 'hidden' => true]);

    $field = (new BelongsToManyField('tags'))->model(SecTag::class)->relation('tags')->labelColumn('name');
    $field->stored([$public->id, $hidden->id], $post);

    expect($post->tags()->pluck('name')->all())->toBe(['Public']);
});

test('P6 has many writes only the declared child fields', function () {
    secArticleSchema();

    $post = SecArticle::create(['title' => 'Parent']);
    $field = (new HasManyField('comments'))->model(SecComment::class)->relation('comments');
    $field->fields = [['name' => 'body', 'type' => 'text']];

    $field->stored([['body' => 'Allowed', 'secret' => 'Leaked']], $post);
    $child = $post->comments()->firstOrFail();

    expect($child->body)->toBe('Allowed')
        ->and($child->secret)->toBeNull();

    $field->updated([['id' => $child->id, 'body' => 'Updated', 'secret' => 'Leaked again']], $post);

    expect($child->fresh()->body)->toBe('Updated')
        ->and($child->fresh()->secret)->toBeNull();
});

test('P13 owner restriction accepts any authenticatable and fails closed without an id', function () {
    secArticleSchema();
    secUserSchema();
    secBindBread();
    secUseAuthPlugin();
    secGate(['browse sec-articles']);

    $owner = SecUser::create(['name' => 'Ada', 'email' => 'ada@example.test']);
    SecArticle::create(['title' => 'Mine', 'user_id' => $owner->id]);
    SecArticle::create(['title' => 'Other', 'user_id' => 999]);

    $definition = BreadDefinition::fromArray([
        'slug' => 'sec-articles',
        'model' => SecArticle::class,
        'name' => 'Article',
        'name_plural' => 'Articles',
        'fields' => [],
        'owner_column' => 'user_id',
    ]);

    expect($definition->restrictedOwner())->toBeNull();

    SecAuthPlugin::$user = new SecPlainUser($owner->id);

    expect($definition->restrictedOwner())->toBe($owner->id)
        ->and($definition->query()->pluck('title')->all())->toBe(['Mine']);

    SecAuthPlugin::$user = new stdClass;

    expect($definition->restrictedOwner())->not->toBeNull()
        ->and($definition->query()->count())->toBe(0);
});

test('P12 relation labels obey the related bread scope in html and csv', function () {
    secArticleSchema();
    $source = secBindBread();

    $source->save([
        'slug' => 'sec-tags',
        'model' => SecTag::class,
        'name' => 'Tag',
        'name_plural' => 'Tags',
        'fields' => [],
        'scope' => 'visible',
    ]);

    $source->save([
        'slug' => 'sec-posts',
        'model' => SecArticle::class,
        'name' => 'Post',
        'name_plural' => 'Posts',
        'fields' => [
            ['name' => 'title', 'type' => 'text', 'browse' => true],
            ['name' => 'tags', 'type' => 'belongs_to_many', 'label' => 'Tags', 'browse' => true,
                'relation' => 'tags', 'model' => SecTag::class, 'label_column' => 'name'],
        ],
        'layout' => ['list' => ['title' => [], 'tags' => []]],
    ]);

    $post = SecArticle::create(['title' => 'Tagged']);
    $public = SecTag::create(['name' => 'Public']);
    $hidden = SecTag::create(['name' => 'Restricted', 'hidden' => true]);
    $post->tags()->attach([$public->id, $hidden->id]);

    Livewire::test('tardis::pages.bread.index', ['slug' => 'sec-posts'])
        ->assertSee('Public')
        ->assertDontSee('Restricted');

    $field = app(BreadManager::class)->find('sec-posts')->getField('tags');

    expect((new CsvExporter([$field]))->cell($post->fresh(), $field))->toBe('Public');
});
