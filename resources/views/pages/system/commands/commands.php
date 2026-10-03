<?php

use Illuminate\Support\Facades\Artisan;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Tardis\Auth\Abilities;
use Tardis\Auth\BreadAuthorization;
use Tardis\Bread\ActivityLogger;
use Tardis\Diagnostics\CommandAllowlist;

new #[Title('tardis::system.command_runner')] #[Layout('tardis::layouts.admin')] class extends Component
{
    /** @var array<int, string> */
    public array $available = [];

    public string $command = '';

    /**
     * Argument values the user ticked, drawn from the command's allowlist.
     *
     * @var array<int, string>
     */
    public array $arguments = [];

    public string $output = '';

    public ?int $exitCode = null;

    public bool $denied = false;

    public string $failure = '';

    /**
     * Public state rather than a computed property: a Blade attribute may not
     * interpolate `$this->canRun`, because the static-text scanner reads the `>`
     * of `->` as the end of the tag and then flags the rest as visible text.
     * A bare `$canRun` keeps the attribute intact.
     */
    public bool $canRun = false;

    /**
     * Runs on every request, not only on mount: Livewire keeps component state
     * between updates, so an ability revoked after the page opened must still
     * stop the next action.
     */
    public function boot(): void
    {
        app(BreadAuthorization::class)->authorizeAbility(Abilities::COMMANDS);
    }

    public function mount(): void
    {
        $this->refreshCommands();
        $this->syncCanRun();
    }

    public function updatedCommand(): void
    {
        // The allowlist is per command, so a switch invalidates the ticks.
        $this->arguments = [];
        $this->resetRun();
        $this->syncCanRun();
    }

    public function refreshCommands(): void
    {
        $this->available = array_keys(app(CommandAllowlist::class)->commands());
    }

    public function syncCanRun(): void
    {
        $this->canRun = $this->enabled && $this->command !== '';
    }

    public function run(): void
    {
        $this->resetRun();

        $allowlist = app(CommandAllowlist::class);

        // Re-checked here rather than trusted from the form: the config may have
        // been changed since the page was opened.
        if (! $allowlist->allows($this->command, $this->arguments)) {
            $this->denied = true;

            return;
        }

        try {
            $this->exitCode = $allowlist->run($this->command, $this->arguments);
            $this->output = Artisan::output();
        } catch (Throwable $e) {
            $this->failure = $e->getMessage();
            $this->exitCode = null;
        }

        app(ActivityLogger::class)->log(
            'tardis.command',
            $this->command,
            'executed',
            newValues: ['arguments' => $this->arguments, 'exit_code' => $this->exitCode],
        );
    }

    public function resetRun(): void
    {
        $this->output = '';
        $this->exitCode = null;
        $this->denied = false;
        $this->failure = '';
    }

    public function getEnabledProperty(): bool
    {
        $allowlist = app(CommandAllowlist::class);

        return $allowlist->enabled() && $allowlist->environmentAllowed();
    }

    /**
     * @return array<int, string>
     */
    public function getPermittedArgumentsProperty(): array
    {
        if ($this->command === '') {
            return [];
        }

        return app(CommandAllowlist::class)->commands()[$this->command] ?? [];
    }
};
