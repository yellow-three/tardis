<?php

declare(strict_types=1);

test('config has admin prefix', function () {
    $config = config('tardis.admin.prefix');
    expect($config)->toBe('admin');
});

test('config has admin middleware', function () {
    $config = config('tardis.admin.middleware');
    expect($config)->toBeArray();
    expect($config)->toContain('web');
    expect($config)->toContain('auth');
});

test('config has bread settings', function () {
    $config = config('tardis.bread');
    expect($config)->toBeArray();
    expect($config)->toHaveKey('soft_deletes');
    expect($config)->toHaveKey('timestamps');
});

test('config has media settings', function () {
    $config = config('tardis.media');
    expect($config)->toBeArray();
    expect($config)->toHaveKey('disk');
    expect($config)->toHaveKey('path');
    expect($config)->toHaveKey('max_size');
});

test('config has plugin settings', function () {
    $config = config('tardis.plugins');
    expect($config)->toBeArray();
    expect($config)->toHaveKey('enabled');
    expect($config)->toHaveKey('disabled');
});

test('config has activity log settings', function () {
    $config = config('tardis.activity_log');
    expect($config)->toBeArray();
    expect($config)->toHaveKey('enabled');
    expect($config)->toHaveKey('log_events');
});
