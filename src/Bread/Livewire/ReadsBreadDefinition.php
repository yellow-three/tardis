<?php

declare(strict_types=1);

namespace Tardis\Bread\Livewire;

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
        return $this->definition()->toDisplayArray();
    }

    /** The definition behind $slug; a missing one is a 404. */
    protected function definition(): BreadDefinition
    {
        $definition = app(BreadManager::class)->find($this->slug);

        abort_if($definition === null, 404);

        return $definition;
    }
}
