<?php

namespace Tardis\Livewire;

use Livewire\Component;
use Tardis\Models\Media;

class StatsMedia extends Component
{
    public int $count;

    public string $totalSize;

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

    public function render()
    {
        return view('tardis-media::livewire.stats-media');
    }
}
