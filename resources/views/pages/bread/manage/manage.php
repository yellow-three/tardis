<?php

use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Tardis\Bread\BreadManager;
use Tardis\Bread\Sources\ConfigBreadSource;

new #[Title('BREAD Management')] #[Layout('tardis::layouts.admin')] class extends Component
{
    #[Computed]
    public function breads()
    {
        return app(BreadManager::class)->all();
    }

    #[Computed]
    public function hasLegacyDefinitions()
    {
        return app(ConfigBreadSource::class)->all()->isNotEmpty();
    }

    public function backups(string $slug)
    {
        return app(BreadManager::class)->backups($slug);
    }

    public function rollback(string $slug, string $backup): void
    {
        try {
            app(BreadManager::class)->rollback($slug, $backup);

            session()->flash('message', "BREAD [{$slug}] restored from a backup.");
        } catch (Throwable $e) {
            session()->flash('error', $e->getMessage());
        }
    }
};
