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

test('config has system settings for the log viewer and the command runner', function () {
    $system = config('tardis.system');

    expect($system)->toBeArray()->toHaveKeys(['logs', 'commands'])
        ->and($system['logs'])->toHaveKeys(['path', 'filename_pattern', 'tail', 'max_bytes'])
        ->and($system['commands'])->toHaveKeys(['enabled', 'environments', 'allowlist']);
});

test('the log viewer defaults keep a single file read bounded', function () {
    // The viewer tails the end of a log file, so the defaults have to cap both
    // the number of lines and the number of bytes it is willing to read.
    expect(config('tardis.system.logs.tail'))->toBeGreaterThan(0)->toBeLessThanOrEqual(1000)
        ->and(config('tardis.system.logs.max_bytes'))->toBeGreaterThan(0);
});

test('the command runner is disabled outside local so a published config cannot expose artisan', function () {
    expect(config('tardis.system.commands.enabled'))->toBeFalse()
        ->and(config('tardis.system.commands.environments'))->toBe(['local'])
        ->and(config('tardis.system.commands.allowlist'))->toBeArray()->toBeEmpty();
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

test('the published config file keeps every documented section', function () {
    // Read the file itself, not the merged runtime config: a rewrite that drops a
    // block would otherwise be hidden by defaults supplied elsewhere.
    $file = require __DIR__.'/../../config/tardis.php';

    expect(array_keys($file))->toEqualCanonicalizing([
        'admin', 'bread', 'database', 'assets', 'locales', 'translation',
        'authorization', 'activity_log', 'models', 'system', 'csp',
    ])->and($file['models'])->toHaveKeys(['role', 'permission', 'media', 'activity_log'])
        ->and($file['system'])->toHaveKeys(['logs', 'commands', 'demo']);
});
