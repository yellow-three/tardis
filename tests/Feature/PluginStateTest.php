<?php

declare(strict_types=1);

use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;
use Tardis\Auth\TardisAuthorizationPlugin;
use Tardis\Contracts\Plugins\AuthorizationPlugin;
use Tardis\Contracts\Plugins\GenericPlugin;
use Tardis\Manager\PluginManager;
use Tardis\Plugins\AuthenticationPlugin;

class StatePluginFixture implements GenericPlugin
{
    public function name(): string
    {
        return 'State fixture';
    }

    public function description(): string
    {
        return 'fixture';
    }
}

class AllowAllPluginFixture implements AuthorizationPlugin
{
    public function name(): string
    {
        return 'Allow all';
    }

    public function can(string $ability, mixed $model = null): bool
    {
        return true;
    }

    public function authorize(string $ability, mixed $model = null): void {}
}

beforeEach(function () {
    $this->stateFile = sys_get_temp_dir().'/tardis-plugin-state-'.uniqid().'/plugins.json';
});

afterEach(function () {
    File::deleteDirectory(dirname($this->stateFile));
});

function pluginManager(string $file): PluginManager
{
    $manager = new PluginManager($file);
    $manager->register('fixture', StatePluginFixture::class);
    $manager->register('tardis-authorization', TardisAuthorizationPlugin::class);
    $manager->register('tardis-auth', AuthenticationPlugin::class);

    return $manager;
}

test('a disable survives a cache flush because it lives in a file', function () {
    $manager = pluginManager($this->stateFile);
    $manager->enableByDefault('fixture');
    $manager->disable('fixture');

    cache()->flush();

    $next = pluginManager($this->stateFile);
    $next->enableByDefault('fixture');

    expect(File::exists($this->stateFile))->toBeTrue()
        ->and($next->isEnabled('fixture'))->toBeFalse();
});

test('enabling clears a stored disable for good', function () {
    $manager = pluginManager($this->stateFile);
    $manager->disable('fixture');
    $manager->enable('fixture');

    $next = pluginManager($this->stateFile);
    $next->enableByDefault('fixture');

    expect($next->isEnabled('fixture'))->toBeTrue();
});

test('a missing or corrupt state file means nothing is disabled', function () {
    File::ensureDirectoryExists(dirname($this->stateFile));
    File::put($this->stateFile, '{not json');

    $manager = pluginManager($this->stateFile);
    $manager->enableByDefault('fixture');

    expect($manager->isEnabled('fixture'))->toBeTrue();
});

test('authentication and authorization plugins cannot be disabled', function (string $name) {
    $manager = pluginManager($this->stateFile);
    $manager->enableByDefault($name);

    expect($manager->isLocked($name))->toBeTrue()
        ->and(fn () => $manager->disable($name))->toThrow(LogicException::class)
        ->and($manager->isEnabled($name))->toBeTrue();
})->with(['tardis-authorization', 'tardis-auth']);

test('a locked plugin stays enabled even if the state file lists it as disabled', function () {
    File::ensureDirectoryExists(dirname($this->stateFile));
    File::put($this->stateFile, json_encode(['disabled' => ['tardis-authorization']]));

    $manager = pluginManager($this->stateFile);
    $manager->enableByDefault('tardis-authorization');

    expect($manager->isEnabled('tardis-authorization'))->toBeTrue();
});

test('plugin types are resolved from every contract', function () {
    $manager = pluginManager($this->stateFile);

    expect($manager->resolveType(new AuthenticationPlugin))->toBe('authentication')
        ->and($manager->resolveType(new TardisAuthorizationPlugin))->toBe('authorization')
        ->and($manager->resolveType(new StatePluginFixture))->toBe('generic');
});

test('the plugins page offers no disable for a locked plugin and refuses the call', function () {
    $user = new User;
    $user->forceFill(['id' => 1]);
    $this->actingAs($user);

    $manager = app(PluginManager::class);
    // First enabled authorization plugin answers the page gate; the real one
    // is registered after it purely to be the locked plugin under test.
    $manager->register('allow-all', AllowAllPluginFixture::class);
    $manager->enableByDefault('allow-all');
    $manager->register('tardis-authorization', TardisAuthorizationPlugin::class);
    $manager->enableByDefault('tardis-authorization');

    Livewire::test('tardis::pages.plugins')
        ->assertDontSee("disable('tardis-authorization')", false)
        ->call('disable', 'tardis-authorization')
        ->assertSet('message', 'This plugin protects the panel and cannot be disabled.');

    expect($manager->isEnabled('tardis-authorization'))->toBeTrue();
});
