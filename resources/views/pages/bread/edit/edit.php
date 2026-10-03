<?php

use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;
use Tardis\Auth\BreadAuthorization;
use Tardis\Bread\BreadDefinition;
use Tardis\Bread\BreadManager;
use Tardis\Bread\FieldValidationRules;
use Tardis\Classes\Translation;
use Tardis\Events\BreadRecordUpdated;
use Tardis\Formfields\Formfield;
use Tardis\Formfields\Types\BelongsToManyField;
use Tardis\Manager\FormfieldManager;

new #[Title('Edit')] #[Layout('tardis::layouts.admin')] class extends Component
{
    use WithFileUploads;

    #[Locked]
    public string $slug = '';

    #[Locked]
    public int|string $id = 0;

    #[Locked]
    public array $bread = [];

    #[Locked]
    public array $record = [];

    public array $form = [];

    public array $relationSearch = [];

    public array $relationResults = [];

    public function mount(string $slug, int|string $id): void
    {
        $this->slug = $slug;
        $this->id = $id;
        $definition = app(BreadManager::class)->find($slug);

        if (! $definition) {
            abort(404);
        }

        $this->bread = $definition->toArray();
        $modelClass = $this->bread['model'] ?? null;

        if (! $modelClass || ! class_exists($modelClass)) {
            abort(404);
        }

        app(BreadAuthorization::class)->authorize('edit', $this->slug);

        $record = BreadDefinition::fromArray($this->bread)->query()->findOrFail($id);
        $this->record = $record->toArray();
        $this->form = $this->record;

        foreach ($this->fields as $field) {
            $name = $field['name'] ?? null;
            $type = $field['type'] ?? null;

            if (! $name || ! $type) {
                continue;
            }

            if ($type === 'password') {
                // Never prefill a password; blank means "keep the current one".
                $this->form[$name] = '';
            } elseif ($type === 'belongs_to_many' && ! empty($field['relation'])) {
                $this->form[$name] = $record->{$field['relation']}()->get()->modelKeys();
            } elseif ($type === 'has_many' && ! empty($field['relation'])) {
                $this->form[$name] = $record->{$field['relation']}()->get()->toArray();
            } elseif (! empty($field['translatable'])) {
                $this->form[$name] = Translation::normalize(
                    $record->{$name} ?? null,
                    Translation::locales($field['locales'] ?? null),
                );
            }
        }

        $this->initRelationSearch();
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

    /**
     * The field objects behind the form; each one renders its own control.
     *
     * @return array<int, Formfield>
     */
    public function getFormfieldsProperty(): array
    {
        return app(FormfieldManager::class)->fields($this->fields);
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
        return FieldValidationRules::for($this->fields, $this->form);
    }

    /**
     * Columns the table declares NOT NULL without a default.
     *
     * Mirrors the create page: clearing such a field would otherwise be
     * written as NULL and abort the whole update with SQLSTATE 23000.
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
            // A missing/renamed table is reported by the update itself.
            return [];
        }

        $inDefinition = array_map(
            fn ($field) => $field->name,
            $fields,
        );

        $required = [];

        foreach ($schema as $column) {
            // A column with a default can be omitted from the statement; a
            // NULL default is still a default, so test the value and not just
            // the key's presence (the MySQL schema always reports the key).
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

        if ($modelClass && class_exists($modelClass)) {
            $record = BreadDefinition::fromArray($this->bread)->query()->findOrFail($this->id);

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

            // A field the user cleared must not be written as NULL when the
            // table refuses nulls, otherwise the update dies on SQLSTATE 23000
            // and nothing tells the user which field was at fault.
            $missing = [];

            foreach ($this->requiredColumns($modelClass, $fields) as $column) {
                if (! array_key_exists($column, $data) || blank($data[$column])) {
                    $missing[] = $column;
                }
            }

            if ($missing !== []) {
                $this->addError('form', __('tardis::bread.fields_required_by_database', [
                    'fields' => implode(', ', $missing),
                ]));

                return;
            }

            // The columns are written before the relations, so a relation that
            // dies mid-loop would otherwise leave the update committed with only
            // part of the relation set applied. Only the database writes are
            // wrapped: uploads were already moved to disk during transform().
            DB::transaction(function () use ($record, $data, $relations) {
                $record->update($data);

                foreach ($relations as [$field, $value]) {
                    $field->updated($value, $record);
                }
            });

            $changes = $record->getChanges();

            if ($changes !== []) {
                BreadRecordUpdated::dispatch(
                    $this->slug,
                    $record,
                    array_intersect_key($record->getPrevious(), $changes),
                    $changes,
                );
            }
        }

        session()->flash('message', __('tardis::bread.item_updated'));
        $this->redirect(url(trim(config('tardis.admin.prefix', 'admin'), '/').'/'.$this->slug));
    }
};
