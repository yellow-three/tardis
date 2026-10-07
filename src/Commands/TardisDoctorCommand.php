<?php

declare(strict_types=1);

namespace Tardis\Commands;

use Illuminate\Console\Command;
use Tardis\Diagnostics\CheckResult;
use Tardis\Diagnostics\DoctorReport;

/**
 * Reports the health of a TARDIS install.
 *
 * The exit code is the contract: a failure makes the command non-zero so it
 * can gate a deploy, while warnings (route cache, stale assets) stay zero.
 * --json emits the same report for scripts.
 */
class TardisDoctorCommand extends Command
{
    protected $signature = 'tardis:doctor
        {--json : Output the report as JSON}';

    protected $description = 'Check that TARDIS is installed and configured correctly';

    public function handle(): int
    {
        $report = DoctorReport::run();

        if ($this->option('json')) {
            $this->line($report->toJson(JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->render($report);
        }

        return $report->hasFailures() ? self::FAILURE : self::SUCCESS;
    }

    private function render(DoctorReport $report): void
    {
        $rows = array_map(fn (CheckResult $check): array => [
            match ($check->status) {
                CheckResult::OK => '<fg=green>OK</>',
                CheckResult::WARN => '<fg=yellow>WARN</>',
                default => '<fg=red>FAIL</>',
            },
            $check->name,
            $check->message,
        ], $report->checks);

        $this->table(['Status', 'Check', 'Detail'], $rows);

        foreach ($report->checks as $check) {
            if ($check->hint !== null) {
                $this->components->twoColumnDetail($check->name, $check->hint);
            }
        }

        if ($report->hasFailures()) {
            $this->components->error('TARDIS is not healthy.');

            return;
        }

        $this->components->info('TARDIS is healthy.');
    }
}
