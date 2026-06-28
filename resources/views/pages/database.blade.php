<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Database')] #[Layout('tardis::layouts.admin')] class extends Component
{
}; ?>

<div>
    <h1 class="text-2xl font-bold mb-6">Database</h1>

    <div class="card bg-base-100 shadow">
        <div class="card-body text-center py-12">
            <x-tardis::icon name="database" class="w-16 h-16 mx-auto opacity-30" />
            <h3 class="text-lg font-semibold mt-4">Database manager coming soon</h3>
        </div>
    </div>
</div>
