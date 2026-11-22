<?php

use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Tardis\Auth\BreadAuthorization;
use Tardis\Bread\BreadDefinition;
use Tardis\Bread\Livewire\ReadsBreadDefinition;
use Tardis\Bread\RecordHistory;
use Tardis\Formfields\Formfield;
use Tardis\Formfields\FormfieldManager;

new #[Title('tardis::shell.titles.view')] #[Layout('tardis::layouts.admin')] class extends Component
{
    use ReadsBreadDefinition;

    #[Locked]
    public string $slug = '';

    #[Locked]
    public int|string $id = 0;

    #[Locked]
    public array $record = [];

    /**
     * Runs on every request, not only on mount: Livewire keeps component state
     * between requests, so a permission removed while the page is open has to
     * stop the next one. mount() still authorises the first render, where the
     * slug is not known yet.
     */
    public function boot(): void
    {
        if ($this->slug !== '') {
            app(BreadAuthorization::class)->authorize('read', $this->slug);
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

        app(BreadAuthorization::class)->authorize('read', $this->slug);

        $modelClass = $this->bread['model'] ?? null;

        if (! BreadDefinition::isModelClass($modelClass)) {
            abort(404);
        }

        $this->loadRecord();
    }

    protected function loadRecord(): void
    {
        $record = $this->definition()->query()->findOrFail($this->id);

        // Only the key and the columns this page shows reach the browser.
        $this->record = array_intersect_key(
            $record->toArray(),
            array_flip([$record->getKeyName(), ...array_filter(array_column($this->fields, 'name'))]),
        );
    }

    /**
     * The record's revisions, limited to the fields this page shows, so the
     * history cannot reveal a value the page itself hides.
     *
     * @return Collection<int, array<string, mixed>>
     */
    #[Computed]
    public function history()
    {
        $record = $this->definition()->query()->findOrFail($this->id);

        return (new RecordHistory($this->slug, $this->definition()))
            ->entries($record, array_values(array_filter(array_column($this->fields, 'name'))));
    }

    public function canRevert(): bool
    {
        return app(BreadAuthorization::class)->allows('edit', $this->slug);
    }

    /** Put back the values one update replaced; the undo is logged as an update. */
    public function revert(int $entryId): void
    {
        $record = $this->definition()->query()->findOrFail($this->id);
        app(BreadAuthorization::class)->authorize('edit', $this->slug, $record);

        $changes = (new RecordHistory($this->slug, $this->definition()))->revert($record, $entryId);

        unset($this->history);
        $this->loadRecord();
        session()->flash('message', $changes === [] ? __('tardis::bread.history_nothing_to_revert') : __('tardis::bread.history_reverted'));
    }

    #[Computed]
    public function layoutFields(): array
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

    #[Computed]
    public function fields(): array
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
    #[Computed]
    public function formfields(): array
    {
        return collect(app(FormfieldManager::class)->fields($this->fields))
            ->keyBy(fn (Formfield $field) => $field->name)
            ->all();
    }
};
