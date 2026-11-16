<?php

declare(strict_types=1);

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Schema;

class UpdateMiddlewareTestUser extends Authenticatable
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

    config()->set('auth.providers.users.model', UpdateMiddlewareTestUser::class);
});

/**
 * Livewire sends every request after the first to its own update endpoint and
 * re-applies only the route middleware it was told to persist. The panel's
 * guard has to be one of them, or a page that was opened while signed in keeps
 * answering after the session is gone.
 */
test('the panel middleware guards Livewire update requests too', function () {
    $user = UpdateMiddlewareTestUser::create(['name' => 'Ada', 'email' => 'ada@example.test']);

    $html = $this->actingAs($user)->get(route('tardis.search'))->assertOk()->getContent();

    preg_match('/wire:snapshot="([^"]+)"/', $html, $matches);
    $snapshot = html_entity_decode($matches[1] ?? '', ENT_QUOTES);

    expect($snapshot)->not->toBe('');

    auth()->logout();
    $this->app['auth']->forgetGuards();

    $response = $this->withHeaders(['X-Livewire' => '1'])->postJson(app('livewire')->getUpdateUri(), [
        'components' => [[
            'snapshot' => $snapshot,
            'updates' => (object) [],
            'calls' => [['path' => '', 'method' => 'clearSearch', 'params' => []]],
        ]],
    ]);

    expect($response->getStatusCode())->not->toBe(200);
});
