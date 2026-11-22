<?php

declare(strict_types=1);

namespace Tardis\Bread\Livewire;

use Tardis\Auth\BreadAuthorization;
use Tardis\Bread\BreadDefinition;
use Tardis\Bread\BreadManager;

/**
 * For the BREAD pages: the definition is read on the server from the slug on
 * every request instead of travelling in the component snapshot. The snapshot
 * stays small, nobody can tamper with a definition there, and a definition
 * changed in the builder applies to an open page on its next request.
 *
 * Reading is cheap: JsonBreadSource caches the definitions per request.
 */
trait ReadsBreadDefinition
{
    /**
     * The definition as the views use it (BreadDefinition::toDisplayArray()).
     * Each page exposes it through its own #[Computed] bread(), since Livewire
     * does not pick the attribute up from a trait.
     *
     * @return array<string, mixed>
     */
    protected function breadArray(): array
    {
        return self::withoutHiddenFields($this->definition()->toDisplayArray());
    }

    /**
     * Drop the fields whose "ability" the user lacks, from the field list and
     * from every layout that names them, so no page, export, import or history
     * built from this array can show or write them.
     *
     * @param  array<string, mixed>  $bread
     * @return array<string, mixed>
     */
    public static function withoutHiddenFields(array $bread): array
    {
        $auth = app(BreadAuthorization::class);
        $hidden = [];

        foreach ((array) ($bread['fields'] ?? []) as $field) {
            if (is_array($field) && ! $auth->allowsField($field)) {
                $hidden[(string) ($field['name'] ?? '')] = true;
            }
        }

        $visible = fn ($item) => is_array($item)
            ? ! isset($hidden[(string) ($item['name'] ?? '')]) && $auth->allowsField($item)
            : ! isset($hidden[(string) $item]);

        $bread['fields'] = array_values(array_filter((array) ($bread['fields'] ?? []), $visible));

        foreach ((array) ($bread['layout'] ?? []) as $key => $entries) {
            if (is_array($entries) && array_is_list($entries)) {
                $bread['layout'][$key] = array_values(array_filter($entries, $visible));
            }
        }

        return $bread;
    }

    /** The definition behind $slug; a missing one is a 404. */
    protected function definition(): BreadDefinition
    {
        $definition = app(BreadManager::class)->find($this->slug);

        abort_if($definition === null, 404);

        return $definition;
    }
}
