<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tardis\Bread\ActivityLogger;
use Tardis\Bread\BreadDefinition;
use Tardis\Bread\BreadManager;
use Tardis\Http\Middleware\SecurityHeaders;
use Tardis\Menu\MenuItem;
use Tardis\Menu\MenuOverlay;
use Tardis\Menu\SiteMenuStore;
use Tardis\Models\ActivityLog;
use Tardis\Models\PanelNotification;
use Tardis\Notifications\PanelNotifications;

class SecurityHeadersTestUser extends Authenticatable
{
    protected $table = 'users';

    protected $guarded = [];
}

/** A BREAD model with no $hidden attributes and only plain text fields. */
class SecretLogRecord extends Model
{
    protected $table = 'secret_log_records';

    protected $guarded = [];

    public $timestamps = false;
}

function seedSecretBread(): void
{
    app(BreadManager::class)->save(BreadDefinition::fromArray([
        'slug' => 'secret-log-records',
        'model' => SecretLogRecord::class,
        'name' => 'Secret record',
        'name_plural' => 'Secret records',
        'fields' => array_map(fn (string $name) => [
            'name' => $name,
            'type' => 'text',
            'label' => ucfirst(str_replace('_', ' ', $name)),
        ], ['title', 'remember_token', 'api_token', 'password', 'recovery_code']),
    ]));
}

/** A host's own middleware that sets a header before Tardis sees the response. */
class SecurityHeadersTestHostMiddleware
{
    public function handle(Request $request, Closure $next): mixed
    {
        $response = $next($request);
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Content-Security-Policy', "default-src 'self'");

        return $response;
    }
}

/** @return array<string, array{string}> */
function craftedUrls(): array
{
    return [
        'slash-backslash' => ['/\evil.test'],
        'protocol-relative' => ['//evil.test'],
        'javascript' => ['javascript:alert(1)'],
        'leading-space' => [' javascript:x'],
        'scheme-without-host' => ['https:evil.test'],
        'backslash' => ['\\evil.test'],
        'two-backslashes' => ['\\\\evil.test'],
    ];
}

/** @return array<string, string> */
function expectedSecurityHeaders(): array
{
    return [
        'X-Frame-Options' => 'SAMEORIGIN',
        'Content-Security-Policy' => "frame-ancestors 'self'",
        'X-Content-Type-Options' => 'nosniff',
        'Referrer-Policy' => 'strict-origin-when-cross-origin',
    ];
}

/** @param mixed $response */
function expectSecurityHeaders($response): void
{
    foreach (expectedSecurityHeaders() as $header => $value) {
        $response->assertHeader($header, $value);
    }
}

beforeEach(function () {
    Schema::create('users', function ($table) {
        $table->id();
        $table->string('name')->nullable();
        $table->timestamps();
    });

    config()->set('auth.providers.users.model', SecurityHeadersTestUser::class);
});

test('the guest login page carries the security headers', function () {
    expectSecurityHeaders($this->get(route('tardis.login'))->assertOk());
});

test('a signed-in dashboard carries the security headers', function () {
    $user = SecurityHeadersTestUser::create(['name' => 'Ada']);

    expectSecurityHeaders($this->actingAs($user)->get(route('tardis.dashboard'))->assertOk());
});

test('a Livewire update carries the security headers', function () {
    $this->withSession(['_token' => 'test-csrf-token']);

    $user = SecurityHeadersTestUser::create(['name' => 'Ada']);

    $html = $this->actingAs($user)->get(route('tardis.search'))->assertOk()->getContent();

    // The page starts with other Livewire components (the notification bell);
    // pick the search component's snapshot so the update has a real method.
    preg_match_all('/wire:snapshot="([^"]+)"/', $html, $matches);
    $snapshot = '';

    foreach ($matches[1] as $candidate) {
        $decoded = html_entity_decode($candidate, ENT_QUOTES);

        if (json_decode($decoded, true)['memo']['name'] === 'tardis::pages.search') {
            $snapshot = $decoded;

            break;
        }
    }

    expect($snapshot)->not->toBe('');

    $response = $this->withHeaders(['X-Livewire' => '1', 'X-CSRF-TOKEN' => 'test-csrf-token'])->postJson(app('livewire')->getUpdateUri(), [
        'components' => [[
            'snapshot' => $snapshot,
            'updates' => (object) [],
            'calls' => [['path' => '', 'method' => 'clearSearch', 'params' => []]],
        ]],
    ])->assertOk();

    expectSecurityHeaders($response);
});

test('the API carries the security headers', function () {
    config()->set('tardis.api.enabled', true);
    config()->set('tardis.api.middleware', ['api']);

    Route::group([], __DIR__.'/../../routes/api.php');
    app('router')->getRoutes()->refreshNameLookups();

    expectSecurityHeaders($this->getJson('/api/tardis/')->assertUnauthorized());
});

test('security headers already set by the host are not overwritten', function () {
    Route::middleware([SecurityHeaders::class, SecurityHeadersTestHostMiddleware::class])
        ->get('/security-headers-host', fn () => response('ok'))
        ->name('security-headers-host');

    $response = $this->get(route('security-headers-host'))->assertOk();

    $response->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('Content-Security-Policy', "default-src 'self'")
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
});

test('an empty header config sends none of the security headers', function () {
    config()->set('tardis.security.headers', []);

    $response = $this->get(route('tardis.login'))->assertOk();

    foreach (expectedSecurityHeaders() as $header => $value) {
        $response->assertHeaderMissing($header);
    }
});

test('a foreign referer cannot turn a locale change into an off-site redirect', function () {
    $response = $this->from('https://evil.test/landing')
        ->post(route('tardis.preferences.locale'), ['locale' => 'de']);

    $response->assertRedirect(route('tardis.login'));
    expect($response->headers->get('Location'))->not->toContain('evil.test');
});

test('a foreign previous URL stored in the session cannot redirect a guest off-site', function () {
    $this->withSession(['_previous' => ['url' => 'https://evil.test/landing', 'route' => null]])
        ->post(route('tardis.preferences.locale'), ['locale' => 'de'])
        ->assertRedirect(route('tardis.login'));
});

test('a signed-in locale change falls back to the dashboard, not an off-site referer', function () {
    $user = SecurityHeadersTestUser::create(['name' => 'Ada']);

    $this->actingAs($user)
        ->from('https://evil.test/landing')
        ->post(route('tardis.preferences.locale'), ['locale' => 'de'])
        ->assertRedirect(route('tardis.dashboard'));
});

test('a same-host referer is kept after a locale change', function () {
    $this->from(route('tardis.login').'?next=panel')
        ->post(route('tardis.preferences.locale'), ['locale' => 'de'])
        ->assertRedirect(route('tardis.login').'?next=panel');
});

test('a crafted notification url is not stored', function (string $url) {
    $this->artisan('migrate');
    $user = SecurityHeadersTestUser::create(['name' => 'Ada']);

    app(PanelNotifications::class)->send($user, 'Bad link', url: $url);

    expect(PanelNotification::query()->where('user_id', $user->id)->first()?->url)->toBeNull();
})->with(craftedUrls());

test('a crafted url is rejected by both menu stores', function (string $url) {
    expect(fn () => app(MenuOverlay::class)->addCustom(['title' => 'Bad', 'url' => $url]))
        ->toThrow(InvalidArgumentException::class);

    $store = app(SiteMenuStore::class);
    $store->create('main');

    expect(fn () => $store->add('main', ['title' => 'Bad', 'url' => $url]))
        ->toThrow(InvalidArgumentException::class);
})->with(craftedUrls());

test('a poisoned notification cannot redirect when opened', function (string $url) {
    $this->artisan('migrate');
    $user = SecurityHeadersTestUser::create(['name' => 'Ada']);
    $notification = PanelNotification::query()->create([
        'user_id' => $user->id,
        'title' => 'Bad link',
        'url' => $url,
        'level' => 'info',
    ]);

    $this->actingAs($user);

    Livewire::test('tardis::components.notifications')
        ->call('open', $notification->id)
        ->assertNoRedirect();
})->with(craftedUrls());

test('a poisoned admin menu link renders as a safe href', function (string $url) {
    expect((new MenuItem('Bad'))->url($url)->href())->toBe('#');
})->with(craftedUrls());

test('ordinary notification and menu urls still work', function (string $url) {
    $this->artisan('migrate');
    $user = SecurityHeadersTestUser::create(['name' => 'Ada']);

    app(PanelNotifications::class)->send($user, 'Good link', url: $url);

    expect(PanelNotification::query()->where('user_id', $user->id)->first()?->url)->toBe($url)
        ->and(app(MenuOverlay::class)->addCustom(['title' => 'Good', 'url' => $url]))->toBeString()
        ->and((new MenuItem('Good'))->url($url)->href())->toBe($url);

    $store = app(SiteMenuStore::class);
    $store->create('main');
    $store->add('main', ['title' => 'Good', 'url' => $url]);

    expect(menu('main')[0]['url'])->toBe($url);
})->with(['root-relative' => ['/admin/x'], 'absolute' => ['https://example.com/a']]);

function seedSecretLogSchema(): void
{
    Artisan::call('migrate');
    Schema::create('secret_log_records', function ($table) {
        $table->id();
        $table->string('title');
        $table->text('remember_token')->nullable();
        $table->text('api_token')->nullable();
        $table->text('password')->nullable();
        $table->text('recovery_code')->nullable();
    });
    seedSecretBread();
}

test('BREAD create redacts secret keys in the activity log but keeps ordinary values', function () {
    seedSecretLogSchema();

    Livewire::test('tardis::pages.bread.create', ['slug' => 'secret-log-records'])
        ->set('form.title', 'Visible title')
        ->set('form.remember_token', 'remember-plain')
        ->set('form.api_token', 'api-plain')
        ->set('form.password', 'password-plain')
        ->set('form.recovery_code', 'recovery-plain')
        ->call('save')
        ->assertHasNoErrors();

    $entry = ActivityLog::query()->where('model_type', SecretLogRecord::class)->where('action', 'created')->latest('id')->first();

    expect($entry)->not->toBeNull()
        ->and($entry->new_values)->toEqual([
            'title' => 'Visible title',
            'remember_token' => '[redacted]',
            'api_token' => '[redacted]',
            'password' => '[redacted]',
            'recovery_code' => '[redacted]',
        ]);
});

test('BREAD edit redacts both old and new secret values', function () {
    seedSecretLogSchema();
    $record = SecretLogRecord::create([
        'title' => 'Before',
        'remember_token' => 'old-remember',
        'api_token' => 'old-api',
        'password' => 'old-password',
        'recovery_code' => 'old-recovery',
    ]);

    Livewire::test('tardis::pages.bread.edit', ['slug' => 'secret-log-records', 'id' => $record->id])
        ->set('form.title', 'After')
        ->set('form.remember_token', 'new-remember')
        ->set('form.api_token', 'new-api')
        ->set('form.password', 'new-password')
        ->set('form.recovery_code', 'new-recovery')
        ->call('save')
        ->assertHasNoErrors();

    $entry = ActivityLog::query()->where('model_type', SecretLogRecord::class)->where('action', 'updated')->latest('id')->first();

    expect($entry)->not->toBeNull()
        ->and($entry->old_values)->toEqual([
            'title' => 'Before',
            'remember_token' => '[redacted]',
            'api_token' => '[redacted]',
            'password' => '[redacted]',
            'recovery_code' => '[redacted]',
        ])
        ->and($entry->new_values)->toEqual([
            'title' => 'After',
            'remember_token' => '[redacted]',
            'api_token' => '[redacted]',
            'password' => '[redacted]',
            'recovery_code' => '[redacted]',
        ]);
});

test('the activity log page does not display a logged secret', function () {
    seedSecretLogSchema();

    Livewire::test('tardis::pages.bread.create', ['slug' => 'secret-log-records'])
        ->set('form.title', 'Visible title')
        ->set('form.remember_token', 'remember-plain')
        ->call('save')
        ->assertHasNoErrors();

    Livewire::test('tardis::pages.activity-log')
        ->assertSee('Visible title')
        ->assertDontSee('remember-plain')
        ->assertSee('[redacted]', false);
});

test('an audit call redacts matching keys, including nested ones', function () {
    $this->artisan('migrate');

    app(ActivityLogger::class)->audit('tardis.test', 'checked', [
        'token' => 'x',
        'title' => 'Visible',
        'nested' => ['api_key' => 'nested-key', 'label' => 'Fine'],
    ]);

    $entry = ActivityLog::query()->where('model_type', 'tardis.test')->where('action', 'checked')->latest('id')->first();

    expect($entry)->not->toBeNull()
        ->and($entry->new_values)->toEqual([
            'token' => '[redacted]',
            'title' => 'Visible',
            'nested' => ['api_key' => '[redacted]', 'label' => 'Fine'],
        ]);
});

test('a flag about a secret stays readable, only the secret value is redacted', function () {
    $this->artisan('migrate');

    app(ActivityLogger::class)->audit('tardis.test', 'flags', [
        'password_changed' => true,
        'two_factor_enabled' => false,
        'remember_token' => null,
    ]);

    $entry = ActivityLog::query()->where('model_type', 'tardis.test')->where('action', 'flags')->latest('id')->first();

    expect($entry->new_values)->toEqual([
        'password_changed' => true,
        'two_factor_enabled' => false,
        'remember_token' => null,
    ]);
});

test('a pasted custom menu link with surrounding spaces is stored trimmed', function () {
    $key = app(MenuOverlay::class)->addCustom(['title' => 'Docs', 'url' => '  /admin/docs  ']);

    $link = collect(app(MenuOverlay::class)->custom())->firstWhere('id', $key);

    expect($link['url'] ?? null)->toBe('/admin/docs');
});
