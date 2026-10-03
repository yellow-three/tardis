<?php

declare(strict_types=1);

namespace Tardis\Diagnostics;

/**
 * The outcome of a single `tardis:doctor` check.
 *
 * A check is deliberately one of three states rather than a boolean: a warning
 * (route cache present, stale assets) must not fail the command, while a
 * failure (missing table, unwritable storage) must. Keeping the status a
 * string lets the JSON output stay stable for scripts.
 */
final class CheckResult
{
    public const OK = 'ok';

    public const WARN = 'warn';

    public const FAIL = 'fail';

    public function __construct(
        public readonly string $name,
        public readonly string $status,
        public readonly string $message,
        public readonly ?string $hint = null,
    ) {}

    public static function ok(string $name, string $message, ?string $hint = null): self
    {
        return new self($name, self::OK, $message, $hint);
    }

    public static function warn(string $name, string $message, ?string $hint = null): self
    {
        return new self($name, self::WARN, $message, $hint);
    }

    public static function fail(string $name, string $message, ?string $hint = null): self
    {
        return new self($name, self::FAIL, $message, $hint);
    }

    public function isOk(): bool
    {
        return $this->status === self::OK;
    }

    public function isWarning(): bool
    {
        return $this->status === self::WARN;
    }

    public function isFailure(): bool
    {
        return $this->status === self::FAIL;
    }

    /**
     * @return array{name: string, status: string, message: string, hint: ?string}
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'status' => $this->status,
            'message' => $this->message,
            'hint' => $this->hint,
        ];
    }
}
