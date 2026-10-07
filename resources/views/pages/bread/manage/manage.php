<?php

use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Tardis\Auth\Abilities;
use Tardis\Auth\BreadAuthorization;
use Tardis\Bread\BreadManager;
use Tardis\Bread\Legacy\LegacyConfigReader;

new #[Title('BREAD Management')] #[Layout('tardis::layouts.admin')] class extends Component
{
    /**
     * Runs on every request, not only on mount: Livewire keeps component state
     * between updates, so a permission revoked after the page opened must
     * still stop the next action.
     */
    public function boot(): void
    {
        app(BreadAuthorization::class)->authorizeAbility(Abilities::BREAD);
    }

    #[Computed]
    public function breads()
    {
        return app(BreadManager::class)->all();
    }

    #[Computed]
    public function hasLegacyDefinitions()
    {
        return app(LegacyConfigReader::class)->all()->isNotEmpty();
    }

    public function backups(string $slug)
    {
        return app(BreadManager::class)->backups($slug);
    }

    public function rollback(string $slug, string $backup): void
    {
        try {
            app(BreadManager::class)->rollback($slug, $backup);

            session()->flash('message', __('tardis::bread.restored_from_backup', ['slug' => $slug]));
        } catch (Throwable $e) {
            session()->flash('error', $e->getMessage());
        }
    }
};
