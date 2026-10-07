<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Tardis\Auth\BreadAuthorization;
use Tardis\Bread\BreadDefinition;
use Tardis\Bread\BreadManager;
use Tardis\Formfields\Formfield;
use Tardis\Manager\FormfieldManager;

new #[Title('View')] #[Layout('tardis::layouts.admin')] class extends Component
{
    #[Locked]
    public string $slug = '';

    #[Locked]
    public int|string $id = 0;

    #[Locked]
    public array $bread = [];

    #[Locked]
    public array $record = [];

    public function mount(string $slug, int|string $id): void
    {
        $this->slug = $slug;
        $this->id = $id;
        $definition = app(BreadManager::class)->find($slug);

        if (! $definition) {
            abort(404);
        }

        $this->bread = $definition->toDisplayArray();

        app(BreadAuthorization::class)->authorize('read', $this->slug);

        $modelClass = $this->bread['model'] ?? null;

        if (! $modelClass || ! class_exists($modelClass)) {
            abort(404);
        }

        $this->record = BreadDefinition::fromArray($this->bread)->query()->findOrFail($id)->toArray();
    }

    public function getLayoutFieldsProperty(): array
    {
        $layout = $this->bread['layout'] ?? [];
        $viewLayout = $layout['view'] ?? $layout['read'] ?? [];

        if (! empty($viewLayout) && is_array($viewLayout)) {
            $result = [];
            foreach ($viewLayout as $item) {
                if (is_string($item)) {
                    $field = collect($this->fields)->first(fn ($f) => ($f['name'] ?? null) === $item);
                    if ($field && ($field['read'] ?? true)) {
                        $result[] = $field;
                    }
                } elseif (is_array($item) && isset($item['name'])) {
                    $field = collect($this->fields)->first(fn ($f) => ($f['name'] ?? null) === $item['name']);
                    if ($field && ($field['read'] ?? true)) {
                        $result[] = array_merge($field, $item);
                    }
                }
            }
            if (! empty($result)) {
                return $result;
            }
        }

        return $this->fields;
    }

    public function getFieldsProperty(): array
    {
        if (empty($this->bread)) {
            return [];
        }

        return array_values(array_filter($this->bread['fields'] ?? [], fn (array $field) => (bool) ($field['read'] ?? true)));
    }

    /**
     * The field objects behind the detail view, keyed by name, so each value is
     * shaped by its type (read()).
     *
     * @return array<string, Formfield>
     */
    public function getFormfieldsProperty(): array
    {
        return collect(app(FormfieldManager::class)->fields($this->fields))
            ->keyBy(fn (Formfield $field) => $field->name)
            ->all();
    }
};
