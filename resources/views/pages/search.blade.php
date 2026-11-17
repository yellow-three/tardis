<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Tardis\Auth\BreadAuthorization;
use Tardis\Bread\BreadDefinition;
use Tardis\Bread\BreadManager;

new #[Title('tardis::search.search')] #[Layout('tardis::layouts.admin')] class extends Component
{
    public string $query = '';

    public array $results = [];

    public bool $showResults = false;

    public function mount(): void
    {
        $this->query = (string) request()->query('query', '');

        if (strlen($this->query) >= 2) {
            $this->search();
        }
    }

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

        $repo = app(BreadManager::class);
        $authorization = app(BreadAuthorization::class);
        $breads = $repo->all();
        $this->results = [];

        foreach ($breads as $slug => $bread) {
            if (! $bread->searchKey) {
                continue;
            }

            if (! $authorization->allows('browse', (string) $slug)) {
                continue;
            }

            $model = $bread->model;
            if (! BreadDefinition::isModelClass($model)) {
                continue;
            }

            try {
                // Through the definition, so its scope applies here as it does
                // on the listing; % and _ in the query are matched literally.
                $term = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $this->query).'%';
                $query = $bread->query();
                $column = $query->getQuery()->getGrammar()->wrap($bread->searchKey);

                $items = $query->whereRaw($column." like ? escape '!'", [$term])
                    ->limit(5)
                    ->get();

                // Link to the page the user may open: edit, else read, else the
                // listing filtered to the record (browse is all they hold).
                $route = match (true) {
                    $authorization->allows('edit', (string) $slug) => 'tardis.bread.edit.item',
                    $authorization->allows('read', (string) $slug) => 'tardis.bread.read',
                    default => null,
                };

                if ($items->isNotEmpty()) {
                    $this->results[] = [
                        'slug' => $slug,
                        'name' => $bread->resolvedNamePlural(),
                        'items' => $items->map(fn ($item) => [
                            'id' => $item->getKey(),
                            'title' => $item->{$bread->searchKey} ?? '#'.$item->getKey(),
                            'url' => $route !== null
                                ? route($route, ['slug' => $slug, 'id' => $item->getKey()])
                                : route('tardis.bread.index', ['slug' => $slug, 'search' => (string) $item->{$bread->searchKey}]),
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
    <x-tardis::page-header :title="__('tardis::search.search')" :description="__('tardis::search.search_across_all_breads')" />

    <!-- Search Input -->
    <div class="card bg-base-100 mb-6 border border-base-300">
        <div class="card-body p-4">
            <div class="flex gap-2">
                <input
                    type="text"
                    wire:model.live.debounce.300ms="query"
                    class="input flex-1"
                    placeholder="{{ __('tardis::search.search_for_anything') }}"
                    aria-label="{{ __('tardis::search.search_across_bread_resources') }}"
                    autocomplete="off"
                />
                <button wire:click="search" class="btn btn-primary gap-2">
                    <x-tardis::icon name="document-text" class="w-4 h-4" />
                    {{ __('tardis::search.search') }}
                </button>
                @if ($query)
                    <button wire:click="clearSearch" class="btn btn-ghost" aria-label="{{ __('tardis::search.clear_search') }}">
                        <x-tardis::icon name="x-mark" class="w-4 h-4" aria-hidden="true" />
                    </button>
                @endif
            </div>
        </div>
    </div>

    <!-- Results -->
    @if ($showResults)
        @if (empty($results))
            <div class="card bg-base-100 border border-base-300" aria-live="polite">
                <div class="card-body text-center py-12">
                    <x-tardis::icon name="document-text" class="w-16 h-16 mx-auto text-base-content/20" />
                    <h3 class="text-lg font-semibold mt-4">{{ __('tardis::search.no_results_found') }}</h3>
                    <p class="text-base-content/60 mt-2">{{ __('tardis::search.try_a_different_search_term') }}</p>
                </div>
            </div>
        @else
            <div class="space-y-4">
                @foreach ($results as $result)
                    <div class="card bg-base-100 border border-base-300">
                        <div class="card-body">
                            <h3 class="card-title text-lg">{{ $result['name'] }}</h3>
                            <div class="divide-y divide-base-200">
                                @foreach ($result['items'] as $item)
                                    <a href="{{ $item['url'] }}" class="flex items-center gap-3 py-3 hover:bg-base-200 px-2 rounded transition-colors">
                                        <x-tardis::icon name="database" class="w-4 h-4 text-primary" />
                                        <span class="font-medium">{{ $item['title'] }}</span>
                                        <span class="text-xs text-base-content/40 ml-auto">#{{ $item['id'] }}</span>
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
