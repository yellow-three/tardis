<?php

declare(strict_types=1);

test('config has the admin prefix', function () {
    expect(config('tardis.admin.prefix'))->toBe('admin');
});

test('config has bread settings', function () {
    expect(config('tardis.bread'))->toBeArray()->toHaveKeys(['path', 'backup_keep']);
});

test('config has authorization settings', function () {
    expect(config('tardis.authorization.super_admin_roles'))->toContain('super-admin')
        ->and(config('tardis.authorization'))->toHaveKey('enabled');
});

test('config has activity log settings', function () {
    $config = config('tardis.activity_log');

    expect($config)->toBeArray()->toHaveKeys(['enabled', 'log_events']);
});

test('config has database explorer and extra asset settings', function () {
    expect(config('tardis.database.hidden_tables'))->toContain('migrations')
        ->and(config('tardis.assets'))->toHaveKeys(['css', 'js']);
});

test('keys nothing reads were removed so changing them is never a silent no-op', function () {
    // These existed in 1.x but no code consulted them: route middleware is fixed
    // to web + tardis.admin, plugin state lives in storage/tardis/plugins.json,
    // media settings are in tardis-media, and BREAD soft deletes / timestamps
    // are per definition.
    expect(config('tardis.admin.middleware'))->toBeNull()
        ->and(config('tardis.plugins'))->toBeNull()
        ->and(config('tardis.media'))->toBeNull()
        ->and(config('tardis.bread.soft_deletes'))->toBeNull()
        ->and(config('tardis.bread.timestamps'))->toBeNull();
});
