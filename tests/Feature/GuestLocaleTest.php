<?php

declare(strict_types=1);

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Livewire\Mechanisms\PersistentMiddleware\PersistentMiddleware;
use Tardis\Http\Middleware\SetPanelLocale;
use Tardis\Support\UserPreferences;

class GuestLocaleUser extends Authenticatable
{
    protected $table = 'users';

    protected $guarded = [];
}

beforeEach(function () {
    Schema::create('users', function ($table) {
        $table->id();
        $table->string('name')->nullable();
        $table->string('email')->nullable();
        $table->string('password')->nullable();
        $table->rememberToken();
        $table->timestamps();
    });
    config()->set('auth.providers.users.model', GuestLocaleUser::class);
    app()->setLocale('en');
});

test('the login page follows the browser language', function () {
    $this->withHeader('Accept-Language', 'tr-TR,tr;q=0.9,en;q=0.5')
        ->get(route('tardis.login'))
        ->assertOk()
        ->assertSee('<html lang="tr"', false);

    app()->setLocale('en');
    $this->withHeader('Accept-Language', 'ja-JP,ja;q=0.9')
        ->get(route('tardis.login'))
        ->assertSee('<html lang="en"', false);
});

test('the language chosen on the login page is kept by session and cookie', function () {
    $this->post(route('tardis.preferences.locale'), ['locale' => 'de'])
        ->assertRedirect()
        ->assertCookie(SetPanelLocale::COOKIE, 'de');

    $this->get(route('tardis.login'))->assertSee('<html lang="de"', false)->assertSee('id="tardis-auth-locale"', false);

    $this->flushSession();
    $this->withCookie(SetPanelLocale::COOKIE, 'fr')
        ->get(route('tardis.login'))
        ->assertSee('<html lang="fr"', false);
});

test('a signed-in user\'s saved language is remembered for the next sign-in', function () {
    $user = GuestLocaleUser::create(['name' => 'Ada', 'email' => 'ada@example.test', 'password' => Hash::make('secret-pass')]);
    app(UserPreferences::class)->set($user->id, 'locale', 'tr');

    $this->actingAs($user)->get(route('tardis.dashboard'))->assertCookie(SetPanelLocale::COOKIE, 'tr');
});

test('Livewire updates on the sign-in pages keep the visitor\'s language', function () {
    expect(app(PersistentMiddleware::class)->getPersistentMiddleware())
        ->toContain(SetPanelLocale::class);
});

test('a garbage cookie or header is ignored', function () {
    $this->withCookie(SetPanelLocale::COOKIE, '../../etc')
        ->withHeader('Accept-Language', '***')
        ->get(route('tardis.login'))
        ->assertOk()
        ->assertSee('<html lang="en"', false);
});
