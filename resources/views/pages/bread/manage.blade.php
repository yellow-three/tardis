<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Tardis\Bread\BreadManager;

new #[Title('BREAD Management')] #[Layout('tardis::layouts.admin')] class extends Component
{
    #[\Livewire\Attributes\Computed]
    public function breads()
    {
        return app(BreadManager::class)->all();
    }
}; ?>

<div>
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold">BREAD Management</h1>
            <p class="text-base-content/60 mt-1">Manage your Browse, Read, Edit, Add, Delete definitions</p>
        </div>
    </div>

    @if ($this->breads->isEmpty())
        <div class="card bg-base-100 shadow">
            <div class="card-body text-center py-12">
                <x-heroicon-o-table-cells class="w-16 h-16 mx-auto opacity-30" />
                <h3 class="text-lg font-semibold mt-4">No BREAD definitions found</h3>
                <p class="text-base-content/60 mt-2">
                    Create a BREAD definition to get started
                </p>
            </div>
        </div>
    @else
        <div class="card bg-base-100 shadow">
            <div class="overflow-x-auto">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Slug</th>
                            <th>Source</th>
                            <th class="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($this->breads as $slug => $bread)
                            <tr>
                                <td class="font-semibold">{{ $bread['name'] ?? $slug }}</td>
                                <td><code class="badge badge-ghost badge-sm">{{ $slug }}</code></td>
                                <td><span class="badge badge-info badge-sm">{{ $bread['source'] ?? 'unknown' }}</span></td>
                                <td class="text-right">
                                    <a href="{{ route('tardis.bread.index', ['slug' => $slug]) }}" class="btn btn-ghost btn-sm">
                                        Browse
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
