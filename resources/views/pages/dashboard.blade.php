<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Dashboard')] #[Layout('tardis::layouts.admin')] class extends Component
{
    public function render()
    {
        return view('tardis::pages.dashboard');
    }
}; ?>

<div>
    <h1 class="text-2xl font-bold mb-6">Dashboard</h1>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <livewire:tardis::stats-media />
    </div>
</div>
