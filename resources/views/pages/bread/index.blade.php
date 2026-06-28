<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('BREAD')] #[Layout('tardis::layouts.admin')] class extends Component
{
    public string $slug = '';

    public function mount(string $slug): void
    {
        $this->slug = $slug;
    }
}; ?>

<div>
    <h1 class="text-2xl font-bold mb-6">{{ ucfirst($slug) }}</h1>

    <div class="card bg-base-100 shadow">
        <div class="card-body text-center py-12">
            <x-tardis::icon name="table-cells" class="w-16 h-16 mx-auto opacity-30" />
            <h3 class="text-lg font-semibold mt-4">BREAD table coming soon</h3>
        </div>
    </div>
</div>
