<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Tardis\Bread\Repositories\JsonBreadRepository;

new #[Title('Search')] #[Layout('tardis::layouts.admin')] class extends Component
{
    public string $query = '';

    public array $results = [];

    public bool $showResults = false;

    public function updatedQuery(): void
    {
        if (strlen($this->query) < 2) {
            $this->results = [];
            $this->showResults = false;
            return;
        }

        $this->search();
    }

    public function search(): void
    {
        if (strlen($this->query) < 2) {
            $this->results = [];
            $this->showResults = false;
            return;
        }

        $repo = app(JsonBreadRepository::class);
        $breads = $repo->all();
        $this->results = [];

        foreach ($breads as $slug => $bread) {
            if (! $bread->searchKey) {
                continue;
            }

            $model = $bread->model;
            if (! class_exists($model)) {
                continue;
            }

            try {
                $items = $model::where($bread->searchKey, 'like', '%'.$this->query.'%')
                    ->limit(5)
                    ->get();

                if ($items->isNotEmpty()) {
                    $this->results[] = [
                        'slug' => $slug,
                        'name' => $bread->namePlural,
                        'items' => $items->map(fn ($item) => [
                            'id' => $item->id,
                            'title' => $item->{$bread->searchKey} ?? "Item #{$item->id}",
                            'url' => route('tardis.bread.edit.item', ['slug' => $slug, 'id' => $item->id]),
                        ])->toArray(),
                    ];
                }
            } catch (\Throwable) {
                continue;
            }
        }

        $this->showResults = true;
    }

    public function clearSearch(): void
    {
        $this->query = '';
        $this->results = [];
        $this->showResults = false;
    }
}; ?>

<div>
    <div class="mb-6">
        <h1 class="text-2xl font-bold">Search</h1>
        <p class="text-base-content/60 mt-1">Search across all BREADs</p>
    </div>

    <!-- Search Input -->
    <div class="card bg-base-100 shadow-sm mb-6">
        <div class="card-body p-4">
            <div class="flex gap-2">
                <input
                    type="text"
                    wire:model.live.debounce.300ms="query"
                    class="input input-bordered flex-1"
                    placeholder="Search for anything..."
                    autofocus
                />
                <button wire:click="search" class="btn btn-primary gap-2">
                    <x-tardis::icon name="document-text" class="w-4 h-4" />
                    Search
                </button>
                @if ($query)
                    <button wire:click="clearSearch" class="btn btn-ghost">
                        <x-tardis::icon name="x-mark" class="w-4 h-4" />
                    </button>
                @endif
            </div>
        </div>
    </div>

    <!-- Results -->
    @if ($showResults)
        @if (empty($results))
            <div class="card bg-base-100 shadow-sm">
                <div class="card-body text-center py-12">
                    <x-tardis::icon name="document-text" class="w-16 h-16 mx-auto opacity-20" />
                    <h3 class="text-lg font-semibold mt-4">No results found</h3>
                    <p class="text-base-content/60 mt-2">Try a different search term</p>
                </div>
            </div>
        @else
            <div class="space-y-4">
                @foreach ($results as $result)
                    <div class="card bg-base-100 shadow-sm">
                        <div class="card-body">
                            <h3 class="card-title text-lg">{{ $result['name'] }}</h3>
                            <div class="divide-y divide-base-200">
                                @foreach ($result['items'] as $item)
                                    <a href="{{ $item['url'] }}" class="flex items-center gap-3 py-3 hover:bg-base-200 px-2 rounded transition-colors">
                                        <x-tardis::icon name="database" class="w-4 h-4 text-primary" />
                                        <span class="font-medium">{{ $item['title'] }}</span>
                                        <span class="text-xs opacity-40 ml-auto">#{{ $item['id'] }}</span>
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    @endif
</div>
