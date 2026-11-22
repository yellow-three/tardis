<?php

declare(strict_types=1);

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tardis\Auth\TwoFactor\Totp;
use Tardis\Auth\TwoFactor\TwoFactorAuthentication;
use Tardis\Models\TwoFactor;

class TwoFactorUser extends Authenticatable
{
    protected $table = 'users';

    protected $guarded = [];
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
    config()->set('auth.providers.users.model', TwoFactorUser::class);
    $this->artisan('migrate');

    $this->user = TwoFactorUser::create(['name' => 'Ada', 'email' => 'ada@example.test', 'password' => Hash::make('secret-pass')]);
});

function currentCode(TwoFactorUser $user): string
{
    return Totp::code(TwoFactor::query()->where('user_id', $user->id)->value('secret'), Totp::currentStep());
}

function turnOnTwoFactor(TwoFactorUser $user): array
{
    $service = app(TwoFactorAuthentication::class);
    $seed = $service->start($user);

    return $service->confirm($user, Totp::code($seed, Totp::currentStep() - 1));
}

test('the profile turns two-factor on with a confirmed code and shows recovery codes once', function () {
    $this->actingAs($this->user);

    $page = Livewire::test('tardis::pages.profile')
        ->call('startTwoFactor')
        ->assertSee('Key:');

    $page->set('twoFactorCode', '000000')->call('confirmTwoFactor')->assertHasErrors('twoFactorCode');
    expect(app(TwoFactorAuthentication::class)->enabledFor($this->user))->toBeFalse();

    $page->set('twoFactorCode', currentCode($this->user))->call('confirmTwoFactor')->assertHasNoErrors();

    expect($page->get('recoveryCodes'))->toHaveCount(8)
        ->and(app(TwoFactorAuthentication::class)->enabledFor($this->user))->toBeTrue()
        // Stored hashed, encrypted secret.
        ->and(TwoFactor::query()->value('recovery_codes')[0])->not->toBe($page->get('recoveryCodes')[0])
        ->and(DB::table('tardis_two_factor')->value('secret'))->not->toBe(TwoFactor::query()->first()->secret);
});

test('signing in with two-factor on asks for the code before the session is the user\'s', function () {
    turnOnTwoFactor($this->user);

    Livewire::test('tardis::pages.login')
        ->set('email', 'ada@example.test')->set('password', 'secret-pass')
        ->call('login')
        ->assertRedirect(route('tardis.two-factor'));

    expect(auth()->check())->toBeFalse()
        ->and(session('tardis.two_factor.id'))->toBe($this->user->id);

    Livewire::test('tardis::pages.two-factor-challenge')
        ->set('code', '111111')->call('verify')
        ->assertSee('That code is not valid.');

    expect(auth()->check())->toBeFalse();

    Livewire::test('tardis::pages.two-factor-challenge')
        ->set('code', currentCode($this->user))->call('verify')
        ->assertRedirect(route('tardis.dashboard'));

    expect(auth()->id())->toBe($this->user->id)
        ->and(session('tardis.two_factor'))->toBeNull();
});

test('a recovery code signs in once', function () {
    $codes = turnOnTwoFactor($this->user);
    $service = app(TwoFactorAuthentication::class);

    expect($service->verify($this->user, strtolower($codes[0])))->toBeTrue()
        ->and($service->verify($this->user, $codes[0]))->toBeFalse()
        ->and($service->remainingRecoveryCodes($this->user))->toBe(7);
});

test('the challenge page without a pending sign-in goes back to the login page', function () {
    Livewire::test('tardis::pages.two-factor-challenge')->assertRedirect(route('tardis.login'));
});

test('too many wrong codes are throttled', function () {
    turnOnTwoFactor($this->user);
    session()->put('tardis.two_factor', ['id' => $this->user->id, 'remember' => false, 'guard' => 'web', 'redirect' => route('tardis.dashboard')]);

    $page = Livewire::test('tardis::pages.two-factor-challenge');

    foreach (range(1, 5) as $attempt) {
        $page->set('code', '000000')->call('verify');
    }

    $page->set('code', currentCode($this->user))->call('verify')->assertSee('Too many');

    expect(auth()->check())->toBeFalse();
});

test('turning it off takes a valid code', function () {
    turnOnTwoFactor($this->user);
    $this->actingAs($this->user);

    $page = Livewire::test('tardis::pages.profile')->set('twoFactorCode', '000000')->call('disableTwoFactor')->assertHasErrors('twoFactorCode');
    expect(app(TwoFactorAuthentication::class)->enabledFor($this->user))->toBeTrue();

    $page->set('twoFactorCode', currentCode($this->user))->call('disableTwoFactor')->assertHasNoErrors();
    expect(app(TwoFactorAuthentication::class)->enabledFor($this->user))->toBeFalse();
});

test('a user without two-factor signs in directly', function () {
    Livewire::test('tardis::pages.login')
        ->set('email', 'ada@example.test')->set('password', 'secret-pass')
        ->call('login')
        ->assertRedirect(route('tardis.dashboard'));

    expect(auth()->id())->toBe($this->user->id);
});
