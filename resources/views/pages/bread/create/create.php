<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Tardis\Bread\BreadManager;

new #[Title('Create')] #[Layout('tardis::layouts.admin')] class extends Component
{
    public string $slug = '';

    public array $bread = [];

    public array $form = [];

    public function mount(string $slug): void
    {
        $this->slug = $slug;
        $definition = app(BreadManager::class)->find($slug);

        if (! $definition) {
            abort(404);
        }

        $this->bread = $definition->toArray();
    }

    public function getFieldsProperty(): array
    {
        if (empty($this->bread)) {
            return [];
        }

        return array_values(array_filter($this->bread['fields'] ?? [], fn (array $field) => (bool) ($field['add'] ?? true)));
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

        if (! $modelClass || ! class_exists($modelClass)) {
            session()->flash('error', 'Unable to determine model class.');

            return;
        }

        $modelClass::create($this->form);

        session()->flash('message', 'Item created successfully.');
        $this->redirect(url(trim(config('tardis.admin.prefix', 'admin'), '/').'/'.$this->slug));
    }
};
