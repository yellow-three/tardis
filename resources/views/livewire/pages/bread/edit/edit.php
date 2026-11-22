<?php

use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;
use Tardis\Auth\BreadAuthorization;
use Tardis\Bread\BreadDefinition;
use Tardis\Bread\BreadSaver;
use Tardis\Bread\FieldValidationRules;
use Tardis\Bread\Livewire\ReadsBreadDefinition;
use Tardis\Bread\MissingColumnsException;
use Tardis\Formfields\Formfield;
use Tardis\Formfields\FormfieldManager;
use Tardis\Formfields\Types\HasOneField;
use Tardis\Support\Translation;

new #[Title('tardis::shell.titles.edit')] #[Layout('tardis::layouts.admin')] class extends Component
{
    use ReadsBreadDefinition;
    use WithFileUploads;

    /** Field types whose control searches the related records. */
    private const SEARCHABLE_RELATIONS = ['belongs_to', 'has_one', 'belongs_to_many'];

    #[Locked]
    public string $slug = '';

    #[Locked]
    public int|string $id = 0;

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

    /**
     * Runs on every request, not only on mount: Livewire keeps component state
     * between requests, so a permission removed while the page is open has to
     * stop the next one. mount() still authorises the first render, where the
     * slug is not known yet.
     */
    public function boot(): void
    {
        if ($this->slug !== '') {
            app(BreadAuthorization::class)->authorize('edit', $this->slug);
        }
    }

    /** @return array<string, mixed> read from the slug on every request */
    #[Computed]
    public function bread(): array
    {
        return $this->breadArray();
    }

    public function mount(string $slug, int|string $id): void
    {
        $this->slug = $slug;
        $this->id = $id;
        $this->definition(); // a missing definition is a 404 before anything else
        $modelClass = $this->bread['model'] ?? null;

        if (! BreadDefinition::isModelClass($modelClass)) {
            abort(404);
        }

        app(BreadAuthorization::class)->authorize('edit', $this->slug);

        $record = $this->definition()->query()->findOrFail($id);
        // Livewire sends public properties to the browser, so only the key and
        // the columns this form edits are kept; anything else on the row stays
        // on the server.
        $this->record = [$record->getKeyName() => $record->getKey()];
        $this->form = array_intersect_key($record->toArray(), array_flip(array_filter(array_column($this->fields, 'name'))));

        $fieldObjects = collect($this->formfields)->keyBy(fn (Formfield $field) => $field->name);

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
            } elseif ($type === 'has_one' && $fieldObjects->get($name) instanceof HasOneField) {
                $this->form[$name] = $fieldObjects->get($name)->currentKey($record);
            } elseif ($type === 'has_many' && ! empty($field['relation'])) {
                $this->form[$name] = $record->{$field['relation']}()->get()->toArray();
            } elseif (! empty($field['translatable'])) {
                $this->form[$name] = Translation::normalize(
                    $record->{$name} ?? null,
                    Translation::locales($field['locales'] ?? null),
                );
            } elseif ($fieldObjects->has($name)) {
                // The stored value, shaped for the form control by the field.
                $this->form[$name] = $fieldObjects->get($name)->edit($record->{$name} ?? null);
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
    #[Computed]
    public function translatableLocales(): array
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

            if (! $name || ! in_array($field['type'] ?? null, self::SEARCHABLE_RELATIONS, true)) {
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

        if (! $field || ! in_array($field['type'] ?? null, self::SEARCHABLE_RELATIONS, true)) {
            return;
        }

        $relationField = app(FormfieldManager::class)->fields([$field])[0] ?? null;

        if (! $relationField instanceof Formfield || ! method_exists($relationField, 'searchOptions')) {
            return;
        }

        $selected = $this->form[$fieldName] ?? [];

        $this->relationResults[$fieldName] = $relationField->searchOptions(
            (string) ($this->relationSearch[$fieldName] ?? ''),
            is_array($selected) ? $selected : [$selected],
        );
    }

    /**
     * The field objects behind the form; each one renders its own control.
     *
     * @return array<int, Formfield>
     */
    #[Computed]
    public function formfields(): array
    {
        return app(FormfieldManager::class)->fields($this->fields);
    }

    #[Computed]
    public function layoutFields(): array
    {
        $layout = $this->bread['layout'] ?? [];
        $viewLayout = $layout['view'] ?? $layout['read'] ?? [];
        $editLayout = $layout['edit'] ?? [];

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

    #[Computed]
    public function fields(): array
    {
        if (empty($this->bread)) {
            return [];
        }

        return array_values(array_filter($this->bread['fields'] ?? [], fn (array $field) => (bool) ($field['edit'] ?? true)));
    }

    protected function validationRules(): array
    {
        return FieldValidationRules::for($this->fields, $this->form, $this->activeLocale, 'edit');
    }

    public function save(): void
    {
        $this->validate($this->validationRules());

        $modelClass = $this->bread['model'] ?? null;

        if (BreadDefinition::isModelClass($modelClass)) {
            $record = $this->definition()->query()->findOrFail($this->id);

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
        $this->redirect(url(trim(config('tardis.admin.prefix', 'admin'), '/').'/'.$this->slug), navigate: true);
    }
};
