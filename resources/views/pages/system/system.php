<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Tardis\Auth\Abilities;
use Tardis\Auth\BreadAuthorization;
use Tardis\Diagnostics\CheckResult;
use Tardis\Diagnostics\DoctorReport;

new #[Title('tardis::system.title')] #[Layout('tardis::layouts.admin')] class extends Component
{
    /**
     * Runs on every request, not only on mount: Livewire keeps component state
     * between updates, so an ability revoked after the page opened must still
     * stop the next action.
     */
    public function boot(): void
    {
        app(BreadAuthorization::class)->authorizeAbility(Abilities::SYSTEM);
    }

    /**
     * The report is read fresh on every request rather than kept in public
     * state: the results are value objects Livewire cannot dehydrate, and a
     * diagnostics screen that showed stale checks would be misleading.
     *
     * @return list<CheckResult>
     */
    public function getChecksProperty(): array
    {
        return DoctorReport::run()->checks;
    }

    /**
     * The report only reports status per check, so the totals the header shows
     * are counted here rather than read from it.
     */
    public function passedCount(): int
    {
        return $this->countWhere(static fn (CheckResult $check) => $check->isOk());
    }

    public function warningCount(): int
    {
        return $this->countWhere(static fn (CheckResult $check) => $check->isWarning());
    }

    public function failedCount(): int
    {
        return $this->countWhere(static fn (CheckResult $check) => $check->isFailure());
    }

    public function getSummaryColorProperty(): string
    {
        return match (true) {
            $this->failedCount() > 0 => 'error',
            $this->warningCount() > 0 => 'warning',
            default => 'success',
        };
    }

    public function getSummaryTextProperty(): string
    {
        return match (true) {
            $this->failedCount() > 0 => __('tardis::system.checks_failed', ['count' => $this->failedCount()]),
            $this->warningCount() > 0 => __('tardis::system.checks_with_warnings', ['count' => $this->warningCount()]),
            default => __('tardis::system.all_checks_passed'),
        };
    }

    /**
     * @param  callable(CheckResult): bool  $predicate
     */
    private function countWhere(callable $predicate): int
    {
        return count(array_filter($this->checks, $predicate));
    }
};
