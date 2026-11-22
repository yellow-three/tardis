<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tardis\Bread\BreadManager;
use Tardis\Bread\Sources\JsonBreadSource;
use Tardis\Contracts\Plugins\AuthorizationPlugin;
use Tardis\Models\Permission;
use Tardis\Plugins\PluginManager;

class OwnedTicket extends Model
{
    protected $table = 'owned_tickets';

    protected $guarded = [];
}

class TicketUser extends Authenticatable
{
    protected $table = 'users';

    protected $guarded = [];
}

/** Grants the abilities in $allowed, nothing else. */
class TicketAuthorization implements AuthorizationPlugin
{
    /** @var list<string> */
    public static array $allowed = [];

    public function name(): string
    {
        return 'ticket-authz';
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

function ticketAbilities(array $abilities): void
{
    TicketAuthorization::$allowed = $abilities;
}

beforeEach(function () {
    Schema::create('users', function ($table) {
        $table->id();
        $table->string('name')->nullable();
        $table->timestamps();
    });
    Schema::create('owned_tickets', function ($table) {
        $table->id();
        $table->string('title');
        $table->integer('budget')->nullable();
        $table->unsignedBigInteger('user_id')->nullable();
        $table->timestamps();
    });
    config()->set('auth.providers.users.model', TicketUser::class);

    $this->breadPath = sys_get_temp_dir().'/tardis-owned-'.uniqid();
    app()->instance(JsonBreadSource::class, $source = new JsonBreadSource($this->breadPath));
    $source->save([
        'slug' => 'tickets',
        'model' => OwnedTicket::class,
        'name' => 'Ticket',
        'name_plural' => 'Tickets',
        'owner_column' => 'user_id',
        'fields' => [
            ['name' => 'title', 'type' => 'text', 'label' => 'Title'],
            ['name' => 'budget', 'type' => 'number', 'label' => 'Budget', 'ability' => 'see budgets'],
        ],
        'layout' => ['list' => ['title', ['name' => 'budget', 'label' => 'Budget']]],
    ]);

    $manager = app(PluginManager::class);
    $manager->register('ticket-authz', TicketAuthorization::class);
    $manager->enable('ticket-authz');

    $this->ada = TicketUser::create(['name' => 'Ada']);
    $this->bob = TicketUser::create(['name' => 'Bob']);
    $this->adas = OwnedTicket::create(['title' => 'Ada ticket', 'budget' => 111, 'user_id' => $this->ada->id]);
    $this->bobs = OwnedTicket::create(['title' => 'Bob ticket', 'budget' => 222, 'user_id' => $this->bob->id]);
});

afterEach(fn () => File::deleteDirectory($this->breadPath));

$crud = ['browse tickets', 'read tickets', 'edit tickets', 'add tickets', 'delete tickets'];

test('a user without manage sees and opens only their own records', function () use ($crud) {
    ticketAbilities($crud);
    $this->actingAs($this->ada);

    Livewire::test('tardis::pages.bread.index', ['slug' => 'tickets'])
        ->assertSee('Ada ticket')->assertDontSee('Bob ticket');

    Livewire::test('tardis::pages.bread.read', ['slug' => 'tickets', 'id' => $this->bobs->id])->assertNotFound();
    Livewire::test('tardis::pages.bread.edit', ['slug' => 'tickets', 'id' => $this->bobs->id])->assertNotFound();
});

test('manage sees every record', function () use ($crud) {
    ticketAbilities([...$crud, 'manage tickets']);
    $this->actingAs($this->ada);

    Livewire::test('tardis::pages.bread.index', ['slug' => 'tickets'])
        ->assertSee('Ada ticket')->assertSee('Bob ticket');
});

test('a new record belongs to its creator, and a limited user cannot give it away', function () use ($crud) {
    ticketAbilities($crud);
    $this->actingAs($this->ada);

    Livewire::test('tardis::pages.bread.create', ['slug' => 'tickets'])
        ->set('form.title', 'New one')->call('save')->assertHasNoErrors();

    expect(OwnedTicket::where('title', 'New one')->value('user_id'))->toBe($this->ada->id);

    $import = fn (string $csv) => Livewire::test('tardis::pages.bread.index', ['slug' => 'tickets'])
        ->set('importFile', UploadedFile::fake()->createWithContent('t.csv', $csv))
        ->call('import')
        ->get('importReport');

    // Bob's record is out of her reach: the row is an error and nothing is written.
    $report = $import("id,title\n{$this->adas->id},Renamed\n{$this->bobs->id},Stolen\n");

    expect(array_keys($report['errors']))->toBe([3])
        ->and($this->adas->fresh()->title)->toBe('Ada ticket')
        ->and($this->bobs->fresh()->title)->toBe('Bob ticket');

    $import("id,title\n{$this->adas->id},Renamed\n");

    expect($this->adas->fresh()->title)->toBe('Renamed')->and($this->adas->fresh()->user_id)->toBe($this->ada->id);
});

test('a field with an ability is hidden and not written without it', function () use ($crud) {
    ticketAbilities([...$crud, 'manage tickets']);
    $this->actingAs($this->ada);

    Livewire::test('tardis::pages.bread.index', ['slug' => 'tickets'])
        ->assertSee('Ada ticket')->assertDontSee('111')->assertDontSee('Budget');

    Livewire::test('tardis::pages.bread.read', ['slug' => 'tickets', 'id' => $this->adas->id])
        ->assertDontSee('111');

    $edit = Livewire::test('tardis::pages.bread.edit', ['slug' => 'tickets', 'id' => $this->adas->id]);

    expect($edit->get('form'))->not->toHaveKey('budget');

    $edit->set('form.title', 'Edited')->set('form.budget', 999)->call('save');

    expect($this->adas->fresh()->only('title', 'budget'))->toBe(['title' => 'Edited', 'budget' => 111]);

    $csv = base64_decode(Livewire::test('tardis::pages.bread.index', ['slug' => 'tickets'])->call('export')->effects['download']['content']);

    expect($csv)->not->toContain('Budget')->not->toContain('111');
});

test('with the ability the field is shown and saved', function () use ($crud) {
    ticketAbilities([...$crud, 'manage tickets', 'see budgets']);
    $this->actingAs($this->ada);

    Livewire::test('tardis::pages.bread.index', ['slug' => 'tickets'])->assertSee('111')->assertSee('222');

    Livewire::test('tardis::pages.bread.edit', ['slug' => 'tickets', 'id' => $this->adas->id])
        ->set('form.budget', 500)->call('save')->assertHasNoErrors();

    expect($this->adas->fresh()->budget)->toBe(500);
});

test('saving the definition creates the manage and field abilities', function () {
    $this->artisan('migrate');
    app(BreadManager::class)->save(json_decode(File::get($this->breadPath.'/tickets.json'), true));

    expect(Permission::query()->whereIn('slug', ['manage tickets', 'see budgets'])->count())->toBe(2);
});
