<?php

declare(strict_types=1);

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tardis\Auth\Abilities;
use Tardis\Bread\BreadManager;
use Tardis\Contracts\Plugins\AuthorizationPlugin;
use Tardis\Manager\MenuManager;
use Tardis\Manager\PluginManager;
use Tardis\Models\Media;

class ScreenGateUser extends Authenticatable
{
    protected $table = 'users';

    protected $guarded = [];
}

/** Answers from an allow-list, so a test states exactly which abilities a user holds. */
class ScreenGatePlugin implements AuthorizationPlugin
{
    /** @var array<int, string> */
    public static array $allowed = [];

    public function name(): string
    {
        return 'screen-gate';
    }

    public function can(string $ability, mixed $model = null): bool
    {
        return in_array($ability, static::$allowed, true);
    }

    public function authorize(string $ability, mixed $model = null): void
    {
        $this->can($ability, $model) || abort(403);
    }
}

function gateAllows(array $abilities): void
{
    ScreenGatePlugin::$allowed = $abilities;

    $manager = app(PluginManager::class);
    $manager->register('screen-gate', ScreenGatePlugin::class);
    $manager->enableByDefault('screen-gate');
}

/** A media row the edit screen can mount; the file itself is irrelevant here. */
function mediaEditFixture(): Media
{
    return Media::create([
        'name' => 'edit-me.jpg',
        'original_name' => 'edit-me.jpg',
        'path' => 'media/edit-me.jpg',
        'disk' => 'public',
        'mime_type' => 'image/jpeg',
        'size' => 123,
    ]);
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

    $this->artisan('migrate');
    $this->actingAs(ScreenGateUser::create(['name' => 'Ada', 'email' => 'ada@example.test', 'password' => 'x']));

    Storage::fake('public');
});

test('each fixed screen refuses a user who lacks its ability', function (string $page, array $params, string $ability) {
    gateAllows([Abilities::ACCESS]);

    // The media edit screen only mounts once the page may open, so the row
    // has to exist for every case in this dataset.
    if ($page === 'tardis::pages.media-edit') {
        $params['id'] = mediaEditFixture()->id;
    }

    Livewire::test($page, $params)->assertForbidden();
})->with([
    'settings' => ['tardis::pages.settings', [], Abilities::SETTINGS],
    'plugins' => ['tardis::pages.plugins', [], Abilities::PLUGINS],
    'menu builder' => ['tardis::pages.menu-builder', [], Abilities::MENUS],
    'theme editor' => ['tardis::pages.theme-editor', [], Abilities::APPEARANCE],
    'database' => ['tardis::pages.database', [], Abilities::DATABASE],
    'database create' => ['tardis::pages.database.create', [], Abilities::DATABASE],
    'database edit' => ['tardis::pages.database.edit', ['table' => 'users'], Abilities::DATABASE],
    'bread manage' => ['tardis::pages.bread.manage', [], Abilities::BREAD],
    'bread builder' => ['tardis::pages.bread-builder', [], Abilities::BREAD],
    'roles' => ['tardis::pages.roles', [], Abilities::ROLES],
    'users' => ['tardis::pages.users', [], Abilities::USERS],
    'permissions' => ['tardis::pages.permissions', [], Abilities::ROLES],
    'activity log' => ['tardis::pages.activity-log', [], Abilities::ACTIVITY],
    'media' => ['tardis::pages.media-browser', [], Abilities::MEDIA_BROWSE],
    'media edit' => ['tardis::pages.media-edit', [], Abilities::MEDIA_RENAME],
    'system' => ['tardis::pages.system', [], Abilities::SYSTEM],
    'system logs' => ['tardis::pages.system.logs', [], Abilities::LOGS],
    'system commands' => ['tardis::pages.system.commands', [], Abilities::COMMANDS],
]);

test('each fixed screen opens for a user who holds its ability', function (string $page, array $params, string $ability) {
    gateAllows([$ability]);

    if ($page === 'tardis::pages.media-edit') {
        $params['id'] = mediaEditFixture()->id;
    }

    Livewire::test($page, $params)->assertOk();
})->with([
    'settings' => ['tardis::pages.settings', [], Abilities::SETTINGS],
    'plugins' => ['tardis::pages.plugins', [], Abilities::PLUGINS],
    'menu builder' => ['tardis::pages.menu-builder', [], Abilities::MENUS],
    'theme editor' => ['tardis::pages.theme-editor', [], Abilities::APPEARANCE],
    'database' => ['tardis::pages.database', [], Abilities::DATABASE],
    'database create' => ['tardis::pages.database.create', [], Abilities::DATABASE],
    'database edit' => ['tardis::pages.database.edit', ['table' => 'users'], Abilities::DATABASE],
    'bread manage' => ['tardis::pages.bread.manage', [], Abilities::BREAD],
    'bread builder' => ['tardis::pages.bread-builder', [], Abilities::BREAD],
    'roles' => ['tardis::pages.roles', [], Abilities::ROLES],
    'users' => ['tardis::pages.users', [], Abilities::USERS],
    'permissions' => ['tardis::pages.permissions', [], Abilities::ROLES],
    'activity log' => ['tardis::pages.activity-log', [], Abilities::ACTIVITY],
    'media' => ['tardis::pages.media-browser', [], Abilities::MEDIA_BROWSE],
    'media edit' => ['tardis::pages.media-edit', [], Abilities::MEDIA_RENAME],
    'system' => ['tardis::pages.system', [], Abilities::SYSTEM],
    'system logs' => ['tardis::pages.system.logs', [], Abilities::LOGS],
    'system commands' => ['tardis::pages.system.commands', [], Abilities::COMMANDS],
]);

test('losing the ability after the page opened blocks the next action', function () {
    gateAllows([Abilities::SETTINGS]);

    $component = Livewire::test('tardis::pages.settings')->assertOk();

    ScreenGatePlugin::$allowed = [];

    // Livewire component state outlives the request that rendered it, so the
    // guard has to run on every update, not only on mount.
    $component->call('save')->assertForbidden();
});

test('losing a system ability after the page opened blocks the next action', function (string $page, array $abilities, string $action) {
    gateAllows($abilities);

    $component = Livewire::test($page)->assertOk();

    ScreenGatePlugin::$allowed = [];

    $component->call($action)->assertForbidden();
})->with([
    'diagnostics' => ['tardis::pages.system', [Abilities::SYSTEM], '$refresh'],
    'log viewer' => ['tardis::pages.system.logs', [Abilities::LOGS], 'refresh'],
    'command runner' => ['tardis::pages.system.commands', [Abilities::COMMANDS], 'run'],
]);

test('each system screen reaches the sidebar on its own ability', function (array $abilities) {
    // The menu is cached, so each case needs a rebuilt instance to be re-filtered.
    app()->forgetInstance(MenuManager::class);

    gateAllows([Abilities::ACCESS, ...$abilities]);

    $html = $this->get('/admin/dashboard')->assertOk()->getContent();

    // Siblings rather than a group: a screen appears exactly when its own
    // ability is held, and a sibling's ability must never strand it.
    $screens = [
        'tardis.system.index' => Abilities::SYSTEM,
        'tardis.system.logs' => Abilities::LOGS,
        'tardis.system.commands' => Abilities::COMMANDS,
    ];

    foreach ($screens as $routeName => $ability) {
        $link = 'href="'.route($routeName).'"';

        in_array($ability, $abilities, true)
            ? expect($html)->toContain($link)
            : expect($html)->not->toContain($link);
    }
})->with([
    'nothing granted' => [[]],
    'diagnostics only' => [[Abilities::SYSTEM]],
    'log viewer only' => [[Abilities::LOGS]],
    'command runner only' => [[Abilities::COMMANDS]],
    'all three' => [[Abilities::SYSTEM, Abilities::LOGS, Abilities::COMMANDS]],
]);

test('media write actions need their own ability on top of browsing', function () {
    gateAllows([Abilities::MEDIA_BROWSE]);

    Livewire::test('tardis::pages.media-browser')
        ->set('newUploads', UploadedFile::fake()->create('a.pdf', 1))
        ->assertForbidden();

    Storage::disk('public')->assertMissing('media/a.pdf');

    Livewire::test('tardis::pages.media-browser')
        ->set('newDirectoryName', 'box')
        ->call('createDirectory')
        ->assertForbidden();

    gateAllows([Abilities::MEDIA_BROWSE, Abilities::MEDIA_UPLOAD]);

    Livewire::test('tardis::pages.media-browser')
        ->set('newUploads', UploadedFile::fake()->create('a.pdf', 1))
        ->assertOk();

    Storage::disk('public')->assertExists('media/a.pdf');
});

test('the sidebar hides screens the user may not open', function () {
    gateAllows([Abilities::ACCESS, Abilities::SETTINGS]);

    $html = $this->get('/admin/dashboard')->assertOk()->getContent();

    expect($html)->toContain(route('tardis.settings.index'))
        ->and($html)->not->toContain('href="'.route('tardis.plugins.index').'"')
        ->and($html)->not->toContain('href="'.route('tardis.roles').'"')
        ->and($html)->not->toContain('href="'.route('tardis.media').'"')
        ->and($html)->not->toContain('href="'.route('tardis.database.index').'"')
        ->and($html)->not->toContain('href="'.route('tardis.bread.manage').'"');
});

test('the sidebar lists a BREAD resource only to users who may browse it', function () {
    app(BreadManager::class)->save(['slug' => 'widgets', 'model' => 'App\\Models\\Widget', 'name' => 'Widget', 'name_plural' => 'Widgets', 'fields' => []]);
    app()->forgetInstance(MenuManager::class);

    gateAllows([Abilities::ACCESS]);
    $hidden = $this->get('/admin/dashboard')->assertOk()->getContent();

    app()->forgetInstance(MenuManager::class);

    gateAllows([Abilities::ACCESS, 'browse widgets']);
    $shown = $this->get('/admin/dashboard')->assertOk()->getContent();

    expect($hidden)->not->toContain('href="'.route('tardis.bread.index', ['slug' => 'widgets']).'"')
        ->and($shown)->toContain('href="'.route('tardis.bread.index', ['slug' => 'widgets']).'"');
});

test('changing the dashboard layout needs its own ability', function () {
    gateAllows([Abilities::ACCESS]);

    Livewire::test('tardis::pages.dashboard')
        ->assertOk()
        ->assertDontSee(__('tardis::dashboard.customize'))
        ->call('toggleEditing')->assertForbidden();

    gateAllows([Abilities::ACCESS, Abilities::DASHBOARD]);

    Livewire::test('tardis::pages.dashboard')->assertSee(__('tardis::dashboard.customize'))->call('toggleEditing')->assertOk();
});

test('media edit route requires appropriate abilities', function () {
    $media = Media::create([
        'name' => 'test.jpg',
        'path' => 'media/test.jpg',
        'mime_type' => 'image/jpeg',
        'disk' => 'public',
        'size' => 100,
        'original_name' => 'test.jpg',
    ]);

    auth()->logout();
    $this->get(route('tardis.media.edit', $media->id))->assertStatus(302);

    $this->actingAs(ScreenGateUser::create(['name' => 'Ada', 'email' => 'ada2@example.test', 'password' => 'x']));

    // ACCESS gets past the middleware; the page itself then demands its own
    // ability, so every case below holds ACCESS and varies only the rest.
    gateAllows([Abilities::ACCESS]);
    $this->get(route('tardis.media.edit', $media->id))->assertStatus(403);

    gateAllows([Abilities::ACCESS, Abilities::MEDIA_BROWSE]);
    $this->get(route('tardis.media.edit', $media->id))->assertStatus(403);

    gateAllows([Abilities::ACCESS, Abilities::MEDIA_BROWSE, Abilities::MEDIA_RENAME]);
    $this->get(route('tardis.media.edit', $media->id))->assertStatus(200);

    gateAllows([Abilities::ACCESS, Abilities::MEDIA_BROWSE, Abilities::MEDIA_RENAME, Abilities::MEDIA_DELETE]);
    $this->get(route('tardis.media.edit', $media->id))->assertStatus(200);
});

test('deleting from the edit screen needs the delete ability on top of renaming', function () {
    Storage::disk('public')->put('media/photo.jpg', 'bytes');
    $media = mediaEditFixture();
    $media->update(['path' => 'media/photo.jpg']);

    // Opening the page only proves rename; delete() checks its own ability.
    gateAllows([Abilities::MEDIA_RENAME]);

    Livewire::test('tardis::pages.media-edit', ['id' => $media->id])
        ->call('delete')->assertForbidden();

    Storage::disk('public')->assertExists('media/photo.jpg');
    expect(Media::query()->whereKey($media->id)->exists())->toBeTrue();

    gateAllows([Abilities::MEDIA_RENAME, Abilities::MEDIA_DELETE]);

    Livewire::test('tardis::pages.media-edit', ['id' => $media->id])
        ->call('delete')->assertRedirect(route('tardis.media'));

    Storage::disk('public')->assertMissing('media/photo.jpg');
    expect(Media::query()->whereKey($media->id)->exists())->toBeFalse();
});
