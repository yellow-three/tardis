<?php

declare(strict_types=1);

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Tardis\Facades\Tardis;
use Tardis\Manager\MenuManager;
use Tardis\TardisServiceProvider;

class StructureTestUser extends Authenticatable
{
    protected $table = 'users';

    protected $guarded = [];
}

function livewireRoot(): string
{
    return realpath(__DIR__.'/../../resources/views/livewire');
}

test('only components live in the Livewire root', function () {
    $outside = collect(File::allFiles(realpath(__DIR__.'/../../resources/views')))
        ->reject(fn ($file) => str_starts_with($file->getRealPath(), livewireRoot().DIRECTORY_SEPARATOR))
        ->filter(fn ($file) => str_contains($file->getContents(), 'extends Component'))
        ->map(fn ($file) => $file->getRelativePathname())
        ->values()
        ->all();

    expect($outside)->toBe([]);
});

test('a multi-file component never holds another one', function () {
    $nested = collect(File::allFiles(livewireRoot()))
        ->filter(fn ($file) => $file->getExtension() === 'php' && ! str_ends_with($file->getFilename(), '.blade.php'))
        ->map(fn ($file) => dirname($file->getRealPath()))
        ->unique()
        ->filter(fn (string $dir) => collect(File::directories($dir))->isNotEmpty())
        ->map(fn (string $dir) => str_replace(livewireRoot().'/', '', $dir))
        ->values()
        ->all();

    expect($nested)->toBe([]);
});

test('index directories keep the component names unchanged', function () {
    foreach (['tardis::pages.database', 'tardis::pages.system', 'tardis::components.media-picker'] as $name) {
        expect(app('livewire.finder')->resolveMultiFileComponentPath($name))->not->toBeNull();
    }
});

test('the views tag publishes overridable views only, never the components', function () {
    $paths = ServiceProvider::pathsToPublish(TardisServiceProvider::class, 'tardis-views');

    expect(array_map('basename', array_keys($paths)))->toBe(['layouts', 'components', 'formfields', 'widgets', 'partials']);
});

test('a published Blade component overrides the package one', function () {
    $dir = resource_path('views/vendor/tardis/components');
    File::ensureDirectoryExists($dir);
    File::put($dir.'/badge.blade.php', '<span>host badge</span>');

    try {
        expect(Blade::render('<x-tardis::badge>x</x-tardis::badge>'))->toContain('host badge');
    } finally {
        File::deleteDirectory(resource_path('views/vendor/tardis'));
    }
});

describe('ui components page', function () {
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
        $this->actingAs(StructureTestUser::create(['name' => 'Ada', 'email' => 'ada@example.test']));
    });

    test('follows the admin.ui-components setting', function () {
        $this->get('/admin/ui-components')->assertOk();
        expect(MenuManager::uiComponentsEnabled())->toBeTrue();

        Tardis::settings()->set('admin.ui-components', 'hide');

        expect(MenuManager::uiComponentsEnabled())->toBeFalse();
        $this->get('/admin/ui-components')->assertNotFound();
        $this->get('/admin/dashboard')->assertOk()->assertDontSee(route('tardis.ui-components'), false);
    });
});
