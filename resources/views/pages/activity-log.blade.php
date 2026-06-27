<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Activity Log')] #[Layout('tardis::layouts.admin')] class extends Component
{
    public function render()
    {
        return view('tardis::pages.activity-log');
    }
}; ?>

<div>
    <h1 class="text-2xl font-bold mb-6">Activity Log</h1>

    <div class="card bg-base-100 shadow">
        <div class="card-body text-center py-12">
            <x-heroicon-o-clock class="w-16 h-16 mx-auto opacity-30" />
            <h3 class="text-lg font-semibold mt-4">Activity log coming soon</h3>
        </div>
    </div>
</div>
