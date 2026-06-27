<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Edit')] #[Layout('tardis::layouts.admin')] class extends Component
{
    public string $slug = '';

    public int|string $id = 0;

    public function mount(string $slug, int|string $id): void
    {
        $this->slug = $slug;
        $this->id = $id;
    }
}; ?>

<div>
    <h1 class="text-2xl font-bold mb-6">Edit {{ ucfirst($slug) }}</h1>

    <div class="card bg-base-100 shadow">
        <div class="card-body text-center py-12">
            <x-heroicon-o-pencil-square class="w-16 h-16 mx-auto opacity-30" />
            <h3 class="text-lg font-semibold mt-4">BREAD form coming soon</h3>
        </div>
    </div>
</div>
