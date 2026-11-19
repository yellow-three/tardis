<?php

declare(strict_types=1);

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Schema;
use Tardis\Assets\AssetManager;

class AssetRenderTestUser extends Authenticatable
{
    protected $table = 'users';

    protected $guarded = [];
}

/**
 * AssetManager is a singleton (plugins register assets on it at boot), but
 * "styles already written" belongs to one page. When the process serves a
 * second request (Octane, a queue worker rendering mail, or simply a test)
 * the second page used to come out without its stylesheet and core script.
 */
test('every request gets the panel stylesheet and core script', function () {
    Schema::dropIfExists('users');
    Schema::create('users', function ($table) {
        $table->id();
        $table->string('name')->nullable();
        $table->string('email')->nullable();
        $table->rememberToken();
        $table->timestamps();
    });
    config()->set('auth.providers.users.model', AssetRenderTestUser::class);
    $user = AssetRenderTestUser::create(['name' => 'Ada']);

    foreach ([1, 2] as $request) {
        $html = $this->actingAs($user)->get(route('tardis.dashboard'))->assertOk()->getContent();

        // toContain() treats every argument as a needle, so no message here.
        [$css, $js] = app(AssetManager::class)->coreAssets();
        expect($html)->toContain('/_assets/'.$css->hash().'.css')->toContain('/_assets/'.$js->hash().'.js');
    }
});

test('a page that renders the directives twice still writes them once', function () {
    $assets = app(AssetManager::class);

    expect($assets->styles())->toContain('/_assets/')
        ->and($assets->styles())->toBe('');
});
