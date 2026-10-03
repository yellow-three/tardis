<?php

declare(strict_types=1);

namespace Tardis\Manager;

use Closure;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use Tardis\Bread\Action;
use Tardis\Bread\Actions\DeleteAction;
use Tardis\Bread\Actions\ForceDeleteAction;
use Tardis\Bread\Actions\RestoreAction;

/**
 * The actions offered on BREAD listings. A host or plugin adds, replaces or
 * reshapes them, for every BREAD (`*`) or for one slug.
 */
class ActionManager
{
    public const ALL = '*';

    /** @var array<string, array<int, Action>> */
    protected array $added = [];

    /** @var array<string, array<int, Closure>> */
    protected array $manipulators = [];

    public function __construct()
    {
        $this->add(self::ALL, new DeleteAction);
        $this->add(self::ALL, new RestoreAction);
        $this->add(self::ALL, new ForceDeleteAction);
    }

    public function add(string $slug, Action|string $action): void
    {
        $this->added[$slug][] = $this->resolve($action);
    }

    /** Swap the action registered under $name for another one. */
    public function replace(string $slug, string $name, Action|string $action): void
    {
        $this->manipulate($slug, function (Collection $actions) use ($name, $action) {
            return $actions->map(fn (Action $existing) => $existing->name() === $name ? $this->resolve($action) : $existing);
        });
    }

    /**
     * @param  Closure(Collection<int, Action>): Collection<int, Action>  $callback
     */
    public function manipulate(string $slug, Closure $callback): void
    {
        $this->manipulators[$slug][] = $callback;
    }

    /**
     * The actions for one BREAD, keyed by name: the global ones first, then
     * those of the slug, then every manipulator. A later registration with the
     * same name wins.
     *
     * @return Collection<string, Action>
     */
    public function for(string $slug): Collection
    {
        $actions = collect([...($this->added[self::ALL] ?? []), ...($this->added[$slug] ?? [])]);

        foreach ([...($this->manipulators[self::ALL] ?? []), ...($this->manipulators[$slug] ?? [])] as $callback) {
            $actions = collect($callback($actions));
        }

        return $actions->keyBy(fn (Action $action) => $action->name());
    }

    public function find(string $slug, string $name): ?Action
    {
        return $this->for($slug)->get($name);
    }

    protected function resolve(Action|string $action): Action
    {
        if ($action instanceof Action) {
            return $action;
        }

        if (! is_subclass_of($action, Action::class)) {
            throw new InvalidArgumentException("[{$action}] is not a ".Action::class.'.');
        }

        return app($action);
    }
}
