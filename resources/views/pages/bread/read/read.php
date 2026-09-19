<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Tardis\Bread\Repositories\JsonBreadRepository;

new #[Title('View')] #[Layout('tardis::layouts.admin')] class extends Component
{
    public string $slug = '';

    public int|string $id = 0;

    public array $bread = [];

    public array $record = [];

    public function mount(string $slug, int|string $id): void
    {
        $this->slug = $slug;
        $this->id = $id;
        $definition = app(JsonBreadRepository::class)->find($slug);

        if (! $definition) {
            abort(404);
        }

        $this->bread = $definition->toArray();
        $modelClass = $this->bread['model'] ?? null;

        if (! $modelClass || ! class_exists($modelClass)) {
            abort(404);
        }

        $this->record = $modelClass::findOrFail($id)->toArray();
    }

    public function getFieldsProperty(): array
    {
        if (empty($this->bread)) {
            return [];
        }

        return array_values(array_filter($this->bread['fields'] ?? [], fn (array $field) => (bool) ($field['read'] ?? true)));
    }
};
