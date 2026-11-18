<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Tardis\Auth\Abilities;
use Tardis\Auth\BreadAuthorization;
use Tardis\Menu\SiteMenuStore;

new #[Title('tardis::shell.titles.site_menus')] #[Layout('tardis::layouts.admin')] class extends Component
{
    /** The menu being edited. */
    public string $menu = '';

    public string $newMenu = '';

    public string $itemTitle = '';

    public string $itemUrl = '';

    public bool $itemNewTab = false;

    public string $itemParent = '';

    /** Runs on every request: the ability can be revoked while the page is open. */
    public function boot(): void
    {
        app(BreadAuthorization::class)->authorizeAbility(Abilities::MENUS);
    }

    public function mount(): void
    {
        $this->menu = $this->store()->names()[0] ?? '';
    }

    /** @return list<string> */
    public function names(): array
    {
        return $this->store()->names();
    }

    /**
     * The items of the open menu, flattened in display order with their depth,
     * for the editor table.
     *
     * @return list<array{id: string, title: string, url: string, new_tab: bool, depth: int, first: bool, last: bool}>
     */
    public function rows(): array
    {
        return $this->flatten($this->menu !== '' ? $this->store()->items($this->menu) : []);
    }

    public function select(string $name): void
    {
        if ($this->store()->has($name)) {
            $this->menu = $name;
            $this->resetErrorBag();
        }
    }

    public function createMenu(): void
    {
        $this->resetErrorBag();
        $name = trim($this->newMenu);

        if (! SiteMenuStore::isValidName($name)) {
            $this->addError('newMenu', __('tardis::site_menus.invalid_name'));

            return;
        }

        $this->store()->create($name);
        $this->menu = $name;
        $this->reset('newMenu');
    }

    public function deleteMenu(): void
    {
        if ($this->menu === '') {
            return;
        }

        $this->store()->delete($this->menu);
        $this->menu = $this->store()->names()[0] ?? '';
    }

    public function addItem(): void
    {
        $this->resetErrorBag();

        try {
            $this->store()->add($this->menu, [
                'title' => $this->itemTitle,
                'url' => $this->itemUrl,
                'new_tab' => $this->itemNewTab,
            ], $this->itemParent !== '' ? $this->itemParent : null);
        } catch (InvalidArgumentException) {
            $this->addError('itemUrl', __('tardis::site_menus.invalid_item'));

            return;
        }

        $this->reset('itemTitle', 'itemUrl', 'itemNewTab', 'itemParent');
    }

    public function updateItem(string $id, string $field, mixed $value): void
    {
        if (! in_array($field, ['title', 'url', 'new_tab'], true)) {
            return;
        }

        $this->resetErrorBag();

        try {
            $this->store()->update($this->menu, $id, [$field => $value]);
        } catch (InvalidArgumentException) {
            $this->addError('items', __('tardis::site_menus.invalid_item'));
        }
    }

    public function removeItem(string $id): void
    {
        $this->store()->remove($this->menu, $id);
    }

    public function moveItem(string $id, int $direction): void
    {
        $this->store()->move($this->menu, $id, $direction);
    }

    public function indentItem(string $id): void
    {
        $this->store()->indent($this->menu, $id);
    }

    public function outdentItem(string $id): void
    {
        $this->store()->outdent($this->menu, $id);
    }

    protected function store(): SiteMenuStore
    {
        return app(SiteMenuStore::class);
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    protected function flatten(array $items, int $depth = 1): array
    {
        $rows = [];
        $last = count($items) - 1;

        foreach ($items as $index => $item) {
            $rows[] = [
                'id' => (string) $item['id'],
                'title' => (string) $item['title'],
                'url' => (string) $item['url'],
                'new_tab' => (bool) ($item['new_tab'] ?? false),
                'depth' => $depth,
                'first' => $index === 0,
                'last' => $index === $last,
            ];

            array_push($rows, ...$this->flatten($item['children'] ?? [], $depth + 1));
        }

        return $rows;
    }
};
