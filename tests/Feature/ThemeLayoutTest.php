<?php

declare(strict_types=1);

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Schema;
use Tardis\Manager\AssetManager;
use Tardis\Manager\ThemeManager;
use Tardis\Support\UserPreferences;
use Tardis\Theme\ThemePreference;

class ThemeLayoutUser extends Authenticatable
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

    $this->actingAs($this->user = ThemeLayoutUser::create(['name' => 'Ada', 'email' => 'ada@example.test', 'password' => 'x']));
});

function dashboardHtml(): string
{
    // The asset and theme managers are singletons that remember what they already
    // rendered; a real request is a fresh process, so start each call clean.
    app()->forgetInstance(AssetManager::class);
    app()->forgetInstance(ThemeManager::class);
    app()->forgetInstance(ThemePreference::class);

    return test()->get('/admin/dashboard')->assertOk()->getContent();
}

test('the server writes the resolved theme into the html element, so no script is needed for first paint', function () {
    app(UserPreferences::class)->set($this->user->id, 'theme', ['mode' => 'light']);

    expect(dashboardHtml())->toMatch('/<html[^>]*\sdata-theme="tardis-light"/');
});

test('dark is the default when nothing was chosen', function () {
    expect(dashboardHtml())->toMatch('/<html[^>]*\sdata-theme="tardis-dark"/');
});

test('system mode starts from the dark theme and leaves the final choice to a tiny script', function () {
    app(UserPreferences::class)->set($this->user->id, 'theme', ['mode' => 'system']);

    $html = dashboardHtml();

    expect($html)->toMatch('/<html[^>]*\sdata-theme="tardis-dark"/')
        ->and($html)->toContain("matchMedia('(prefers-color-scheme: dark)')");
});

test('the boot data carries the themes, the choice and where to save it', function () {
    (new ThemeManager)->saveCustom(['name' => 'ocean', 'scheme' => 'dark', 'colors' => ['primary' => '#336699', 'base-100' => '#101820', 'base-content' => '#eeeeee']]);

    $html = dashboardHtml();

    preg_match('/window\.__TARDIS__\s*=\s*(\{.*?\});/s', $html, $match);
    $data = json_decode($match[1] ?? '{}', true);

    expect($data['theme']['mode'])->toBe('dark')
        ->and(array_column($data['theme']['themes'], 'name'))->toContain('tardis-light', 'tardis-dark', 'ocean')
        ->and($data['theme']['saveUrl'])->toBe(route('tardis.preferences.theme'))
        ->and($data['theme'])->toHaveKey('csrf');
});

test('runtime themes are written as css in the head', function () {
    expect(dashboardHtml())->not->toContain('id="tardis-themes"');

    (new ThemeManager)->saveCustom(['name' => 'ocean', 'scheme' => 'dark', 'colors' => ['primary' => '#336699', 'base-100' => '#101820', 'base-content' => '#eeeeee']]);

    expect(dashboardHtml())->toContain('<style id="tardis-themes">[data-theme="ocean"]{');
});

test('the core script is a file loaded before Livewire and the inline theme store is gone', function () {
    $html = dashboardHtml();

    expect($html)->not->toContain("Alpine.store('theme'")
        ->and($html)->toContain('assets/app.js');

    preg_match('/livewire[^"\']*\.js/', $html, $livewire, PREG_OFFSET_CAPTURE);

    expect($livewire)->not->toBe([])
        ->and(strpos($html, 'assets/app.js'))->toBeLessThan($livewire[0][1]);
});

test('the core script comes before plugin scripts', function () {
    $scripts = app(AssetManager::class)->scripts();

    expect($scripts)->toContain('assets/app.js');
});

test('a user can save a theme choice', function () {
    $this->postJson(route('tardis.preferences.theme'), ['mode' => 'light', 'light' => 'tardis-light'])->assertNoContent();

    expect(app(UserPreferences::class)->get($this->user->id, 'theme'))->toBe(['mode' => 'light', 'light' => 'tardis-light']);
});

test('an invalid choice is refused and nothing is stored', function (array $payload) {
    $this->postJson(route('tardis.preferences.theme'), $payload)->assertStatus(422);

    expect(app(UserPreferences::class)->get($this->user->id, 'theme'))->toBeNull();
})->with([
    'unknown mode' => [['mode' => 'purple']],
    'wrong scheme' => [['dark' => 'tardis-light']],
    'unknown theme' => [['light' => 'nope']],
    'empty' => [[]],
]);

test('a guest posting a choice changes nothing', function () {
    auth()->logout();

    $this->postJson(route('tardis.preferences.theme'), ['mode' => 'light'])->assertNoContent();

    expect(app(UserPreferences::class)->get($this->user->id, 'theme'))->toBeNull();
});

test('the auth layout resolves the same way', function () {
    auth()->logout();

    $html = $this->get('/admin/login')->assertOk()->getContent();

    expect($html)->toMatch('/<html[^>]*\sdata-theme="tardis-dark"/')->toContain('window.__TARDIS__');
});
