<?php

use Livewire\Component;
use Tardis\Models\Media;

new class extends Component
{
    public int $count = 0;

    public string $totalSize = '0 B';

    public function mount(): void
    {
        $this->count = Media::count();
        $bytes = Media::sum('size');
        $units = ['B', 'KB', 'MB', 'GB'];
        for ($i = 0; $bytes > 1024; $i++) {
            $bytes /= 1024;
        }
        $this->totalSize = $this->count > 0
            ? round($bytes, 2).' '.($units[$i] ?? 'B')
            : '0 B';
    }
}; ?>

<div class="card bg-base-100 shadow">
    <div class="card-body">
        <div class="flex items-center gap-4">
            <div class="p-3 rounded-box bg-primary/10 text-primary">
                <x-tardis::icon name="photo" class="w-8 h-8" />
            </div>
            <div>
                <p class="text-3xl font-bold">{{ $count }}</p>
                <p class="text-sm opacity-60">Media Files</p>
                @if ($count > 0)
                    <p class="text-xs opacity-40">{{ $totalSize }} total</p>
                @endif
            </div>
        </div>
    </div>
</div>
