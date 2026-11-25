<?php

declare(strict_types=1);

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tardis\Auth\BrowserSessions;
use Tardis\Auth\Impersonation;
use Tardis\Contracts\Plugins\AuthorizationPlugin;
use Tardis\Models\ActivityLog;
use Tardis\Plugins\PluginManager;

class ImpersonationUser extends Authenticatable
{
    protected $table = 'users';

    protected $guarded = [];
}

class NoImpersonationAuthorization implements AuthorizationPlugin
{
    public function name(): string
    {
        return 'no-impersonation';
    }

    public function can(string $ability, mixed $model = null): bool
    {
        return $ability !== 'impersonate users';
    }

    public function authorize(string $ability, mixed $model = null): void
    {
        abort_unless($this->can($ability, $model), 403);
    }
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
    config()->set('auth.providers.users.model', ImpersonationUser::class);
    $this->artisan('migrate');

    $this->admin = ImpersonationUser::create(['name' => 'Ada', 'email' => 'ada@example.test', 'password' => Hash::make('ada-password')]);
    $this->editor = ImpersonationUser::create(['name' => 'Bob', 'email' => 'bob@example.test', 'password' => Hash::make('bob-password')]);
});

test('an administrator signs in as another user and comes back', function () {
    $this->actingAs($this->admin);

    Livewire::test('tardis::pages.users')
        ->assertSee('Sign in as')
        ->call('impersonate', $this->editor->id)
        ->assertRedirect(route('tardis.dashboard'));

    expect(auth()->id())->toBe($this->editor->id)
        ->and(app(Impersonation::class)->impersonator()['id'])->toBe($this->admin->id);

    $this->get(route('tardis.dashboard'))->assertOk()->assertSee('You are signed in as Bob.');

    $this->post(route('tardis.impersonation.leave'))->assertRedirect(route('tardis.users.index'));

    expect(auth()->id())->toBe($this->admin->id)
        ->and(app(Impersonation::class)->active())->toBeFalse()
        ->and(ActivityLog::query()->whereIn('action', ['impersonation_started', 'impersonation_ended'])->count())->toBe(2);
});

test('nobody signs in as themselves, or again while already signed in as someone', function () {
    $impersonation = app(Impersonation::class);

    expect($impersonation->mayImpersonate($this->admin, $this->admin))->toBeFalse();

    session()->put(Impersonation::SESSION_KEY, ['id' => $this->admin->id, 'guard' => 'web']);

    expect($impersonation->mayImpersonate($this->admin, $this->editor))->toBeFalse();
});

test('without the ability there is no button and the action is refused', function () {
    config()->set('tardis.authorization.enabled', true);
    $plugins = app(PluginManager::class);
    $plugins->register('no-impersonation', NoImpersonationAuthorization::class);
    $plugins->enable('no-impersonation');
    $this->actingAs($this->admin);

    Livewire::test('tardis::pages.users')
        ->assertDontSee('Sign in as')
        ->call('impersonate', $this->editor->id)
        ->assertForbidden();

    expect(auth()->id())->toBe($this->admin->id);
});

test('leaving without having started goes to the dashboard', function () {
    $this->actingAs($this->admin)->post(route('tardis.impersonation.leave'))->assertRedirect(route('tardis.dashboard'));
});

test('the profile lists the sessions and signs out the others with the password', function () {
    config()->set('session.driver', 'database');
    Schema::create('sessions', function ($table) {
        $table->string('id')->primary();
        $table->foreignId('user_id')->nullable()->index();
        $table->string('ip_address', 45)->nullable();
        $table->text('user_agent')->nullable();
        $table->longText('payload');
        $table->integer('last_activity')->index();
    });
    $this->actingAs($this->admin);
    $this->admin->forceFill(['remember_token' => 'old-token'])->save();

    $current = session()->getId();
    DB::table('sessions')->insert([
        ['id' => $current, 'user_id' => $this->admin->id, 'ip_address' => '10.0.0.1', 'user_agent' => 'Mozilla/5.0 (X11; Linux x86_64) Firefox/140.0', 'payload' => '', 'last_activity' => time()],
        ['id' => 'other-phone', 'user_id' => $this->admin->id, 'ip_address' => '10.0.0.2', 'user_agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0) Safari/605.1', 'payload' => '', 'last_activity' => time() - 3600],
        ['id' => 'bobs', 'user_id' => $this->editor->id, 'ip_address' => '10.0.0.3', 'user_agent' => 'x', 'payload' => '', 'last_activity' => time()],
    ]);

    $page = Livewire::test('tardis::pages.profile')
        ->assertSee('Firefox · Linux')->assertSee('Safari · iOS')->assertSee('This session')
        ->assertDontSee('10.0.0.3');

    $page->set('sessionsPassword', 'wrong')->call('logoutOtherSessions')->assertHasErrors('sessionsPassword');
    expect(DB::table('sessions')->count())->toBe(3);

    $page->set('sessionsPassword', 'ada-password')->call('logoutOtherSessions')->assertHasNoErrors();

    expect(DB::table('sessions')->pluck('id')->sort()->values()->all())->toBe(collect(['bobs', $current])->sort()->values()->all())
        ->and($this->admin->fresh()->remember_token)->not->toBe('old-token');
});

test('without the database driver the profile says why there is no list', function () {
    config()->set('session.driver', 'array');
    $this->actingAs($this->admin);

    expect(app(BrowserSessions::class)->available())->toBeFalse();

    Livewire::test('tardis::pages.profile')->assertSee('database session driver');
});

test('signing in as someone who may not open the panel comes straight back', function () {
    config()->set('tardis.authorization.enabled', true);
    $plugins = app(PluginManager::class);
    $plugins->register('admin-only', AdminOnlyAuthorization::class);
    $plugins->enable('admin-only');
    AdminOnlyAuthorization::$admin = $this->admin->id;
    $this->actingAs($this->admin);

    app(Impersonation::class)->start($this->editor);

    $this->get(route('tardis.dashboard'))
        ->assertRedirect(route('tardis.users.index'))
        ->assertSessionHas('error');

    expect(auth()->id())->toBe($this->admin->id)->and(app(Impersonation::class)->active())->toBeFalse();
});

/** Everything for one user id, nothing for anyone else. */
class AdminOnlyAuthorization implements AuthorizationPlugin
{
    public static int|string|null $admin = null;

    public function name(): string
    {
        return 'admin-only';
    }

    public function can(string $ability, mixed $model = null): bool
    {
        return auth()->id() === static::$admin;
    }

    public function authorize(string $ability, mixed $model = null): void
    {
        abort_unless($this->can($ability, $model), 403);
    }
}
