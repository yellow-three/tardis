<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Tardis\Diagnostics\CommandAllowlist;

beforeEach(function () {
    config()->set('tardis.system.commands.enabled', false);
    config()->set('tardis.system.commands.environments', ['testing']);
    config()->set('tardis.system.commands.allowlist', []);
});

test('nothing is allowed while the runner is disabled', function () {
    config()->set('tardis.system.commands.allowlist', ['cache:clear' => []]);

    expect((new CommandAllowlist)->allows('cache:clear'))->toBeFalse();
});

test('nothing is allowed when the allowlist is empty', function () {
    config()->set('tardis.system.commands.enabled', true);

    expect((new CommandAllowlist)->allows('cache:clear'))->toBeFalse();
});

test('a listed command with no args is allowed', function () {
    config()->set('tardis.system.commands.enabled', true);
    config()->set('tardis.system.commands.allowlist', ['cache:clear' => []]);

    expect((new CommandAllowlist)->allows('cache:clear'))->toBeTrue();
});

test('an unlisted command is rejected', function () {
    config()->set('tardis.system.commands.enabled', true);
    config()->set('tardis.system.commands.allowlist', ['cache:clear' => []]);

    expect((new CommandAllowlist)->allows('migrate'))->toBeFalse();
});

test('only listed argument values are allowed', function () {
    config()->set('tardis.system.commands.enabled', true);
    config()->set('tardis.system.commands.allowlist', ['queue:work' => ['--once']]);

    expect((new CommandAllowlist)->allows('queue:work', ['--once']))->toBeTrue()
        ->and((new CommandAllowlist)->allows('queue:work', ['--stop-when-empty']))->toBeFalse();
});

test('shell metacharacters are rejected even when listed', function (string $arg) {
    config()->set('tardis.system.commands.enabled', true);
    config()->set('tardis.system.commands.allowlist', ['cache:clear' => [$arg]]);

    expect((new CommandAllowlist)->allows('cache:clear', [$arg]))->toBeFalse();
})->with(['--force; rm -rf /', '$(whoami)', 'a b', 'x|y', '`id`']);

test('the runner is rejected outside the allowed environments', function () {
    config()->set('tardis.system.commands.enabled', true);
    config()->set('tardis.system.commands.environments', ['production']);
    config()->set('tardis.system.commands.allowlist', ['cache:clear' => []]);

    expect((new CommandAllowlist)->allows('cache:clear'))->toBeFalse();
});

test('run throws for a disallowed command', function () {
    expect(fn () => (new CommandAllowlist)->run('cache:clear'))->toThrow(InvalidArgumentException::class);
});

test('run calls artisan for an allowed command', function () {
    config()->set('tardis.system.commands.enabled', true);
    config()->set('tardis.system.commands.allowlist', ['tardis:doctor' => []]);

    $kernel = Mockery::mock(Kernel::class);
    $kernel->shouldReceive('call')->once()->with('tardis:doctor', [])->andReturn(0);
    Artisan::swap($kernel);

    expect((new CommandAllowlist)->run('tardis:doctor'))->toBe(0);
});

test('allowlisted flags reach the command as options, not as ignored positionals', function () {
    config()->set('tardis.system.commands.enabled', true);
    config()->set('tardis.system.commands.allowlist', ['queue:work' => ['--once', '--queue=default']]);

    $kernel = Mockery::mock(Kernel::class);
    $kernel->shouldReceive('call')->once()->with('queue:work', ['--once' => true, '--queue' => 'default'])->andReturn(0);
    Artisan::swap($kernel);

    expect((new CommandAllowlist)->run('queue:work', ['--once', '--queue=default']))->toBe(0);
});

test('a positional value is never allowed, only --flag and --option=value', function () {
    config()->set('tardis.system.commands.enabled', true);
    config()->set('tardis.system.commands.allowlist', ['tardis:doctor' => ['json', '--json']]);

    expect((new CommandAllowlist)->allows('tardis:doctor', ['json']))->toBeFalse()
        ->and((new CommandAllowlist)->allows('tardis:doctor', ['--json']))->toBeTrue();
});

test('a flag really changes what the command does', function () {
    config()->set('tardis.system.commands.enabled', true);
    config()->set('tardis.system.commands.allowlist', ['tardis:doctor' => ['--json']]);

    (new CommandAllowlist)->run('tardis:doctor', ['--json']);

    expect(json_decode(trim(Artisan::output()), true))->toHaveKey('checks');
});
