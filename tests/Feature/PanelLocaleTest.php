<?php

declare(strict_types=1);

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Schema;
use Tardis\Support\Locales;
use Tardis\Support\UserPreferences;

class LocaleTestUser extends Authenticatable
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

    $this->actingAs($this->user = LocaleTestUser::create(['name' => 'Ada', 'email' => 'ada@example.test', 'password' => 'x']));
});

test('the panel ships English and Turkish', function () {
    expect(Locales::available())->toContain('en', 'tr');
});

test('a locale the package has no strings for is not available', function () {
    expect(Locales::isAvailable('xx'))->toBeFalse()->and(Locales::isAvailable('tr'))->toBeTrue();
});

test('a host can add a language by publishing lang/vendor/tardis/<locale>', function () {
    $dir = lang_path('vendor/tardis/de');
    File::ensureDirectoryExists($dir);
    File::put($dir.'/menu.php', "<?php\n\nreturn ['dashboard' => 'Übersicht'];\n");

    expect(Locales::available())->toContain('de');

    File::deleteDirectory(lang_path('vendor'));
});

test('the panel is rendered in the app locale by default', function () {
    app()->setLocale('en');

    $this->get('/admin/dashboard')->assertOk()->assertSee('Dashboard');
});

test('a stored user locale overrides the app locale', function () {
    app(UserPreferences::class)->set($this->user->id, 'locale', 'tr');

    $this->get('/admin/dashboard')->assertOk()->assertSee('Kontrol paneli');

    expect(app()->getLocale())->toBe('tr');
});

test('the html lang attribute follows the active locale', function () {
    app(UserPreferences::class)->set($this->user->id, 'locale', 'tr');

    $this->get('/admin/dashboard')->assertSee('<html lang="tr"', false);
});

test('a user can switch the panel language and it is remembered', function () {
    $this->post(route('tardis.preferences.locale'), ['locale' => 'tr'])->assertRedirect();

    expect(app(UserPreferences::class)->get($this->user->id, 'locale'))->toBe('tr');
});

test('an unknown locale is rejected and nothing is stored', function () {
    $this->post(route('tardis.preferences.locale'), ['locale' => 'xx'])->assertSessionHasErrors('locale');

    expect(app(UserPreferences::class)->get($this->user->id, 'locale'))->toBeNull();
});

test('the language switcher lists the available languages', function () {
    $html = $this->get('/admin/dashboard')->getContent();

    expect($html)->toContain(route('tardis.preferences.locale'))
        ->toContain('value="tr"');
});
