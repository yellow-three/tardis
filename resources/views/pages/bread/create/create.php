<?php

use Illuminate\Http\UploadedFile;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;
use Tardis\Bread\BreadManager;
use Tardis\Classes\Translation;
use Tardis\Formfields\Types\BelongsToManyField;
use Tardis\Manager\FormfieldManager;

new #[Title('Create')] #[Layout('tardis::layouts.admin')] class extends Component
{
    use WithFileUploads;

    public string $slug = '';

    public array $bread = [];

    public array $form = [];

    public array $relationSearch = [];

    public array $relationResults = [];

    public function mount(string $slug): void
    {
        $this->slug = $slug;
        $definition = app(BreadManager::class)->find($slug);

        if (! $definition) {
            abort(404);
        }

        $this->bread = $definition->toArray();

        $this->initTranslatableFields();
        $this->initRelationSearch();
    }

    public function initTranslatableFields(): void
    {
        foreach ($this->fields as $field) {
            $name = $field['name'] ?? null;

            if (! $name || empty($field['translatable'])) {
                continue;
            }

            $this->form[$name] = Translation::normalize(null, Translation::locales($field['locales'] ?? null));
        }
    }

    public function initRelationSearch(): void
    {
        foreach ($this->fields as $field) {
            $name = $field['name'] ?? null;

            if (! $name || ($field['type'] ?? null) !== 'belongs_to_many') {
                continue;
            }

            $this->relationSearch[$name] = '';
            $this->searchRelationOptions($name);
        }
    }

    public function updated($name, $value): void
    {
        if (str_starts_with((string) $name, 'relationSearch.')) {
            $this->searchRelationOptions(substr((string) $name, strlen('relationSearch.')));
        }
    }

    public function searchRelationOptions(string $fieldName): void
    {
        $field = collect($this->fields)->first(fn (array $field) => ($field['name'] ?? null) === $fieldName);

        if (! $field || ($field['type'] ?? null) !== 'belongs_to_many') {
            return;
        }

        $relationField = app(FormfieldManager::class)->fields([$field])[0] ?? null;

        if (! $relationField instanceof BelongsToManyField) {
            return;
        }

        $this->relationResults[$fieldName] = $relationField->searchOptions(
            (string) ($this->relationSearch[$fieldName] ?? ''),
            (array) ($this->form[$fieldName] ?? []),
        );
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

            if (($field['type'] ?? null) === 'file' && ($this->form[$name] ?? null) instanceof UploadedFile) {
                $rules['form.'.$name] .= '|file';

                if (! empty($field['mimes'])) {
                    $rules['form.'.$name] .= '|mimes:'.implode(',', (array) $field['mimes']);
                }

                if (! empty($field['max_size'])) {
                    $rules['form.'.$name] .= '|max:'.(int) $field['max_size'];
                }
            }
        }

        return $rules;
    }

    /**
     * Columns the table declares NOT NULL without a default.
     *
     * A BREAD definition can mark such a field as optional (its validation
     * list is empty), so an untouched field would otherwise be written as
     * NULL and abort the whole insert with an opaque SQLSTATE 23000 — the
     * user only sees a failed save, with no hint which field was at fault.
     *
     * @return array<int, string>
     */
    protected function requiredColumns(string $modelClass, array $fields): array
    {
        try {
            $table = (new $modelClass)->getTable();

            $schema = (new $modelClass)->getConnection()
                ->getSchemaBuilder()
                ->getColumns($table);
        } catch (Throwable) {
            // A missing/renamed table is reported by the insert itself.
            return [];
        }

        $inDefinition = array_map(
            fn ($field) => $field->name,
            $fields,
        );

        $required = [];

        foreach ($schema as $column) {
            // A column with a default can be omitted from the insert; a NULL
            // default is still a default, so test the value and not just the
            // key's presence (the MySQL schema always reports the key).
            if (($column['nullable'] ?? true) || ($column['default'] ?? null) !== null) {
                continue;
            }

            if (in_array($column['name'], $inDefinition, true)) {
                $required[] = $column['name'];
            }
        }

        return $required;
    }

    public function save(): void
    {
        $this->validate($this->validationRules());

        $modelClass = $this->bread['model'] ?? null;

        if (! $modelClass || ! class_exists($modelClass)) {
            session()->flash('error', 'Unable to determine model class.');

            return;
        }

        $fields = app(FormfieldManager::class)->fields($this->fields);
        $data = [];
        $relations = [];

        foreach ($fields as $field) {
            $value = $this->form[$field->name] ?? $field->default;

            if ($field->skipWhenBlank() && blank($value)) {
                continue;
            }

            if ($field->isRelation()) {
                $relations[] = [$field, $value];

                continue;
            }

            $data[$field->name] = $field->transform($value);
        }

        // A field left blank in the form must not be written as NULL when the
        // table refuses nulls, otherwise the whole insert dies on SQLSTATE
        // 23000 and the user is told nothing about which field was missing.
        $missing = [];

        foreach ($this->requiredColumns($modelClass, $fields) as $column) {
            if (! array_key_exists($column, $data) || blank($data[$column])) {
                $missing[] = $column;
            }
        }

        if ($missing !== []) {
            $this->addError('form', sprintf(
                'These fields are required by the database and cannot be left empty: %s.',
                implode(', ', $missing),
            ));

            return;
        }

        $model = $modelClass::create($data);

        foreach ($relations as [$field, $value]) {
            $field->stored($value, $model);
        }

        session()->flash('message', 'Item created successfully.');
        $this->redirect(url(trim(config('tardis.admin.prefix', 'admin'), '/').'/'.$this->slug));
    }
};
