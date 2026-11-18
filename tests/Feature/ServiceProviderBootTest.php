<?php

declare(strict_types=1);

use Tardis\Manager\PluginManager;
use Tardis\TardisServiceProvider;

/**
 * Commands registered only when running in the console are missing from a
 * web request, so the panel's command runner could never run a tardis:*
 * command even when the allow-list named it.
 */
test('the package commands are registered outside the console as well', function () {
    $source = (string) file_get_contents((new ReflectionClass(TardisServiceProvider::class))->getFileName());

    preg_match('/protected function registerCommands\(\): void\s*\{(.*?)\n    \}/s', $source, $method);

    expect($method[1] ?? '')->toContain('$this->commands(')->not->toContain('runningInConsole');
});

test('the default authentication plugin is registered at boot, not while providers register', function () {
    $provider = new TardisServiceProvider(app());
    $source = (string) file_get_contents((new ReflectionClass($provider))->getFileName());

    preg_match('/public function register\(\): void\s*\{(.*?)\n    \}/s', $source, $register);

    expect($register[1] ?? '')->not->toContain('registerDefaultAuth')
        ->and(app(PluginManager::class)->authenticationPlugin())->not->toBeNull();
});
