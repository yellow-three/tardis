<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Tardis\Auth\Abilities;
use Tardis\Auth\BreadAuthorization;
use Tardis\Manager\MenuManager;
use Tardis\Manager\PluginManager;
use Tardis\Menu\MenuOverlay;

new #[Title('tardis::menu_builder.title')] #[Layout('tardis::layouts.admin')] class extends Component
{
    public string $linkTitle = '';

    public string $linkUrl = '';

    public string $linkIcon = '';

    public string $linkSection = '';

    public string $linkPermission = '';

    public bool $linkNewTab = false;

    /** Runs on every request: the ability can be revoked while the page is open. */
    public function boot(): void
    {
        app(BreadAuthorization::class)->authorizeAbility(Abilities::MENUS);
    }

    /**
     * Every item the user may see, including those the overlay hides, in order.
     *
     * "title" is what the row shows for the active locale; "rawTitle" is the
     * title as stored, so rename() can write a locale back into a map instead
     * of flattening it.
     *
     * @return array<int, array{id: string, title: string, rawTitle: string|array, section: ?string, hidden: bool, custom: bool, icon: ?string}>
     */
    public function rows(): array
    {
        // The sidebar already collected the menu in this request; collect it afresh.
        app()->forgetInstance(MenuManager::class);

        $menu = app(MenuManager::class);
        $menu->collectFromPlugins(app(PluginManager::class));

        return $menu->all(withHidden: true)
            ->reject(fn ($item) => $item->isDivider)
            ->map(fn ($item) => [
                'id' => $item->id(),
                'title' => $item->resolvedTitle(),
                'rawTitle' => $item->title,
                'section' => $item->section,
                'hidden' => $item->overlayHidden,
                'custom' => str_starts_with($item->id(), 'custom-'),
                'icon' => $item->icon,
            ])
            ->values()
            ->all();
    }

    public function toggle(string $id): void
    {
        $current = app(MenuOverlay::class)->items()[$id]['hidden'] ?? false;

        app(MenuOverlay::class)->change($id, ['hidden' => ! $current]);
    }

    /**
     * Retitle an item.
     *
     * A title stored as a locale map is written back as a map with only the
     * active locale replaced, so renaming in one language leaves the other
     * translations standing instead of collapsing them to that one string.
     */
    public function rename(string $id, string $title): void
    {
        $raw = collect($this->rows())->firstWhere('id', $id)['rawTitle'] ?? null;

        if (! is_array($raw)) {
            app(MenuOverlay::class)->change($id, ['title' => $title]);

            return;
        }

        $map = [];

        foreach ($raw as $locale => $translation) {
            if (is_scalar($translation)) {
                $map[(string) $locale] = (string) $translation;
            }
        }

        $map[app()->getLocale()] = $title;

        app(MenuOverlay::class)->change($id, ['title' => $map]);
    }

    public function resection(string $id, string $section): void
    {
        app(MenuOverlay::class)->change($id, ['section' => $section]);
    }

    public function move(string $id, int $direction): void
    {
        $ids = array_column($this->rows(), 'id');
        $from = array_search($id, $ids, true);
        $to = $from === false ? false : $from + ($direction < 0 ? -1 : 1);

        if ($from === false || $to < 0 || $to >= count($ids)) {
            return;
        }

        [$ids[$from], $ids[$to]] = [$ids[$to], $ids[$from]];

        // Spread the orders out so a later move has room between neighbours.
        foreach ($ids as $position => $itemId) {
            app(MenuOverlay::class)->change($itemId, ['order' => ($position + 1) * 10]);
        }
    }

    public function addLink(): void
    {
        $this->resetErrorBag();

        try {
            app(MenuOverlay::class)->addCustom([
                'title' => $this->linkTitle,
                'url' => $this->linkUrl,
                'icon' => $this->linkIcon,
                'section' => $this->linkSection,
                'permission' => $this->linkPermission,
                'new_tab' => $this->linkNewTab,
            ]);
        } catch (InvalidArgumentException) {
            $this->addError('linkUrl', __('tardis::menu_builder.invalid_link'));

            return;
        }

        $this->reset('linkTitle', 'linkUrl', 'linkIcon', 'linkSection', 'linkPermission', 'linkNewTab');
    }

    public function removeLink(string $id): void
    {
        app(MenuOverlay::class)->removeCustom($id);
    }

    public function resetMenu(): void
    {
        app(MenuOverlay::class)->reset();
    }
}; ?>

<div>
    <x-tardis::page-header :title="__('tardis::menu_builder.title')" :description="__('tardis::menu_builder.description')" />

    <x-tardis::card :title="__('tardis::menu_builder.items')" class="mb-6">
        <x-slot name="actions">
            <button type="button" wire:click="resetMenu" wire:confirm="{{ __('tardis::menu_builder.confirm_reset') }}" class="btn btn-ghost btn-sm">{{ __('tardis::menu_builder.reset') }}</button>
        </x-slot>

        <div class="overflow-x-auto">
            <table class="table">
                <thead>
                    <tr>
                        <th scope="col">{{ __('tardis::menu_builder.title_label') }}</th>
                        <th scope="col">{{ __('tardis::menu_builder.section') }}</th>
                        <th class="text-right" scope="col">{{ __('tardis::menu_builder.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($this->rows() as $row)
                        <tr wire:key="menu-{{ $row['id'] }}" class="{{ $row['hidden'] ? 'opacity-50' : '' }}">
                            <td>
                                <input type="text" value="{{ $row['title'] }}" wire:change="rename(@js($row['id']), $event.target.value)" class="input input-sm w-full max-w-xs" aria-label="{{ __('tardis::menu_builder.title_label') }}" />
                            </td>
                            <td>
                                <input type="text" value="{{ $row['section'] }}" wire:change="resection(@js($row['id']), $event.target.value)" class="input input-sm w-full max-w-xs" aria-label="{{ __('tardis::menu_builder.section') }}" />
                            </td>
                            <td class="text-right">
                                <div class="flex justify-end gap-1">
                                    <button type="button" wire:click="move(@js($row['id']), -1)" class="btn btn-ghost btn-xs" aria-label="{{ __('tardis::menu_builder.move_up') }}">▲</button>
                                    <button type="button" wire:click="move(@js($row['id']), 1)" class="btn btn-ghost btn-xs" aria-label="{{ __('tardis::menu_builder.move_down') }}">▼</button>
                                    <button type="button" wire:click="toggle(@js($row['id']))" class="btn btn-ghost btn-xs">{{ $row['hidden'] ? __('tardis::menu_builder.show') : __('tardis::menu_builder.hide') }}</button>
                                    @if ($row['custom'])
                                        <button type="button" wire:click="removeLink(@js($row['id']))" wire:confirm="{{ __('tardis::menu_builder.confirm_remove') }}" class="btn btn-ghost btn-xs text-error">{{ __('tardis::menu_builder.remove') }}</button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-tardis::card>

    <x-tardis::card :title="__('tardis::menu_builder.add_link')">
        <form wire:submit="addLink" class="grid gap-4 md:grid-cols-2">
            <label class="flex flex-col gap-1">
                <span class="font-medium">{{ __('tardis::menu_builder.title_label') }}</span>
                <input type="text" wire:model="linkTitle" class="input w-full" required />
            </label>
            <label class="flex flex-col gap-1">
                <span class="font-medium">{{ __('tardis::menu_builder.url') }}</span>
                <input type="text" wire:model="linkUrl" class="input w-full" placeholder="https://… or /docs" required />
                @error('linkUrl') <span class="text-error text-sm">{{ $message }}</span> @enderror
            </label>
            <label class="flex flex-col gap-1">
                <span class="font-medium">{{ __('tardis::menu_builder.icon') }}</span>
                <input type="text" wire:model="linkIcon" class="input w-full" placeholder="link" />
            </label>
            <label class="flex flex-col gap-1">
                <span class="font-medium">{{ __('tardis::menu_builder.section') }}</span>
                <input type="text" wire:model="linkSection" class="input w-full" />
            </label>
            <label class="flex flex-col gap-1">
                <span class="font-medium">{{ __('tardis::menu_builder.permission') }}</span>
                <input type="text" wire:model="linkPermission" class="input w-full" placeholder="browse posts" />
            </label>
            <label class="flex items-center gap-3 self-end">
                <input type="checkbox" wire:model="linkNewTab" class="checkbox" />
                <span>{{ __('tardis::menu_builder.new_tab') }}</span>
            </label>
            <div class="md:col-span-2">
                <button type="submit" class="btn btn-primary">{{ __('tardis::menu_builder.add_link') }}</button>
            </div>
        </form>
    </x-tardis::card>
</div>
