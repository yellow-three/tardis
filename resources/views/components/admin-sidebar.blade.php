@php
    $menuManager = app(\Tardis\Core\Manager\MenuManager::class);
    $pluginManager = app(\Tardis\Core\Manager\PluginManager::class);
    $menuManager->collectFromPlugins($pluginManager);

    $breadManager = app(\Tardis\Core\Bread\BreadManager::class);
    $breads = $breadManager->all();

    if ($breads->isNotEmpty()) {
        foreach ($breads as $slug => $bread) {
            $menuManager->addItems(
                (new \Tardis\Core\Classes\MenuItem(
                    $bread['display_name_plural'] ?? $slug,
                    'heroicon-o-table-cells',
                ))->route('tardis.bread.index', ['slug' => $slug])->group('BREAD')->order(100),
            );
        }
    }

    $groups = $menuManager->groups();
@endphp

<div class="drawer-side z-40">
    <label for="tardis-drawer" aria-label="close sidebar" class="drawer-overlay"></label>

    <aside class="bg-base-100 min-h-full w-64 border-r border-base-300">
        <div class="p-4 border-b border-base-300">
            <a href="{{ route('tardis.dashboard') }}" class="flex items-center gap-2">
                <div class="w-8 h-8 bg-primary rounded-lg flex items-center justify-center">
                    <span class="text-primary-content font-bold">T</span>
                </div>
                <span class="text-lg font-bold">TARDIS</span>
            </a>
        </div>

        <ul class="menu p-4">
            @foreach ($groups as $groupName => $items)
                @if ($groupName !== '')
                    <li class="menu-title mt-4">{{ $groupName }}</li>
                @endif
                @foreach ($items as $item)
                    <li>
                        <a href="{{ $item->href() }}" class="{{ $item->isActive() ? 'active' : '' }}">
                            @if ($item->icon)
                                <x-dynamic-component :component="$item->icon" class="w-5 h-5" />
                            @endif
                            {{ $item->title }}
                            @if ($item->badgeColor)
                                <span class="badge badge-{{ $item->badgeColor }} badge-sm ml-auto">
                                    {{ $item->badgeValue ?? '' }}
                                </span>
                            @endif
                        </a>
                        @if (!empty($item->children))
                            <ul>
                                @foreach ($item->children as $child)
                                    @if ($child->isVisible())
                                        <li>
                                            <a href="{{ $child->href() }}" class="{{ $child->isActive() ? 'active' : '' }}">
                                                @if ($child->icon)
                                                    <x-dynamic-component :component="$child->icon" class="w-4 h-4" />
                                                @endif
                                                {{ $child->title }}
                                            </a>
                                        </li>
                                    @endif
                                @endforeach
                            </ul>
                        @endif
                    </li>
                @endforeach
            @endforeach
        </ul>

        <div class="border-t border-base-300 p-3">
            <button @click="$store.theme.toggle()" class="btn btn-ghost btn-sm w-full justify-start gap-2">
                <template x-if="$store.theme.mode === 'dark' || ($store.theme.mode === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches)">
                    <x-heroicon-o-sun class="w-4 h-4" />
                </template>
                <template x-if="$store.theme.mode === 'light' || ($store.theme.mode === 'system' && !window.matchMedia('(prefers-color-scheme: dark)').matches)">
                    <x-heroicon-o-moon class="w-4 h-4" />
                </template>
                <span x-text="$store.theme.mode === 'dark' ? 'Dark mode' : ($store.theme.mode === 'light' ? 'Light mode' : 'System mode')"></span>
            </button>
        </div>
    </aside>
</div>
