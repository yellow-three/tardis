<?php

declare(strict_types=1);

use Illuminate\Support\Collection;
use Livewire\Livewire;
use Tardis\Models\ActivityLog;

beforeEach(function () {
    $this->artisan('migrate');

    config()->set('tardis.system.commands.enabled', true);
    config()->set('tardis.system.commands.environments', ['testing']);
    config()->set('tardis.system.commands.allowlist', ['tardis:doctor' => ['--json']]);
});

function commandLog(): Collection
{
    return ActivityLog::where('model_type', 'tardis.command')->get();
}

test('a command that runs is logged as executed with its options and exit code', function () {
    Livewire::test('tardis::pages.system.commands')
        ->set('command', 'tardis:doctor')->set('arguments', ['--json'])
        ->call('run');

    $entry = commandLog()->sole();

    expect($entry->action)->toBe('executed')
        ->and($entry->new_values['command'])->toBe('tardis:doctor')
        ->and($entry->new_values['arguments'])->toBe(['--json'])
        ->and($entry->new_values)->toHaveKey('exit_code');
});

test('a command outside the allowlist is refused and the attempt is logged', function () {
    Livewire::test('tardis::pages.system.commands')
        ->set('command', 'migrate:fresh')
        ->call('run')
        ->assertSet('denied', true);

    $entry = commandLog()->sole();

    expect($entry->action)->toBe('denied')
        ->and($entry->new_values['command'])->toBe('migrate:fresh');
});

test('a disallowed option on an allowed command is logged as denied', function () {
    Livewire::test('tardis::pages.system.commands')
        ->set('command', 'tardis:doctor')->set('arguments', ['--force'])
        ->call('run')->assertSet('denied', true);

    expect(commandLog()->sole()->new_values['arguments'])->toBe(['--force']);
});

test('a hostile command name is stored as data and cannot break the log row', function () {
    $name = "x'; DROP TABLE activity_logs; --".str_repeat('a', 500);

    Livewire::test('tardis::pages.system.commands')->set('command', $name)->call('run');

    expect(commandLog()->sole()->new_values['command'])->toHaveLength(200)
        ->and(ActivityLog::count())->toBe(1);
});

test('nothing is written when the activity log is switched off', function () {
    config()->set('tardis.activity_log.enabled', false);

    Livewire::test('tardis::pages.system.commands')->set('command', 'migrate:fresh')->call('run');

    expect(commandLog())->toBeEmpty();
});
