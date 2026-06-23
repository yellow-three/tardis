<?php

declare(strict_types=1);

use Tardis\Contracts\Plugins\AuthorizationPlugin;
use Tardis\Contracts\Plugins\GenericPlugin;
use Tardis\Manager\PluginManager;

test('plugin manager can be instantiated', function () {
    $manager = new PluginManager;

    expect($manager)->toBeInstanceOf(PluginManager::class);
});

test('plugin manager starts with empty collection', function () {
    $manager = new PluginManager;

    expect($manager->all())->toHaveCount(0);
});

test('plugin manager enabled collection starts empty', function () {
    $manager = new PluginManager;

    expect($manager->enabled())->toHaveCount(0);
});

test('plugin manager get returns null for non-existent plugin', function () {
    $manager = new PluginManager;

    expect($manager->get('nonexistent'))->toBeNull();
});

test('plugin manager isEnabled returns false for unregistered plugin', function () {
    $manager = new PluginManager;

    expect($manager->isEnabled('nonexistent'))->toBeFalse();
});

test('plugin manager resolveType returns generic for GenericPlugin', function () {
    $manager = new PluginManager;
    $plugin = new class implements GenericPlugin
    {
        public function name(): string
        {
            return 'test';
        }

        public function description(): string
        {
            return 'test';
        }
    };

    expect($manager->resolveType($plugin))->toBe('generic');
});

test('plugin manager resolveType returns authorization for AuthorizationPlugin', function () {
    $manager = new PluginManager;
    $plugin = new class implements AuthorizationPlugin
    {
        public function name(): string
        {
            return 'test';
        }

        public function can(string $ability, mixed $model): bool
        {
            return true;
        }

        public function authorize(string $ability, mixed $model): void {}
    };

    expect($manager->resolveType($plugin))->toBe('authorization');
});
