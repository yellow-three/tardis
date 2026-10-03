<?php

declare(strict_types=1);

namespace Tardis\Diagnostics;

use Illuminate\Support\Facades\Artisan;

/**
 * Gate for running artisan commands from the panel.
 *
 * Disabled unless the host opts in, and even then only the exact commands and
 * options listed in config are accepted. An entry is `--flag` or
 * `--option=value`; positional arguments are not supported. Options are passed
 * to Artisan as a parameter array, never interpolated into a shell string, so a
 * value that slips through validation still cannot become a second command.
 */
final class CommandAllowlist
{
    public function enabled(): bool
    {
        return (bool) config('tardis.system.commands.enabled', false);
    }

    public function environmentAllowed(): bool
    {
        $environments = config('tardis.system.commands.environments', ['local']);

        if (! is_array($environments) || $environments === []) {
            return false;
        }

        return app()->environment(array_map('strval', $environments));
    }

    /**
     * @return array<string, list<string>>
     */
    public function commands(): array
    {
        $commands = config('tardis.system.commands.allowlist', []);

        if (! is_array($commands)) {
            return [];
        }

        $normalised = [];

        foreach ($commands as $command => $args) {
            if (! is_string($command) || $command === '') {
                continue;
            }

            $normalised[$command] = is_array($args)
                ? array_values(array_filter(array_map('strval', $args), fn (string $arg) => $arg !== ''))
                : [];
        }

        return $normalised;
    }

    /**
     * @param  list<string>  $args
     */
    public function allows(string $command, array $args = []): bool
    {
        if (! $this->enabled() || ! $this->environmentAllowed()) {
            return false;
        }

        if (preg_match('/^[A-Za-z0-9:_-]+$/', $command) !== 1) {
            return false;
        }

        $commands = $this->commands();

        if (! array_key_exists($command, $commands)) {
            return false;
        }

        $allowed = $commands[$command];

        foreach ($args as $arg) {
            if (! is_string($arg) || $this->isUnsafe($arg) || ! $this->isOption($arg) || ! in_array($arg, $allowed, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  list<string>  $args
     */
    public function run(string $command, array $args = []): int
    {
        if (! $this->allows($command, $args)) {
            throw new \InvalidArgumentException("Command [{$command}] is not allowed.");
        }

        return Artisan::call($command, $this->parameters($args));
    }

    /**
     * Artisan::call() reads `--flag` only as an array KEY; a list such as
     * ['--once'] is silently ignored, so the strings become a keyed array.
     *
     * @param  list<string>  $args
     * @return array<string, bool|string>
     */
    private function parameters(array $args): array
    {
        $parameters = [];

        foreach ($args as $arg) {
            [$name, $value] = array_pad(explode('=', $arg, 2), 2, null);

            $parameters[$name] = $value ?? true;
        }

        return $parameters;
    }

    private function isOption(string $arg): bool
    {
        return preg_match('/^--[A-Za-z0-9][A-Za-z0-9-]*(=[^=]*)?$/', $arg) === 1;
    }

    /**
     * Reject anything that could be read as shell syntax or a second argument.
     * Even though Artisan::call() takes an array, a value like `--force; rm -rf`
     * must never reach a command that shells out internally.
     */
    private function isUnsafe(string $arg): bool
    {
        return preg_match('/[\s;|&`$()<>*?!#{}\[\]~\\\\\'"]/', $arg) === 1;
    }
}
