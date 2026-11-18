<?php

use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Tardis\Auth\BreadAuthorization;
use Tardis\Bread\ActivityLogger;
use Tardis\Bread\BreadDefinition;
use Tardis\Bread\BreadManager;

/**
 * Put the records of a BREAD in order by hand. Positions are written to the
 * definition's order_column as 1, 2, 3, ... in the order shown; the listing
 * then sorts by that column.
 */
new #[Title('tardis::shell.titles.order')] #[Layout('tardis::layouts.admin')] class extends Component
{
    /** Records beyond this are not shown; a BREAD that large needs another tool. */
    public const LIMIT = 500;

    #[Locked]
    public string $slug = '';

    public function boot(): void
    {
        if ($this->slug !== '') {
            app(BreadAuthorization::class)->authorize('edit', $this->slug);
        }
    }

    public function mount(string $slug): void
    {
        $this->slug = $slug;
        $definition = $this->definition();

        abort_if($definition->orderColumn === null || $definition->orderColumn === '', 404);

        app(BreadAuthorization::class)->authorize('edit', $this->slug);
    }

    /**
     * @return list<array{id: int|string, label: string}>
     */
    public function rows(): array
    {
        $definition = $this->definition();
        $label = $definition->orderDisplayColumn ?: $definition->searchKey;

        return $this->query()
            ->limit(self::LIMIT)
            ->get()
            ->map(fn ($record) => [
                'id' => $record->getKey(),
                'label' => (string) (($label ? data_get($record, $label) : null) ?? '#'.$record->getKey()),
            ])
            ->all();
    }

    public function total(): int
    {
        return $this->definition()->query()->count();
    }

    /**
     * Save a new order. Only records this BREAD shows (its scope applies) are
     * written; any other id is ignored, and records left out keep their place
     * after the ones given.
     *
     * @param  array<int, int|string>  $ids
     */
    public function saveOrder(array $ids): void
    {
        $definition = $this->definition();
        $column = (string) $definition->orderColumn;
        $known = array_map('strval', $this->query()->limit(self::LIMIT)->get()->modelKeys());
        $ids = array_values(array_unique(array_filter(array_map('strval', $ids), fn (string $id) => in_array($id, $known, true))));
        $ordered = [...$ids, ...array_values(array_diff($known, $ids))];

        DB::transaction(function () use ($definition, $column, $ordered) {
            foreach ($ordered as $position => $id) {
                $definition->query()->whereKey($id)->update([$column => $position + 1]);
            }
        });

        app(ActivityLogger::class)->audit('tardis.bread.order', 'reordered', ['slug' => $this->slug, 'count' => count($ordered)]);
        session()->flash('message', __('tardis::bread.order_saved'));
    }

    public function move(int|string $id, int $direction): void
    {
        $ids = array_map(fn (array $row) => (string) $row['id'], $this->rows());
        $from = array_search((string) $id, $ids, true);
        $to = $from === false ? false : $from + ($direction < 0 ? -1 : 1);

        if ($from === false || $to < 0 || $to >= count($ids)) {
            return;
        }

        [$ids[$from], $ids[$to]] = [$ids[$to], $ids[$from]];
        $this->saveOrder($ids);
    }

    protected function query()
    {
        $definition = $this->definition();
        $query = $definition->query()->orderBy((string) $definition->orderColumn);

        return $query->orderBy($query->getModel()->getKeyName());
    }

    protected function definition(): BreadDefinition
    {
        $definition = app(BreadManager::class)->find($this->slug);

        abort_if($definition === null || ! BreadDefinition::isModelClass($definition->model), 404);

        return $definition;
    }
};
