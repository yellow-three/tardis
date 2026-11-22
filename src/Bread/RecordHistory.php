<?php

declare(strict_types=1);

namespace Tardis\Bread;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tardis\Bread\Livewire\ReadsBreadDefinition;
use Tardis\Models\ActivityLog;
use Tardis\Support\ModelResolver;
use Tardis\Support\PanelUser;

/**
 * A record's revisions, read from the activity log the BREAD pages already
 * write (created, updated, deleted, restored), and the undo of one update.
 *
 * Only fields of the definition are shown or restored, by their labels;
 * values the log never held (passwords, hidden attributes) cannot come back.
 */
final class RecordHistory
{
    public function __construct(
        private readonly string $slug,
        private readonly BreadDefinition $definition,
    ) {}

    /**
     * The newest entries first, each with its changes as [label, old, new].
     *
     * @return Collection<int, array{id: int, action: string, user: string, at: ?string, changes: list<array{field: string, label: string, old: mixed, new: mixed}>, revertible: bool}>
     */
    /**
     * @param  list<string>  $only  field names the viewer may see; empty means every field
     */
    public function entries(Model $record, array $only = [], int $limit = 50): Collection
    {
        if (! Schema::hasTable(ActivityLog::TABLE)) {
            return collect();
        }

        $logs = ModelResolver::activityLog()::query()
            ->where('model_type', $record::class)
            ->where('model_id', (string) $record->getKey())
            ->latest('id')
            ->limit($limit)
            ->get();

        $users = $this->users($logs->pluck('user_id')->filter()->unique()->values()->all());
        $labels = $only === [] ? $this->labels() : array_intersect_key($this->labels(), array_flip($only));

        return $logs->map(function (ActivityLog $log) use ($users, $labels) {
            $old = (array) ($log->old_values ?? []);
            $new = (array) ($log->new_values ?? []);
            $changes = [];

            foreach (array_unique([...array_keys($new), ...array_keys($old)]) as $field) {
                if (! isset($labels[$field]) || ($old[$field] ?? null) === ($new[$field] ?? null)) {
                    continue;
                }

                $changes[] = ['field' => (string) $field, 'label' => $labels[$field], 'old' => $old[$field] ?? null, 'new' => $new[$field] ?? null];
            }

            return [
                'id' => (int) $log->getKey(),
                'action' => (string) $log->action,
                'user' => $log->user_id === null ? __('tardis::bread.history_system') : ($users[(string) $log->user_id] ?? '#'.$log->user_id),
                'at' => $log->created_at?->toDateTimeString(),
                'changes' => $changes,
                'revertible' => $log->action === 'updated' && $this->revertibleValues($old) !== [],
            ];
        });
    }

    /**
     * Put back the values an update replaced. Goes through the same saving as
     * the edit page, so the undo is itself an update in the log.
     *
     * @return array<string, mixed> the attributes that changed
     */
    public function revert(Model $record, int $entryId): array
    {
        $log = ModelResolver::activityLog()::query()
            ->whereKey($entryId)
            ->where('model_type', $record::class)
            ->where('model_id', (string) $record->getKey())
            ->where('action', 'updated')
            ->firstOrFail();

        $values = $this->revertibleValues((array) ($log->old_values ?? []));
        $fields = array_values(array_filter($this->fields(), fn (array $field) => array_key_exists((string) ($field['name'] ?? ''), $values)));

        return $values === [] ? [] : app(BreadSaver::class)->update($this->slug, $record, $fields, $values);
    }

    /**
     * Old values of plain editable fields: no relations, files or passwords,
     * whose logged value is not what the field would store.
     *
     * @param  array<string, mixed>  $old
     * @return array<string, mixed>
     */
    private function revertibleValues(array $old): array
    {
        $editable = [];

        foreach ($this->fields() as $field) {
            $type = (string) ($field['type'] ?? '');

            if (($field['edit'] ?? true) !== false && ! BreadQuery::showsRelation($field)
                && ! in_array($type, ['password', 'file', 'image', 'multiple_images', 'media_picker'], true)) {
                $editable[(string) $field['name']] = true;
            }
        }

        return array_intersect_key($old, $editable);
    }

    /**
     * The definition's fields this user may see: a field behind an "ability"
     * the user lacks is neither shown in the history nor restored.
     *
     * @return list<array<string, mixed>>
     */
    private function fields(): array
    {
        return ReadsBreadDefinition::withoutHiddenFields($this->definition->toDisplayArray())['fields'] ?? [];
    }

    /** @return array<string, string> field name => label */
    private function labels(): array
    {
        $labels = [];

        foreach ($this->fields() as $field) {
            $name = (string) ($field['name'] ?? '');

            if ($name !== '') {
                $label = $field['label'] ?? $field['display_name'] ?? null;
                $labels[$name] = is_string($label) && $label !== '' ? $label : Str::headline($name);
            }
        }

        return $labels;
    }

    /**
     * @param  list<int|string>  $ids
     * @return array<string, string> user id => name
     */
    private function users(array $ids): array
    {
        $class = config('auth.providers.users.model');

        if ($ids === [] || ! is_string($class) || ! is_subclass_of($class, Model::class)) {
            return [];
        }

        return $class::query()->whereKey($ids)->get()
            ->mapWithKeys(fn (Model $user) => [(string) $user->getKey() => PanelUser::name($user)])
            ->all();
    }
}
