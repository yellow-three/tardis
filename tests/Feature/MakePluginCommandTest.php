<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Tardis\Manager\PluginManager;
use Tardis\Plugins\AuthenticationPlugin;

beforeEach(function () {
    $this->pluginRoot = sys_get_temp_dir().'/tardis-make-plugin-'.uniqid();
});

afterEach(function () {
    File::deleteDirectory($this->pluginRoot);
});

function generatePlugin(string $root): string
{
    test()->artisan('tardis:make-plugin', [
        'name' => 'blog',
        '--namespace' => 'Acme\\Blog',
        '--package-dir' => $root,
        '--with-menu' => true,
        '--with-widgets' => true,
        '--with-settings' => true,
    ])->assertSuccessful();

    return $root.'/Blog';
}

test('every Tardis class a generated plugin imports actually exists', function () {
    $dir = generatePlugin($this->pluginRoot);

    $imports = [];

    foreach (File::allFiles($dir) as $file) {
        if (preg_match_all('/^use (Tardis\\\\[A-Za-z\\\\]+);$/m', $file->getContents(), $matches)) {
            $imports = array_merge($imports, $matches[1]);
        }
    }

    expect($imports)->not->toBeEmpty();

    foreach (array_unique($imports) as $import) {
        expect(class_exists($import) || interface_exists($import))
            ->toBeTrue("generated plugin imports [{$import}], which does not exist");
    }
});

test('every generated PHP file is syntactically valid', function () {
    $dir = generatePlugin($this->pluginRoot);

    foreach (File::allFiles($dir) as $file) {
        if (str_ends_with($file->getFilename(), '.php') && ! str_ends_with($file->getFilename(), '.blade.php')) {
            exec('php -l '.escapeshellarg($file->getPathname()).' 2>&1', $output, $status);

            expect($status)->toBe(0, $file->getFilename().': '.implode("\n", $output));
        }
    }
});

test('the generated page uses a layout that exists', function () {
    $dir = generatePlugin($this->pluginRoot);

    $page = File::get($dir.'/resources/views/pages/admin/blog.blade.php');

    expect($page)->toContain("#[Layout('tardis::layouts.admin')]")
        ->and(view()->exists('tardis::layouts.admin'))->toBeTrue();
});

test('the generated service provider enables the plugin it registers', function () {
    $provider = File::get(generatePlugin($this->pluginRoot).'/src/TardisBlogServiceProvider.php');

    expect($provider)->toContain('use Tardis\\Manager\\PluginManager;')
        ->toContain('enableByDefault');
});

test('enableByDefault turns a plugin on without clearing a stored disable', function () {
    $manager = new PluginManager;
    $manager->register('blog', AuthenticationPlugin::class);
    $manager->disable('blog');

    $manager->enableByDefault('blog');

    expect($manager->isEnabled('blog'))->toBeFalse();

    $fresh = new PluginManager;
    $fresh->register('blog', AuthenticationPlugin::class);
    $fresh->enableByDefault('blog');

    expect($fresh->isEnabled('blog'))->toBeFalse('a disable stored in the cache must survive the boot-time default');
});

test('enableByDefault enables a plugin nobody has disabled', function () {
    $manager = new PluginManager;
    $manager->register('blog', AuthenticationPlugin::class);
    $manager->enableByDefault('blog');
    $manager->enableByDefault('blog');

    expect($manager->isEnabled('blog'))->toBeTrue();
});
