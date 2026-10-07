<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Tardis\Auth\Abilities;
use Tardis\Auth\BreadAuthorization;
use Tardis\Dashboard\DashboardLayout;
use Tardis\Manager\PluginManager;
use Tardis\Manager\WidgetManager;

new #[Title('tardis::dashboard.dashboard')] #[Layout('tardis::layouts.admin')] class extends Component
{
    public bool $editing = false;

    public function toggleEditing(): void
    {
        $this->authorizeLayout();
        $this->editing = ! $this->editing;
    }

    public function canCustomize(): bool
    {
        return app(BreadAuthorization::class)->allowsAbility(Abilities::DASHBOARD);
    }

    /**
     * @return \Illuminate\Support\Collection<int, \Tardis\Classes\Widget>
     */
    public function widgets(bool $withHidden = false)
    {
        $manager = new WidgetManager;
        $manager->collectFromPlugins(app(PluginManager::class));

        return $manager->all($withHidden);
    }

    public function toggleWidget(string $id): void
    {
        $this->authorizeLayout();

        $hidden = app(DashboardLayout::class)->all()[$id]['hidden'] ?? false;

        app(DashboardLayout::class)->change($id, ['hidden' => ! $hidden]);
    }

    public function resize(string $id, int $width): void
    {
        $this->authorizeLayout();

        app(DashboardLayout::class)->change($id, ['width' => $width]);
    }

    public function move(string $id, int $direction): void
    {
        $this->authorizeLayout();

        $ids = $this->widgets(withHidden: true)->map->id()->all();
        $from = array_search($id, $ids, true);
        $to = $from === false ? false : $from + ($direction < 0 ? -1 : 1);

        if ($from === false || $to < 0 || $to >= count($ids)) {
            return;
        }

        [$ids[$from], $ids[$to]] = [$ids[$to], $ids[$from]];

        foreach ($ids as $position => $widgetId) {
            app(DashboardLayout::class)->change($widgetId, ['order' => ($position + 1) * 10]);
        }
    }

    public function resetLayout(): void
    {
        $this->authorizeLayout();

        app(DashboardLayout::class)->reset();
    }

    protected function authorizeLayout(): void
    {
        app(BreadAuthorization::class)->authorizeAbility(Abilities::DASHBOARD);
    }
}; ?>

<div>
    <x-tardis::page-header :title="__('tardis::dashboard.dashboard')" :description="__('tardis::dashboard.welcome_to_tardis_admin')">
        @if ($this->canCustomize())
            <x-slot:action>
                <div class="flex gap-2">
                    @if ($editing)
                        <button type="button" wire:click="resetLayout" wire:confirm="{{ __('tardis::dashboard.confirm_reset') }}" class="btn btn-ghost">{{ __('tardis::dashboard.reset_layout') }}</button>
                    @endif
                    <button type="button" wire:click="toggleEditing" class="btn {{ $editing ? 'btn-primary' : 'btn-ghost' }}">
                        {{ $editing ? __('tardis::dashboard.done') : __('tardis::dashboard.customize') }}
                    </button>
                </div>
            </x-slot:action>
        @endif
    </x-tardis::page-header>

    <div class="grid grid-cols-12 gap-4">
        @foreach ($this->widgets(withHidden: $editing) as $widget)
            <div wire:key="widget-{{ $widget->id() }}" class="tardis-widget {{ $widget->layoutHidden ? 'opacity-40' : '' }}" style="--span: {{ $widget->width }}">
                <x-tardis::card class="h-full">
                    @if ($editing)
                        <div class="mb-3 flex flex-wrap items-center gap-1 border-b border-base-300 pb-2">
                            <span class="mr-auto text-xs font-semibold uppercase tracking-wide text-base-content/60">{{ $widget->title }}</span>
                            <button type="button" wire:click="move(@js($widget->id()), -1)" class="btn btn-ghost btn-xs" aria-label="{{ __('tardis::menu_builder.move_up') }}">◀</button>
                            <button type="button" wire:click="move(@js($widget->id()), 1)" class="btn btn-ghost btn-xs" aria-label="{{ __('tardis::menu_builder.move_down') }}">▶</button>
                            <select wire:change="resize(@js($widget->id()), $event.target.value)" class="select select-xs w-auto" aria-label="{{ __('tardis::dashboard.width') }}">
                                @foreach (\Tardis\Dashboard\DashboardLayout::WIDTHS as $option)
                                    <option value="{{ $option }}" @selected($widget->width === $option)>{{ $option }}/12</option>
                                @endforeach
                            </select>
                            <button type="button" wire:click="toggleWidget(@js($widget->id()))" class="btn btn-ghost btn-xs">{{ $widget->layoutHidden ? __('tardis::menu_builder.show') : __('tardis::menu_builder.hide') }}</button>
                        </div>
                    @endif

                    @include($widget->component, $widget->parameters)
                </x-tardis::card>
            </div>
        @endforeach
    </div>
</div>
