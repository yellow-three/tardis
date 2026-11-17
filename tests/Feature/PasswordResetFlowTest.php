<?php

declare(strict_types=1);

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tardis\Auth\Notifications\ResetPasswordNotification;

class ResetFlowTestUser extends Authenticatable
{
    use Notifiable;

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

    Schema::dropIfExists('password_reset_tokens');
    Schema::create('password_reset_tokens', function ($table) {
        $table->string('email')->primary();
        $table->string('token');
        $table->timestamp('created_at')->nullable();
    });

    config()->set('auth.providers.users.model', ResetFlowTestUser::class);
});

function resetFlowUser(): ResetFlowTestUser
{
    return ResetFlowTestUser::create(['name' => 'Ada', 'email' => 'ada@example.test', 'password' => Hash::make('old-password')]);
}

test('forgot password mails a link to the panel reset page', function () {
    Notification::fake();
    $user = resetFlowUser();

    Livewire::test('tardis::pages.forgot-password')
        ->set('email', 'ada@example.test')
        ->call('sendResetLink')
        ->assertHasNoErrors()
        ->assertSet('status', __('tardis::auth.reset_link_sent'));

    Notification::assertSentTo($user, ResetPasswordNotification::class, function (ResetPasswordNotification $notification) use ($user) {
        $url = $notification->toMail($user)->actionUrl;

        return str_contains($url, '/admin/reset-password/'.$notification->token)
            && str_contains($url, 'email=ada%40example.test');
    });
});

test('forgot password answers the same for an unknown address', function () {
    Notification::fake();
    resetFlowUser();

    Livewire::test('tardis::pages.forgot-password')
        ->set('email', 'nobody@example.test')
        ->call('sendResetLink')
        ->assertHasNoErrors()
        ->assertSet('status', __('tardis::auth.reset_link_sent'));

    Notification::assertNothingSent();
});

test('the reset page fills in the address from the link', function () {
    $this->get(route('tardis.password.reset', ['token' => 'abc', 'email' => 'ada@example.test']))->assertOk();

    Livewire::withQueryParams(['email' => 'ada@example.test'])
        ->test('tardis::pages.reset-password', ['token' => 'abc'])
        ->assertSet('email', 'ada@example.test')
        ->assertSet('token', 'abc');
});

test('a matching confirmation resets the password and stores it hashed', function () {
    $user = resetFlowUser();
    $token = Password::createToken($user);

    Livewire::test('tardis::pages.reset-password', ['token' => $token])
        ->set('email', 'ada@example.test')
        ->set('password', 'new-password-123')
        ->set('passwordConfirmation', 'new-password-123')
        ->call('resetPassword')
        ->assertHasNoErrors()
        ->assertSet('status', __('tardis::auth.password_reset_done'));

    // The test model has no `hashed` cast, like a host that never added one.
    expect($user->fresh()->password)->not->toBe('new-password-123')
        ->and(Hash::check('new-password-123', $user->fresh()->password))->toBeTrue();
});

test('a different confirmation is refused', function () {
    $user = resetFlowUser();
    $token = Password::createToken($user);

    Livewire::test('tardis::pages.reset-password', ['token' => $token])
        ->set('email', 'ada@example.test')
        ->set('password', 'new-password-123')
        ->set('passwordConfirmation', 'something-else')
        ->call('resetPassword')
        ->assertHasErrors('passwordConfirmation')
        ->assertSet('status', null);

    expect(Hash::check('old-password', $user->fresh()->password))->toBeTrue();
});

test('an invalid token is reported as an error, not a success', function () {
    $user = resetFlowUser();

    Livewire::test('tardis::pages.reset-password', ['token' => 'not-a-real-token'])
        ->set('email', 'ada@example.test')
        ->set('password', 'new-password-123')
        ->set('passwordConfirmation', 'new-password-123')
        ->call('resetPassword')
        ->assertHasErrors('email')
        ->assertSet('status', null);

    expect(Hash::check('old-password', $user->fresh()->password))->toBeTrue();
});
