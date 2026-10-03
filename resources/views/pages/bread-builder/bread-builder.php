<?php

use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Tardis\Auth\Abilities;
use Tardis\Auth\BreadAuthorization;
use Tardis\Bread\BreadDefinition;
use Tardis\Bread\BreadManager;
use Tardis\Bread\ModelReflector;
use Tardis\Bread\ReservedSlugs;
use Tardis\Classes\Translation;
use Tardis\Manager\FormfieldManager;

new #[Title('BREAD Builder')] #[Layout('tardis::layouts.admin')] class extends Component
{
    public int $step = 1;

    public string $slug = '';

    public string $model = '';

    public string $name = '';

    public string $namePlural = '';

    public ?string $icon = null;

    public ?string $description = null;

    public array $detectedFields = [];

    public array $detectedRelationships = [];

    public array $fieldConfig = [];

    public array $relationshipConfig = [];

    public string $searchKey = '';

    public ?string $orderColumn = null;

    public string $orderDirection = 'asc';

    public ?string $activeTab = 'fields';

    public array $browseColumns = [];

    public array $editTabs = [];

    /**
     * Ordered list of field names rendered on the read/show view.
     *
     * Stored as an ordered name list rather than a reindexed map so that
     * `fieldConfig` keys (which are field names, referenced by
     * `wire:model="fieldConfig.<name>.type"`) are never invalidated.
     */
    public array $readLayout = [];

    /**
     * User-defined display order for the step 2 field table.
     * Holds field names; `$fieldConfig` itself is never reindexed.
     */
    public array $fieldOrder = [];

    public bool $softDelete = false;

    public bool $editMode = false;

    public ?string $existingSlug = null;

    public bool $showIconPicker = false;

    public string $iconSearch = '';

    public bool $focusMode = false;

    public string $fieldSearch = '';

    /**
     * Tracks whether the slug was typed by hand, so name-driven slug
     * generation never clobbers a deliberate value.
     */
    public bool $slugTouched = false;

    public string $modelTable = '';

    public bool $modelHasSoftDeletes = false;

    public bool $modelHasTimestamps = true;

    /**
     * Definition keys the builder has no form for. They are carried through a
     * load/save round trip so editing a BREAD never drops them.
     *
     * @var array<string, string>
     */
    public array $components = [];

    public ?string $policy = null;

    public ?string $scope = null;

    /**
     * Locale maps loaded from the definition for the three translatable labels.
     *
     * The form edits one resolved string at a time, so the raw map is kept
     * aside and written back untouched unless the user actually retyped the
     * label. Without this, saving a BREAD would silently flatten every locale
     * to the single string currently shown in the input.
     *
     * @var array<string, string>
     */
    public array $nameTranslations = [];

    /** @var array<string, string> */
    public array $namePluralTranslations = [];

    /** @var array<string, string> */
    public array $descriptionTranslations = [];

    /**
     * Runs on every request, not only on mount: Livewire keeps component state
     * between updates, so a permission revoked after the page opened must
     * still stop the next action.
     */
    public function boot(): void
    {
        app(BreadAuthorization::class)->authorizeAbility(Abilities::BREAD);
    }

    public function mount(?string $slug = null): void
    {
        if ($slug) {
            $repo = app(BreadManager::class);
            $bread = $repo->find($slug);

            if ($bread) {
                $this->editMode = true;
                $this->existingSlug = $slug;
                $this->slug = $bread->slug;
                $this->slugTouched = true;
                $this->model = $bread->model;
                $this->nameTranslations = $this->toTranslationMap($bread->name);
                $this->name = $bread->resolvedName();
                $this->namePluralTranslations = $this->toTranslationMap($bread->namePlural);
                $this->namePlural = $bread->resolvedNamePlural();
                $this->icon = $bread->icon;
                $this->descriptionTranslations = $this->toTranslationMap($bread->description);
                $this->description = $bread->resolvedDescription();
                $this->fieldConfig = $bread->fields;
                $this->relationshipConfig = $bread->relationships;
                $this->searchKey = $bread->searchKey ?? '';
                $this->orderColumn = $bread->orderColumn ?? null;
                $this->orderDirection = $bread->orderDirection ?? 'asc';
                $this->softDelete = $bread->softDelete;
                $this->browseColumns = $bread->layout['browse'] ?? [];
                $this->editTabs = $bread->layout['edit'] ?? [];
                $this->readLayout = $bread->layout['read'] ?? [];
                $this->fieldOrder = $bread->layout['field_order'] ?? [];
                $this->components = $bread->components;
                $this->policy = $bread->policy;
                $this->scope = $bread->scope;
                $this->step = 3;
                $this->activeTab = 'general';

                $this->captureModelAnalysis();
                $this->hydrateLayoutDefaults();
            }
        }
    }

    public function detectFields(): void
    {
        $this->validate([
            'model' => 'required|string',
        ]);

        if (! class_exists($this->model)) {
            session()->flash('error', __('tardis::builder.model_not_found'));

            return;
        }

        $this->captureModelAnalysis();

        $this->detectedFields = ModelReflector::getFields($this->model);
        $this->fieldConfig = $this->detectedFields;
        $this->detectedRelationships = ModelReflector::getRelationships(new $this->model);
        $this->relationshipConfig = $this->detectedRelationships;

        foreach ($this->relationshipConfig as $key => $relationship) {
            $this->relationshipConfig[$key]['display_type'] ??= 'select';
        }

        $this->name = class_basename($this->model);
        $this->namePlural = Str::headline(Str::plural(class_basename($this->model)));

        $this->slugTouched = false;
        $this->syncSlug();

        $this->fieldOrder = array_keys($this->fieldConfig);
        $this->readLayout = [];
        $this->browseColumns = [];
        $this->hydrateLayoutDefaults();

        $this->step = 2;
        $this->activeTab = 'fields';
    }

    public function goToStep(int $step): void
    {
        $this->step = $step;

        // Step 3 (Configure) keeps its own tab set; 'fields' belongs to step 2.
        if ($step === 3) {
            $this->activeTab = 'general';
        }

        if ($step === 2) {
            $this->activeTab = 'fields';
        }
    }

    public function save(): void
    {
        $this->validate([
            'slug' => 'required|regex:/^[a-z0-9-]+$/',
            'name' => 'required|string|max:255',
        ]);

        if (ReservedSlugs::has($this->slug)) {
            $this->addError('slug', __('tardis::builder.errors.slug_reserved'));

            return;
        }

        $repo = app(BreadManager::class);

        // Guard against silently overwriting a different BREAD definition.
        if ($repo->find($this->slug) !== null && $this->existingSlug !== $this->slug) {
            $this->addError('slug', __('tardis::builder.errors.slug_taken'));

            return;
        }

        $this->hydrateLayoutDefaults();
        $this->normalizeFieldTypes();

        $bread = BreadDefinition::fromArray([
            'slug' => $this->slug,
            'model' => $this->model,
            'name' => $this->labelPayload($this->name, $this->nameTranslations),
            'name_plural' => $this->labelPayload($this->namePlural, $this->namePluralTranslations),
            'fields' => $this->fieldConfig,
            'relationships' => $this->relationshipConfig,
            'icon' => $this->icon,
            'description' => $this->labelPayload($this->description, $this->descriptionTranslations),
            'search_key' => $this->searchKey ?: null,
            'order_column' => $this->orderColumn,
            'order_direction' => $this->orderDirection,
            'soft_delete' => $this->softDelete,
            'components' => $this->components,
            'policy' => $this->policy,
            'scope' => $this->scope,
            'layout' => [
                'browse' => $this->browseColumns,
                'edit' => $this->editTabs,
                'read' => $this->readLayout,
                'field_order' => $this->fieldOrder,
            ],
        ]);

        $repo->save($bread);

        session()->flash('message', __('tardis::builder.saved'));
        $this->redirect(route('tardis.bread.manage'));
    }

    /**
     * The locale map behind a definition label, or an empty array when the
     * label is a plain string and therefore not translatable.
     *
     * @return array<string, string>
     */
    protected function toTranslationMap(mixed $value): array
    {
        if (is_array($value)) {
            return array_map(strval(...), $value);
        }

        if (! is_string($value) || $value === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? array_map(strval(...), $decoded) : [];
    }

    /**
     * Keep a definition's locale map when its resolved label was not retyped.
     *
     * A deliberate edit replaces the map with the single string now shown in
     * the input, so what gets stored is exactly what the form displayed.
     */
    protected function labelPayload(?string $edited, array $translations): string|array|null
    {
        if ($translations === []) {
            return $edited;
        }

        return $edited === Translation::label($translations) ? $translations : $edited;
    }

    public function getModelOptions(): array
    {
        $path = app_path('Models');
        $models = [];
        $taken = $this->modelsWithBread();

        if (is_dir($path)) {
            foreach (glob($path.'/*.php') as $file) {
                $name = pathinfo($file, PATHINFO_FILENAME);
                $class = "App\\Models\\{$name}";
                if (class_exists($class)) {
                    // A model that already drives a BREAD is reached through that
                    // BREAD, so listing it here would only create duplicates.
                    if (isset($taken[$class]) || isset($taken[$name])) {
                        continue;
                    }

                    $models[$class] = $name;
                }
            }
        }

        return $models;
    }

    /**
     * Models that already have a BREAD, keyed both fully qualified and by
     * basename — stored definitions may reference either form.
     *
     * The BREAD currently being edited is skipped: its own model has to stay
     * selectable, otherwise the definition could never be re-saved.
     *
     * @return array<string, true>
     */
    protected function modelsWithBread(): array
    {
        $taken = [];

        foreach (app(BreadManager::class)->all() as $slug => $bread) {
            if ($this->editMode && $slug === $this->existingSlug) {
                continue;
            }

            if ($bread->model === '') {
                continue;
            }

            $taken[$bread->model] = true;
            $taken[class_basename($bread->model)] = true;
        }

        return $taken;
    }

    public function addEditTab(): void
    {
        $this->editTabs[] = [
            'name' => 'Tab '.(count($this->editTabs) + 1),
            'fields' => [],
        ];
    }

    public function removeEditTab(int $index): void
    {
        unset($this->editTabs[$index]);
        $this->editTabs = array_values($this->editTabs);
    }

    // ---------------------------------------------------------------------
    // Slug handling
    // ---------------------------------------------------------------------

    public function updatedName(): void
    {
        $this->syncSlug();
    }

    public function updatedNamePlural(): void
    {
        $this->syncSlug();
    }

    public function updatedSlug(): void
    {
        $this->slugTouched = true;
    }

    /**
     * Derive the slug from the display names until the user types one by hand.
     */
    protected function syncSlug(): void
    {
        if ($this->slugTouched) {
            return;
        }

        $this->slug = Str::slug($this->namePlural !== '' ? $this->namePlural : $this->name);
    }

    /**
     * @return 'empty'|'invalid'|'reserved'|'taken'|'current'|'available'
     */
    public function getSlugStatusProperty(): string
    {
        if (trim($this->slug) === '') {
            return 'empty';
        }

        if (ReservedSlugs::has($this->slug)) {
            return 'reserved';
        }

        if (! preg_match('/^[a-z0-9-]+$/', $this->slug)) {
            return 'invalid';
        }

        $existing = app(BreadManager::class)->find($this->slug);

        if ($existing === null) {
            return 'available';
        }

        return $this->existingSlug === $this->slug ? 'current' : 'taken';
    }

    // ---------------------------------------------------------------------
    // Field ordering, filtering and bulk toggles
    // ---------------------------------------------------------------------

    /**
     * Field names in display order, appending any fields missing from
     * `$fieldOrder` (e.g. loaded from a definition saved before ordering
     * existed) so nothing can silently disappear from the table.
     *
     * @return list<string>
     */
    public function getOrderedFieldKeysProperty(): array
    {
        $known = array_keys($this->fieldConfig);
        $ordered = array_values(array_filter(
            $this->fieldOrder,
            fn ($key) => array_key_exists($key, $this->fieldConfig)
        ));

        return array_values(array_merge($ordered, array_diff($known, $ordered)));
    }

    /**
     * @return list<string>
     */
    public function getVisibleFieldKeysProperty(): array
    {
        $query = mb_strtolower(trim($this->fieldSearch));

        if ($query === '') {
            return $this->orderedFieldKeys;
        }

        return array_values(array_filter(
            $this->orderedFieldKeys,
            function ($key) use ($query) {
                $field = $this->fieldConfig[$key] ?? [];

                return str_contains(mb_strtolower($key), $query)
                    || str_contains(mb_strtolower((string) ($field['label'] ?? $key)), $query)
                    || str_contains(mb_strtolower((string) ($field['type'] ?? '')), $query);
            }
        ));
    }

    public function moveField(string $key, int $delta): void
    {
        $keys = $this->orderedFieldKeys;
        $index = array_search($key, $keys, true);

        if ($index === false) {
            return;
        }

        $target = $index + $delta;

        if ($target < 0 || $target >= count($keys)) {
            return;
        }

        [$keys[$index], $keys[$target]] = [$keys[$target], $keys[$index]];

        $this->fieldOrder = $keys;
    }

    public function toggleAllFields(string $flag, bool $value): void
    {
        if (! in_array($flag, ['browse', 'read', 'edit', 'add'], true)) {
            return;
        }

        foreach ($this->fieldConfig as $key => $field) {
            $this->fieldConfig[$key][$flag] = $value;
        }
    }

    public function setFieldType(string $key, string $type): void
    {
        if (! app(FormfieldManager::class)->has($type) || ! isset($this->fieldConfig[$key])) {
            return;
        }

        $this->fieldConfig[$key]['type'] = $type;
    }

    /**
     * Coerce every staged field type back onto a registered field type.
     *
     * The type <select> only ever offers registered values, but it binds
     * straight to fieldConfig.<key>.type, so a hand-crafted Livewire payload
     * can write any string there. JsonBreadSource rejects an unknown type by
     * throwing, which would surface to the user as a 500 on save, so fall
     * back to the type the reflector detected for that column instead.
     */
    protected function normalizeFieldTypes(): void
    {
        $formfields = app(FormfieldManager::class);

        foreach ($this->fieldConfig as $key => $field) {
            if (! is_array($field)) {
                continue;
            }

            $detected = $this->detectedFields[$key]['type'] ?? null;
            $fallback = is_string($detected) && $formfields->has($detected) ? $detected : null;

            // Run the staged value through normalize() first so a legacy or
            // semantic name (image, email, simple_array) still maps onto a
            // registered type instead of being discarded in favour of the
            // detected one.
            $current = $field['type'] ?? null;
            $normalized = is_string($current) && $formfields->has($formfields->normalize($current))
                ? $formfields->normalize($current)
                : null;

            $this->fieldConfig[$key]['type'] = $normalized ?? $fallback ?? 'text';
        }
    }

    // ---------------------------------------------------------------------
    // Read layout
    // ---------------------------------------------------------------------

    public function toggleReadField(string $key): void
    {
        if (! isset($this->fieldConfig[$key])) {
            return;
        }

        $layout = array_values(array_filter(
            $this->readLayout,
            fn ($name) => $name !== $key
        ));

        if (in_array($key, $this->readLayout, true)) {
            $this->readLayout = $layout;

            return;
        }

        $layout[] = $key;
        $this->readLayout = $layout;
    }

    public function moveReadField(string $key, int $delta): void
    {
        $layout = array_values($this->readLayout);
        $index = array_search($key, $layout, true);

        if ($index === false) {
            return;
        }

        $target = $index + $delta;

        if ($target < 0 || $target >= count($layout)) {
            return;
        }

        [$layout[$index], $layout[$target]] = [$layout[$target], $layout[$index]];

        $this->readLayout = $layout;
    }

    // ---------------------------------------------------------------------
    // Icon picker
    // ---------------------------------------------------------------------

    public function selectIcon(string $name): void
    {
        $this->icon = $name;
        $this->showIconPicker = false;
        $this->iconSearch = '';
    }

    /**
     * @return list<string>
     */
    public function getIconOptionsProperty(): array
    {
        return [
            'table-cells', 'document-text', 'database', 'folder', 'photo', 'puzzle-piece',
            'squares-2x2', 'key', 'tag', 'hashtag', 'book-open', 'user-group', 'bell',
            'calendar-days', 'clock', 'cog-6-tooth', 'bars-3', 'chevron-up-down',
            'paper-clip', 'link', 'adjustments-horizontal', 'text', 'toggle',
            'pencil-square', 'plus-circle', 'check-circle', 'x-circle', 'power',
            'check', 'x-mark', 'plus', 'sun', 'moon', 'lock-closed',
        ];
    }

    // ---------------------------------------------------------------------
    // Review step
    // ---------------------------------------------------------------------

    /**
     * Non-blocking advisories shown on the review step. Everything here is
     * derivable from the current state, so the user can judge the BREAD
     * before writing it.
     *
     * @return list<array{level: string, message: string}>
     */
    public function getReviewWarningsProperty(): array
    {
        $warnings = [];

        if ($this->slug === '') {
            $warnings[] = ['level' => 'error', 'message' => __('tardis::builder.warnings.slug_empty')];
        } elseif ($this->getSlugStatusProperty() === 'taken') {
            $warnings[] = ['level' => 'error', 'message' => __('tardis::builder.warnings.slug_taken', ['slug' => $this->slug])];
        }

        if ($this->fieldConfig === []) {
            $warnings[] = [
                'level' => 'error',
                'message' => __('tardis::builder.warnings.no_fields'),
            ];
        }

        if ($this->fieldConfig !== []) {
            $visibleBrowse = array_filter(
                $this->orderedFieldKeys,
                fn ($key) => $this->browseColumns[$key]['visible'] ?? ($this->fieldConfig[$key]['browse'] ?? true)
            );

            if ($visibleBrowse === []) {
                $warnings[] = ['level' => 'warning', 'message' => __('tardis::builder.warnings.no_browse_columns')];
            }

            $addable = array_filter($this->fieldConfig, fn ($field) => (bool) ($field['add'] ?? false));
            $editable = array_filter($this->fieldConfig, fn ($field) => (bool) ($field['edit'] ?? false));

            if ($addable === []) {
                $warnings[] = ['level' => 'warning', 'message' => __('tardis::builder.warnings.no_add_field')];
            }

            if ($editable === []) {
                $warnings[] = ['level' => 'warning', 'message' => __('tardis::builder.warnings.no_edit_field')];
            }

            if ($this->readLayout === []) {
                $warnings[] = ['level' => 'warning', 'message' => __('tardis::builder.warnings.empty_read_layout')];
            }

            if ($this->searchKey !== '' && ! ($this->browseColumns[$this->searchKey]['searchable'] ?? false)) {
                $warnings[] = [
                    'level' => 'warning',
                    'message' => __('tardis::builder.warnings.search_key_not_searchable', ['key' => $this->searchKey]),
                ];
            }
        }

        foreach ($this->editTabs as $index => $tab) {
            if (($tab['fields'] ?? []) === []) {
                $warnings[] = [
                    'level' => 'info',
                    'message' => __('tardis::builder.warnings.tab_without_fields', ['tab' => $tab['name'] ?? __('tardis::builder.tab_n', ['number' => $index + 1])]),
                ];
            }
        }

        if ($this->modelHasSoftDeletes && ! $this->softDelete) {
            $warnings[] = [
                'level' => 'info',
                'message' => __('tardis::builder.warnings.soft_delete_disabled'),
            ];
        }

        return $warnings;
    }

    /**
     * @return array<string, mixed>
     */
    public function getReviewSummaryProperty(): array
    {
        $browse = array_filter(
            $this->orderedFieldKeys,
            fn ($key) => $this->browseColumns[$key]['visible'] ?? ($this->fieldConfig[$key]['browse'] ?? true)
        );

        return [
            'slug' => $this->slug,
            'model' => $this->model,
            'table' => $this->modelTable,
            'name' => $this->name,
            'name_plural' => $this->namePlural,
            'icon' => $this->icon,
            'total_fields' => count($this->fieldConfig),
            'browse_fields' => array_values($browse),
            'read_fields' => array_values($this->readLayout),
            'add_fields' => count(array_filter($this->fieldConfig, fn ($f) => (bool) ($f['add'] ?? false))),
            'edit_fields' => count(array_filter($this->fieldConfig, fn ($f) => (bool) ($f['edit'] ?? false))),
            'relationships' => count($this->relationshipConfig),
            'search_key' => $this->searchKey,
            'order_column' => $this->orderColumn,
            'order_direction' => $this->orderDirection,
            'soft_delete' => $this->softDelete,
        ];
    }

    // ---------------------------------------------------------------------
    // Internals
    // ---------------------------------------------------------------------

    protected function captureModelAnalysis(): void
    {
        if ($this->model === '' || ! class_exists($this->model)) {
            return;
        }

        $analysis = ModelReflector::analyze($this->model);

        $this->modelTable = (string) ($analysis['table'] ?? '');
        $this->modelHasSoftDeletes = (bool) ($analysis['softDelete'] ?? false);
        $this->modelHasTimestamps = (bool) ($analysis['timestamps'] ?? true);
    }

    /**
     * Fill in any layout entries the user has not visited yet, so a BREAD
     * saved straight from step 1 still carries a complete layout.
     */
    protected function hydrateLayoutDefaults(): void
    {
        $searchable = $this->searchKey !== '' ? [$this->searchKey => true] : [];

        foreach ($this->fieldConfig as $key => $field) {
            $this->browseColumns[$key] ??= [
                'visible' => (bool) ($field['browse'] ?? true),
                'sortable' => false,
                'searchable' => $searchable[$key] ?? false,
            ];
        }

        if ($this->readLayout === []) {
            $this->readLayout = array_values(array_filter(
                array_keys($this->fieldConfig),
                fn ($key) => (bool) ($this->fieldConfig[$key]['read'] ?? true)
            ));
        }

        if ($this->fieldOrder === []) {
            $this->fieldOrder = array_keys($this->fieldConfig);
        }
    }
};
