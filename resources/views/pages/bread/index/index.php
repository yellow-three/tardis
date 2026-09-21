<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Tardis\Bread\Repositories\JsonBreadRepository;
use Tardis\Events\BreadDeleted;

new #[Title('BREAD')] #[Layout('tardis::layouts.admin')] class extends Component
{
    public string $slug = '';

    public string $search = '';

    public array $bread = [];

    public function mount(string $slug): void
    {
        $this->slug = $slug;
        $definition = app(JsonBreadRepository::class)->find($slug);

        if (! $definition) {
            abort(404);
        }

        $this->bread = $definition->toArray();
    }

    public function getRowsProperty()
    {
        if (empty($this->bread)) {
            return collect();
        }

        $model = $this->bread['model'] ?? null;

        if (! $model || ! class_exists($model)) {
            return collect();
        }

        $query = $model::query();

        if (! empty($this->bread['order_column'])) {
            $query->orderBy($this->bread['order_column'], $this->bread['order_direction'] ?? 'asc');
        }

        if (! empty($this->bread['search_key']) && $this->search !== '') {
            $query->where($this->bread['search_key'], 'like', '%'.$this->search.'%');
        }

        return $query->paginate(15);
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

    public function delete(int|string $id): void
    {
        $model = $this->bread['model'] ?? null;

        if (! $model || ! class_exists($model)) {
            abort(404);
        }

        $item = $model::findOrFail($id);
        $item->delete();

        BreadDeleted::dispatch($this->slug, $item);
        session()->flash('message', 'Item deleted successfully.');
    }
};
