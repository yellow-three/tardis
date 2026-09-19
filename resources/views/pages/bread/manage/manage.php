<?php

use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Tardis\Bread\BreadManager;

new #[Title('BREAD Management')] #[Layout('tardis::layouts.admin')] class extends Component
{
    #[Computed]
    public function breads()
    {
        return app(BreadManager::class)->all();
    }
};
