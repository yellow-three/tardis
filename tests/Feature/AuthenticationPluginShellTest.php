<?php

declare(strict_types=1);

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Schema;
use Tardis\Auth\SessionAuthenticationPlugin;
use Tardis\Plugins\PluginManager;

class PanelUserShellTestUser extends Authenticatable
{
    protected $table = 'users';

    protected $guarded = [];
}

class ShellTestAuthentication extends SessionAuthenticationPlugin
{
    public static ?string $avatar = null;

    public function nameField(): string
    {
        return 'nickname';
    }

    public function avatar(mixed $user): ?string
    {
        return static::$avatar;
    }
}

beforeEach(function () {
    Schema::dropIfExists('users');
    Schema::create('users', function ($table) {
        $table->id();
        $table->string('name')->nullable();
        $table->string('nickname')->nullable();
        $table->string('email')->nullable();
        $table->string('password')->nullable();
        $table->rememberToken();
        $table->timestamps();
    });
    config()->set('auth.providers.users.model', PanelUserShellTestUser::class);

    $manager = app(PluginManager::class);
    $manager->register('shell-auth', ShellTestAuthentication::class);
    $manager->enableByDefault('shell-auth');
});

test('the shell names the user by the plugin name field and shows its avatar', function () {
    ShellTestAuthentication::$avatar = 'https://example.test/ada.png';
    $user = PanelUserShellTestUser::create(['name' => 'Ada Lovelace', 'nickname' => 'Countess', 'email' => 'ada@example.test']);

    $this->actingAs($user)->get(route('tardis.dashboard'))
        ->assertOk()
        ->assertSee('Countess')
        ->assertSee('src="https://example.test/ada.png"', false);
});

test('an avatar that is not an http(s) or root-relative URL is not used', function () {
    ShellTestAuthentication::$avatar = 'javascript:alert(1)';
    $user = PanelUserShellTestUser::create(['nickname' => 'Countess']);

    $this->actingAs($user)->get(route('tardis.dashboard'))
        ->assertOk()
        ->assertDontSee('javascript:alert(1)', false);
});
