<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Livewire\Livewire;
use Tardis\Contracts\Plugins\Features\Provider\SettingsComponent;
use Tardis\Contracts\Plugins\GenericPlugin;
use Tardis\Manager\PluginManager;

class SettingsPlugin implements GenericPlugin, SettingsComponent
{
    public function name(): string
    {
        return 'Settings plugin';
    }

    public function description(): string
    {
        return 'has a settings screen';
    }

    public function settingsComponent(): string
    {
        return 'plugintest::settings';
    }
}

class PlainPlugin implements GenericPlugin
{
    public function name(): string
    {
        return 'Plain plugin';
    }

    public function description(): string
    {
        return 'no settings';
    }
}

beforeEach(function () {
    $this->views = sys_get_temp_dir().'/tardis-plugin-settings-'.uniqid();
    File::ensureDirectoryExists($this->views);
    File::put($this->views.'/settings.blade.php', <<<'PHP'
<?php

use Livewire\Component;

new class extends Component
{
}; ?>

<div>plugin settings form</div>
PHP);
    Livewire::addNamespace('plugintest', viewPath: $this->views);

    $plugins = app(PluginManager::class);
    $plugins->register('with-settings', SettingsPlugin::class);
    $plugins->register('plain', PlainPlugin::class);
    $plugins->enable('with-settings');
    $plugins->enable('plain');
});

afterEach(fn () => File::deleteDirectory($this->views));

test('a plugin with a settings component gets a Settings button that opens it', function () {
    Livewire::test('tardis::pages.plugins')
        ->assertSee('Settings')
        ->assertDontSee('plugin settings form')
        ->call('openSettings', 'with-settings')
        ->assertSet('settingsFor', 'with-settings')
        ->assertSee('plugin settings form')
        ->call('closeSettings')
        ->assertDontSee('plugin settings form');
});

test('a plugin without a settings component cannot open a dialog', function () {
    Livewire::test('tardis::pages.plugins')
        ->call('openSettings', 'plain')
        ->assertSet('settingsFor', null)
        ->call('openSettings', 'does-not-exist')
        ->assertSet('settingsFor', null);
});

test('a disabled plugin hides its settings', function () {
    app(PluginManager::class)->disable('with-settings');

    Livewire::test('tardis::pages.plugins')
        ->call('openSettings', 'with-settings')
        ->assertSet('settingsFor', null);
});
