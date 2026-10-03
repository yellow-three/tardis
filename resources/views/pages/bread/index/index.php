<?php

use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Tardis\Auth\BreadAuthorization;
use Tardis\Bread\BreadDefinition;
use Tardis\Bread\BreadListing;
use Tardis\Bread\BreadManager;
use Tardis\Bread\BreadQuery;
use Tardis\Events\BreadRecordDeleted;

new #[Title('BREAD')] #[Layout('tardis::layouts.admin')] class extends Component
{
    use WithPagination;

    #[Locked]
    public string $slug = '';

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $sort = '';

    #[Url(except: 'asc')]
    public string $direction = 'asc';

    #[Url(except: 15)]
    public int $perPage = 15;

    #[Url(except: 'without')]
    public string $trashed = 'without';

    #[Locked]
    public array $bread = [];

    public function mount(string $slug): void
    {
        $this->slug = $slug;
        $definition = app(BreadManager::class)->find($slug);

        if (! $definition) {
            abort(404);
        }

        $this->bread = $definition->toArray();

        app(BreadAuthorization::class)->authorize('browse', $this->slug);
    }

    protected function bread(): BreadDefinition
    {
        return BreadDefinition::fromArray($this->bread);
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingPerPage(): void
    {
        $this->resetPage();
    }

    public function updatingTrashed(): void
    {
        $this->resetPage();
    }

    public function sortBy(string $column): void
    {
        if (! in_array($column, $this->query()->orderable(), true)) {
            return;
        }

        $this->direction = $this->sort === $column && $this->direction === 'asc' ? 'desc' : 'asc';
        $this->sort = $column;
        $this->resetPage();
    }

    public function query(): BreadQuery
    {
        return new BreadQuery($this->bread());
    }

    #[Computed]
    public function listing(): ?BreadListing
    {
        $model = $this->bread['model'] ?? null;

        if (! $model || ! class_exists($model)) {
            return null;
        }

        return $this->query()->listing($this->search, $this->sort ?: null, $this->direction, $this->perPage, $this->trashed);
    }

    public function getRowsProperty()
    {
        return $this->listing?->rows ?? collect();
    }

    public function getExecutionMsProperty(): float
    {
        return $this->listing?->executionMs ?? 0.0;
    }

    public function getWarningsProperty(): array
    {
        return $this->listing?->warnings ?? [];
    }

    public function getVisibleFieldsProperty(): array
    {
        if (empty($this->bread)) {
            return [];
        }

        return array_values(array_filter($this->bread['fields'] ?? [], fn (array $field) => (bool) ($field['browse'] ?? true)));
    }

    public function getCreateUrlProperty(): string
    {
        return url(trim(config('tardis.admin.prefix', 'admin'), '/').'/'.$this->slug.'/create');
    }

    protected function findRecord(int|string $id): Model
    {
        $model = $this->bread['model'] ?? null;

        if (! $model || ! class_exists($model)) {
            abort(404);
        }

        return $this->query()->build(trashed: 'with')->findOrFail($id);
    }

    public function delete(int|string $id): void
    {
        app(BreadAuthorization::class)->authorize('delete', $this->slug);

        $item = $this->findRecord($id);
        $item->delete();

        BreadRecordDeleted::dispatch($this->slug, $item);
        session()->flash('message', __('tardis::bread.item_deleted'));
    }

    public function restore(int|string $id): void
    {
        app(BreadAuthorization::class)->authorize('edit', $this->slug);

        $item = $this->findRecord($id);

        if (method_exists($item, 'restore')) {
            $item->restore();
            session()->flash('message', __('tardis::bread.item_restored'));
        }
    }

    public function forceDelete(int|string $id): void
    {
        app(BreadAuthorization::class)->authorize('delete', $this->slug);

        $item = $this->findRecord($id);

        if (method_exists($item, 'forceDelete')) {
            $item->forceDelete();

            BreadRecordDeleted::dispatch($this->slug, $item);
            session()->flash('message', __('tardis::bread.item_deleted_permanently'));
        }
    }
};
