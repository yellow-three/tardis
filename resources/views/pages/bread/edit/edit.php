<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Tardis\Bread\Repositories\JsonBreadRepository;

new #[Title('Edit')] #[Layout('tardis::layouts.admin')] class extends Component
{
    public string $slug = '';

    public int|string $id = 0;

    public array $bread = [];

    public array $record = [];

    public array $form = [];

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

        $record = $modelClass::findOrFail($id);
        $this->record = $record->toArray();
        $this->form = $this->record;
    }

    public function getFieldsProperty(): array
    {
        if (empty($this->bread)) {
            return [];
        }

        return array_values(array_filter($this->bread['fields'] ?? [], fn (array $field) => (bool) ($field['edit'] ?? true)));
    }

    protected function validationRules(): array
    {
        $rules = [];

        foreach ($this->fields as $field) {
            $name = $field['name'] ?? null;

            if (! $name) {
                continue;
            }

            $fieldRules = $field['validation'] ?? [];
            $rules['form.'.$name] = in_array('required', $fieldRules, true) ? 'required' : 'nullable';
        }

        return $rules;
    }

    public function save(): void
    {
        $this->validate($this->validationRules());

        $modelClass = $this->bread['model'] ?? null;

        if ($modelClass && class_exists($modelClass)) {
            $record = $modelClass::findOrFail($this->id);
            $record->update($this->form);
        }

        session()->flash('message', 'Item updated successfully.');
        $this->redirect(url(trim(config('tardis.admin.prefix', 'admin'), '/').'/'.$this->slug));
    }
};
