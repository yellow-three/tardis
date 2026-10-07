<?php

declare(strict_types=1);

use Illuminate\Cache\RateLimiter;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tardis\Manager\PluginManager;
use Tardis\Plugins\AuthenticationPlugin;

class LoginTestUser extends Authenticatable
{
    protected $table = 'users';

    protected $guarded = [];
}

beforeEach(function () {
    Schema::dropIfExists('users');
    Schema::create('users', function (Blueprint $table) {
        $table->id();
        $table->string('name')->nullable();
        $table->string('email')->unique();
        $table->string('password');
        $table->rememberToken();
        $table->timestamps();
    });

    config()->set('auth.providers.users.model', LoginTestUser::class);

    LoginTestUser::create([
        'name' => 'Ada',
        'email' => 'ada@example.test',
        'password' => Hash::make('correct-horse'),
    ]);
});

afterEach(function () {
    Schema::dropIfExists('users');
    app(RateLimiter::class)->clear('ada@example.test|127.0.0.1');
});

test('a valid login signs the user in and redirects to the dashboard without an error', function () {
    Livewire::test('tardis::pages.login')
        ->set('email', 'ada@example.test')
        ->set('password', 'correct-horse')
        ->call('login')
        ->assertRedirect(route('tardis.dashboard'))
        ->assertSet('error', null);

    expect(auth()->check())->toBeTrue();
});

test('a wrong password shows the failure message', function () {
    Livewire::test('tardis::pages.login')
        ->set('email', 'ada@example.test')
        ->set('password', 'nope')
        ->call('login')
        ->assertSet('error', __('auth.failed'))
        ->assertNoRedirect();

    expect(auth()->check())->toBeFalse();
});

test('repeated failed logins are throttled even when the password is then correct', function () {
    $component = Livewire::test('tardis::pages.login')->set('email', 'ada@example.test');

    foreach (range(1, 5) as $attempt) {
        $component->set('password', 'wrong-'.$attempt)->call('login');
    }

    $component->set('password', 'correct-horse')
        ->call('login')
        ->assertNoRedirect();

    expect(auth()->check())->toBeFalse()
        ->and($component->get('error'))->toContain('Too many');
});

test('a successful login clears the failed attempt counter', function () {
    $component = Livewire::test('tardis::pages.login')->set('email', 'ada@example.test');

    foreach (range(1, 3) as $attempt) {
        $component->set('password', 'wrong-'.$attempt)->call('login');
    }

    $component->set('password', 'correct-horse')->call('login')->assertRedirect();

    expect(app(RateLimiter::class)->attempts('ada@example.test|127.0.0.1'))->toBe(0);
});

class LoginTestAuthPlugin extends AuthenticationPlugin
{
    /** @var array<int, array{credentials: array, remember: bool}> */
    public static array $attempts = [];

    public static bool $succeeds = true;

    public function attempt(array $credentials, bool $remember = false): bool
    {
        static::$attempts[] = ['credentials' => $credentials, 'remember' => $remember];

        return static::$succeeds;
    }

    public function redirectTo(): string
    {
        return '/custom-landing';
    }
}

function useLoginAuthPlugin(bool $succeeds): void
{
    LoginTestAuthPlugin::$attempts = [];
    LoginTestAuthPlugin::$succeeds = $succeeds;

    $manager = app(PluginManager::class);
    $manager->register('login-test-auth', LoginTestAuthPlugin::class);
    $manager->enableByDefault('login-test-auth');
}

test('the login form delegates to the enabled authentication plugin', function () {
    useLoginAuthPlugin(true);

    Livewire::test('tardis::pages.login')
        ->set('email', 'ada@example.test')
        ->set('password', 'whatever')
        ->set('remember', true)
        ->call('login')
        ->assertRedirect('/custom-landing');

    expect(LoginTestAuthPlugin::$attempts)->toBe([[
        'credentials' => ['email' => 'ada@example.test', 'password' => 'whatever'],
        'remember' => true,
    ]]);
});

test('a refusal from the authentication plugin counts as a failed attempt', function () {
    useLoginAuthPlugin(false);

    $component = Livewire::test('tardis::pages.login')
        ->set('email', 'ada@example.test')
        ->set('password', 'whatever')
        ->call('login')
        ->assertNoRedirect()
        ->assertSet('error', __('auth.failed'));

    expect(app(RateLimiter::class)->attempts('ada@example.test|127.0.0.1'))->toBe(1);
});

test('the built-in plugin signs the user in on the guard it declares', function () {
    expect(app(PluginManager::class)->authenticationPlugin())->toBeInstanceOf(AuthenticationPlugin::class);

    Livewire::test('tardis::pages.login')
        ->set('email', 'ada@example.test')
        ->set('password', 'correct-horse')
        ->call('login')
        ->assertRedirect(route('tardis.dashboard'));

    expect(auth()->guard('web')->check())->toBeTrue();
});

test('a plugin registered after the built-in one takes over authentication', function () {
    useLoginAuthPlugin(true);

    expect(app(PluginManager::class)->authenticationPlugin())->toBeInstanceOf(LoginTestAuthPlugin::class);
});
