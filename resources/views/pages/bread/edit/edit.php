<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;
use Tardis\Auth\BreadAuthorization;
use Tardis\Bread\BreadDefinition;
use Tardis\Bread\BreadManager;
use Tardis\Bread\BreadSaver;
use Tardis\Bread\FieldValidationRules;
use Tardis\Bread\MissingColumnsException;
use Tardis\Classes\Translation;
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

    /**
     * The locale a translatable field's control is showing. One locale for the
     * whole page, so switching tabs reveals another language's inputs without
     * discarding what has already been typed into the current one.
     */
    public string $activeLocale = '';

    public function mount(string $slug, int|string $id): void
    {
        $this->slug = $slug;
        $this->id = $id;
        $definition = app(BreadManager::class)->find($slug);

        if (! $definition) {
            abort(404);
        }

        $this->bread = $definition->toDisplayArray();
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

        $locales = $this->translatableLocales;
        $current = (string) app()->getLocale();

        $this->activeLocale = in_array($current, $locales, true) ? $current : ($locales[0] ?? '');

        $this->initRelationSearch();
    }

    /**
     * Every locale any translatable field on this page declares, in order.
     *
     * @return array<int, string>
     */
    public function getTranslatableLocalesProperty(): array
    {
        $locales = [];

        foreach ($this->fields as $field) {
            if (empty($field['translatable'])) {
                continue;
            }

            foreach (Translation::locales($field['locales'] ?? null) as $locale) {
                $locales[$locale] = true;
            }
        }

        return array_keys($locales);
    }

    /**
     * Only a locale one of these fields actually declares is accepted, so a
     * crafted request cannot leave the page rendering a locale nothing has.
     */
    public function setActiveLocale(string $locale): void
    {
        if (in_array($locale, $this->translatableLocales, true)) {
            $this->activeLocale = $locale;
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
        return FieldValidationRules::for($this->fields, $this->form, $this->activeLocale);
    }

    public function save(): void
    {
        $this->validate($this->validationRules());

        $modelClass = $this->bread['model'] ?? null;

        if ($modelClass && class_exists($modelClass)) {
            $record = BreadDefinition::fromArray($this->bread)->query()->findOrFail($this->id);

            try {
                app(BreadSaver::class)->update($this->slug, $record, $this->fields, $this->form);
            } catch (MissingColumnsException $e) {
                $this->addError('form', __('tardis::bread.fields_required_by_database', [
                    'fields' => implode(', ', $e->columns),
                ]));

                return;
            }
        }

        session()->flash('message', __('tardis::bread.item_updated'));
        $this->redirect(url(trim(config('tardis.admin.prefix', 'admin'), '/').'/'.$this->slug));
    }
};
