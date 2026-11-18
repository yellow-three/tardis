<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Tardis\Auth\Abilities;
use Tardis\Auth\BreadAuthorization;
use Tardis\Diagnostics\LogReader;

new #[Title('tardis::system.log_viewer')] #[Layout('tardis::layouts.admin')] class extends Component
{
    /** @var array<int, string> */
    public array $available = [];

    public string $file = '';

    public int $tail = 200;

    /** @var array<int, string> */
    public array $lines = [];

    public bool $unavailable = false;

    /**
     * Runs on every request, not only on mount: Livewire keeps component state
     * between updates, so an ability revoked after the page opened must still
     * stop the next action.
     */
    public function boot(): void
    {
        app(BreadAuthorization::class)->authorizeAbility(Abilities::LOGS);
    }

    public function mount(): void
    {
        $this->refreshFiles();

        if ($this->available !== []) {
            $this->file = $this->available[0];
        }

        $this->loadLines();
    }

    public function updatedFile(): void
    {
        $this->loadLines();
    }

    public function updatedTail(): void
    {
        $this->loadLines();
    }

    public function refresh(): void
    {
        // A new log file may have appeared since mount.
        $this->refreshFiles();
        $this->loadLines();
    }

    /**
     * @return array<int, string>
     */
    public function refreshFiles(): array
    {
        return $this->available = app(LogReader::class)->files();
    }

    public function loadLines(): void
    {
        $this->unavailable = false;
        $this->lines = [];

        if ($this->file === '') {
            return;
        }

        try {
            // The reader bounds the tail itself and refuses anything outside
            // the configured directory, so the page never touches the disk
            // directly and never writes.
            $this->lines = app(LogReader::class)->tail($this->file, $this->tailLines());
        } catch (InvalidArgumentException) {
            // The file was removed or the selection was tampered with; keep the
            // screen usable instead of surfacing an internal message.
            $this->unavailable = true;
        }
    }

    public function tailLines(): int
    {
        // Keep the page input inside the range the reader accepts.
        return max(1, min($this->tail, 5000));
    }

    public function getHasFilesProperty(): bool
    {
        return $this->available !== [];
    }

    public function getIsEmptyProperty(): bool
    {
        return $this->lines === [];
    }

    public function getIsTruncatedProperty(): bool
    {
        return count($this->lines) >= $this->tailLines();
    }
};
