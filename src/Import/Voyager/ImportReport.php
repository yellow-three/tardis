<?php

declare(strict_types=1);

namespace Tardis\Import\Voyager;

/**
 * What an import did or would do, line by line: what was imported, what was
 * skipped and what could not be mapped, so nothing is lost silently.
 */
final class ImportReport
{
    public const IMPORTED = 'imported';

    public const SKIPPED = 'skipped';

    public const UNMAPPED = 'unmapped';

    /** @var list<array{section: string, item: string, status: string, message: string}> */
    private array $lines = [];

    public function add(string $section, string $item, string $status, string $message = ''): void
    {
        $this->lines[] = ['section' => $section, 'item' => $item, 'status' => $status, 'message' => $message];
    }

    /** @return list<array{section: string, item: string, status: string, message: string}> */
    public function lines(): array
    {
        return $this->lines;
    }

    public function count(string $status, ?string $section = null): int
    {
        return count(array_filter(
            $this->lines,
            fn (array $line) => $line['status'] === $status && ($section === null || $line['section'] === $section),
        ));
    }
}
