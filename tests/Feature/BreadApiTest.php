<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tardis\Bread\Sources\JsonBreadSource;
use Tardis\Contracts\Plugins\AuthorizationPlugin;
use Tardis\Models\ActivityLog;
use Tardis\Plugins\PluginManager;

class ApiTicket extends Model
{
    protected $table = 'api_tickets';

    protected $guarded = [];

    protected $hidden = ['secret_note'];
}

class ApiTicketUser extends Authenticatable
{
    protected $table = 'users';

    protected $guarded = [];
}

class ApiTicketAuthorization implements AuthorizationPlugin
{
    /** @var list<string> */
    public static array $allowed = [];

    public function name(): string
    {
        return 'api-ticket-authz';
    }

    public function can(string $ability, mixed $model = null): bool
    {
        return in_array($ability, static::$allowed, true);
    }

    public function authorize(string $ability, mixed $model = null): void
    {
        abort_unless($this->can($ability, $model), 403);
    }
}

function apiAllow(array $abilities): void
{
    ApiTicketAuthorization::$allowed = ['access admin', ...$abilities];
}

function loadApiRoutes(): void
{
    Route::group([], __DIR__.'/../../routes/api.php');
    app('router')->getRoutes()->refreshNameLookups();
}

beforeEach(function () {
    Schema::create('users', function ($table) {
        $table->id();
        $table->string('name')->nullable();
        $table->timestamps();
    });
    Schema::create('api_tickets', function ($table) {
        $table->id();
        $table->string('title');
        $table->integer('budget')->nullable();
        $table->string('secret_note')->nullable();
        $table->unsignedBigInteger('user_id')->nullable();
        $table->timestamps();
    });
    $this->artisan('migrate');
    config()->set('auth.providers.users.model', ApiTicketUser::class);
    config()->set('tardis.api.enabled', true);
    config()->set('tardis.api.middleware', ['api']);
    loadApiRoutes();

    $this->breadPath = sys_get_temp_dir().'/tardis-api-'.uniqid();
    app()->instance(JsonBreadSource::class, $source = new JsonBreadSource($this->breadPath));
    $source->save([
        'slug' => 'tickets',
        'model' => ApiTicket::class,
        'name' => 'Ticket',
        'name_plural' => 'Tickets',
        'owner_column' => 'user_id',
        'search_key' => 'title',
        'fields' => [
            ['name' => 'title', 'type' => 'text', 'label' => 'Title', 'validation' => ['required', 'max:20']],
            ['name' => 'budget', 'type' => 'number', 'label' => 'Budget', 'ability' => 'see budgets'],
            ['name' => 'secret_note', 'type' => 'text', 'label' => 'Note'],
        ],
    ]);

    $manager = app(PluginManager::class);
    $manager->register('api-ticket-authz', ApiTicketAuthorization::class);
    $manager->enable('api-ticket-authz');

    $this->ada = ApiTicketUser::create(['name' => 'Ada']);
    $this->bob = ApiTicketUser::create(['name' => 'Bob']);
    $this->adas = ApiTicket::create(['title' => 'Ada ticket', 'budget' => 111, 'secret_note' => 'shh', 'user_id' => $this->ada->id]);
    $this->bobs = ApiTicket::create(['title' => 'Bob ticket', 'budget' => 222, 'user_id' => $this->bob->id]);
});

afterEach(fn () => File::deleteDirectory($this->breadPath));

$crud = ['browse tickets', 'read tickets', 'edit tickets', 'add tickets', 'delete tickets'];

test('a guest gets 401 and a user without access admin 403, in JSON', function () {
    $this->getJson('/api/tardis/tickets')->assertUnauthorized()->assertJsonPath('message', __('tardis::api.unauthenticated'));

    ApiTicketAuthorization::$allowed = ['browse tickets'];
    $this->actingAs($this->ada)->getJson('/api/tardis/tickets')->assertForbidden();
});

test('a guest without an Accept header gets a JSON 401, not a redirect to login', function () {
    // Testbench has no Sanctum guard; a host that installs it does, and that
    // is the setup where the missing Accept header previously caused a 500.
    config()->set('auth.guards.sanctum', ['driver' => 'session', 'provider' => 'users']);
    config()->set('tardis.api.middleware', ['api', 'auth:sanctum', 'throttle:60,1']);
    loadApiRoutes();

    $this->get('/api/tardis/tickets')
        ->assertUnauthorized()
        ->assertHeader('Content-Type', 'application/json')
        ->assertJsonStructure(['message']);
});

test('the list shows only the user\'s own records, without hidden fields and attributes', function () use ($crud) {
    apiAllow($crud);

    $response = $this->actingAs($this->ada)->getJson('/api/tardis/tickets')->assertOk();

    expect($response->json('data'))->toHaveCount(1)
        ->and($response->json('data.0.title'))->toBe('Ada ticket')
        ->and($response->json('data.0'))->not->toHaveKey('budget')
        ->and($response->json('data.0'))->not->toHaveKey('secret_note')
        ->and($response->json('meta.total'))->toBe(1);

    apiAllow([...$crud, 'see budgets']);
    expect($this->actingAs($this->ada)->getJson('/api/tardis/tickets')->json('data.0.budget'))->toBe(111);
});

test('search, sort and paging use the panel listing', function () use ($crud) {
    apiAllow([...$crud, 'manage tickets']);
    ApiTicket::create(['title' => 'Another', 'user_id' => $this->ada->id]);

    $this->actingAs($this->ada)->getJson('/api/tardis/tickets?search=ticket&sort=title&direction=desc&per_page=1')
        ->assertOk()
        ->assertJsonPath('data.0.title', 'Bob ticket')
        ->assertJsonPath('meta.total', 2)
        ->assertJsonPath('meta.per_page', 1);
});

test('another user\'s record is 404 and a missing ability is 403', function () use ($crud) {
    apiAllow($crud);

    $this->actingAs($this->ada)->getJson("/api/tardis/tickets/{$this->bobs->id}")->assertNotFound();
    $this->actingAs($this->ada)->getJson("/api/tardis/tickets/{$this->adas->id}")->assertOk()->assertJsonPath('data.title', 'Ada ticket');

    apiAllow(['browse tickets']);
    $this->actingAs($this->ada)->getJson("/api/tardis/tickets/{$this->adas->id}")->assertForbidden();
    $this->actingAs($this->ada)->postJson('/api/tardis/tickets', ['title' => 'x'])->assertForbidden();
    $this->actingAs($this->ada)->deleteJson("/api/tardis/tickets/{$this->adas->id}")->assertForbidden();
});

test('creating validates with the definition and assigns the owner', function () use ($crud) {
    apiAllow($crud);

    $this->actingAs($this->ada)->postJson('/api/tardis/tickets', ['title' => str_repeat('x', 30)])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['title']);

    $response = $this->actingAs($this->ada)->postJson('/api/tardis/tickets', ['title' => 'New', 'budget' => 999, 'user_id' => $this->bob->id])
        ->assertCreated()
        ->assertJsonPath('data.title', 'New');

    $created = ApiTicket::find($response->json('data.id'));

    // The budget field is behind an ability the user lacks; the owner is the
    // signed-in user whatever the body says.
    expect($created->budget)->toBeNull()
        ->and($created->user_id)->toBe($this->ada->id)
        ->and(ActivityLog::query()->where('model_type', ApiTicket::class)->where('action', 'created')->exists())->toBeTrue();
});

test('PATCH changes only the fields sent, PUT validates every field', function () use ($crud) {
    apiAllow($crud);

    $this->actingAs($this->ada)->patchJson("/api/tardis/tickets/{$this->adas->id}", ['secret_note' => 'new'])
        ->assertOk();
    expect($this->adas->refresh()->title)->toBe('Ada ticket')->and($this->adas->secret_note)->toBe('new');

    $this->actingAs($this->ada)->putJson("/api/tardis/tickets/{$this->adas->id}", ['secret_note' => 'x'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['title']);

    $this->actingAs($this->ada)->patchJson("/api/tardis/tickets/{$this->bobs->id}", ['title' => 'Mine now'])->assertNotFound();
    expect($this->bobs->refresh()->title)->toBe('Bob ticket');
});

test('deleting goes through the delete action', function () use ($crud) {
    apiAllow($crud);

    $this->actingAs($this->ada)->deleteJson("/api/tardis/tickets/{$this->adas->id}")->assertNoContent();

    expect(ApiTicket::find($this->adas->id))->toBeNull()
        ->and(ActivityLog::query()->where('action', 'deleted')->exists())->toBeTrue();
});

test('the definitions list shows what the user may browse, and tardis.api.only limits it', function () use ($crud) {
    apiAllow($crud);

    $this->actingAs($this->ada)->getJson('/api/tardis')
        ->assertOk()
        ->assertJsonPath('data.0.slug', 'tickets')
        ->assertJsonPath('data.0.fields', fn (array $fields) => ! in_array('budget', array_column($fields, 'name'), true));

    config()->set('tardis.api.only', ['posts']);
    $this->actingAs($this->ada)->getJson('/api/tardis')->assertJsonCount(0, 'data');
    $this->actingAs($this->ada)->getJson('/api/tardis/tickets')->assertNotFound();
});

test('the API routes exist only when enabled', function () {
    expect(Route::has('tardis.api.index'))->toBeTrue();

    $this->refreshApplication();

    expect(Route::has('tardis.api.index'))->toBeFalse();
});
