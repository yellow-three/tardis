<?php

declare(strict_types=1);

namespace Tardis\Bread;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;
use Tardis\Auth\BreadAuthorization;

/**
 * Shared read-side gate for related records.
 *
 * A relation label (in a listing cell or an export) is data from another BREAD:
 * it must obey that BREAD's scope, owner restriction and browse ability, just
 * like the picker's options do. Otherwise a hidden related row leaks through a
 * parent that is itself visible.
 */
final class RelatedRecords
{
    /**
     * The query the user may draw related records from.
     *
     * A model without a BREAD has no definition to defer to and is listed in
     * full; null means nothing may be shown.
     */
    public static function scopedQuery(?string $model): ?Builder
    {
        if (! BreadDefinition::isModelClass($model)) {
            return null;
        }

        $bread = self::breadFor($model);

        if ($bread === null) {
            return $model::query();
        }

        if (! app(BreadAuthorization::class)->allows('browse', $bread->permissionKey())) {
            return null;
        }

        return $bread->query();
    }

    /**
     * The related records of $row this user is allowed to see.
     *
     * Keys come from the parent's relation so only records actually attached to
     * $row are considered; the values are then read through the related BREAD's
     * restricted query rather than the raw relation.
     *
     * @return Collection<int, Model>
     */
    public static function visible(Model $row, string $relation, ?string $model = null): Collection
    {
        if ($relation === '' || ! method_exists($row, $relation)) {
            return collect();
        }

        $relationQuery = $row->{$relation}();

        if (! $relationQuery instanceof Relation) {
            return collect();
        }

        $related = $relationQuery->getRelated();
        $model = $model !== null && $model !== '' ? $model : get_class($related);

        // Without a BREAD for the related model there is nothing to defer to:
        // the eager-loaded relation is used as before, with no extra query.
        if (self::breadFor($model) === null) {
            return self::loaded($row, $relation);
        }

        $key = $related->getKeyName();
        $keys = self::loaded($row, $relation)->pluck($key)->filter(fn ($id) => $id !== null)->all();

        if ($keys === []) {
            return collect();
        }

        $query = self::scopedQuery($model);

        return $query === null ? collect() : $query->whereIn($related->qualifyColumn($key), $keys)->get();
    }

    private static function breadFor(string $model): ?BreadDefinition
    {
        return app(BreadManager::class)
            ->all()
            ->first(fn (BreadDefinition $definition) => ltrim($definition->model, '\\') === ltrim($model, '\\'));
    }

    /**
     * The relation as a collection (eager-loaded when the list asked for it).
     * A to-one relation is a single model or null.
     *
     * @return Collection<int, Model>
     */
    private static function loaded(Model $row, string $relation): Collection
    {
        $value = $row->{$relation};

        if ($value instanceof Collection) {
            return $value;
        }

        return collect($value instanceof Model ? [$value] : []);
    }
}
