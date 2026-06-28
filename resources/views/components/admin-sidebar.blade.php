@php
    $menuManager = app(\Tardis\Manager\MenuManager::class);
    $pluginManager = app(\Tardis\Manager\PluginManager::class);
    $menuManager->collectFromPlugins($pluginManager);
    $items = $menuManager->tree();
@endphp

<div class="drawer-side z-40">
    <label for="tardis-drawer" aria-label="close sidebar" class="drawer-overlay"></label>

    <aside class="bg-base-100 min-h-full w-72 lg:is-drawer-open:w-16 border-r border-base-300 transition-all duration-300 flex flex-col overflow-x-hidden">
        <div class="flex-none p-4 border-b border-base-300">
            <a href="{{ route('tardis.dashboard') }}" class="flex items-center gap-3">
                <div class="w-8 h-8 bg-primary rounded-lg flex items-center justify-center flex-shrink-0">
                    <span class="text-primary-content font-bold">T</span>
                </div>
                <span class="text-lg font-bold lg:is-drawer-open:hidden">TARDIS</span>
            </a>
        </div>

        <ul class="menu menu-lg p-2 gap-1 grow">
            @foreach ($items as $item)
                @include('tardis::partials.menu-item', ['item' => $item, 'level' => 0])
            @endforeach
        </ul>

        <div class="flex-none border-t border-base-300 p-2 space-y-1">
            <button @click="$store.theme.toggle()" 
                    class="btn btn-ghost btn-sm w-full justify-start gap-2 lg:is-drawer-open:tooltip lg:is-drawer-open:tooltip-right" 
                    data-tip="Toggle theme">
                <template x-if="$store.theme.mode === 'dark' || ($store.theme.mode === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches)">
                    <x-tardis::icon name="sun" class="w-5 h-5 flex-shrink-0" />
                </template>
                <template x-if="$store.theme.mode === 'light' || ($store.theme.mode === 'system' && !window.matchMedia('(prefers-color-scheme: dark)').matches)">
                    <x-tardis::icon name="moon" class="w-5 h-5 flex-shrink-0" />
                </template>
                <span class="lg:is-drawer-open:hidden" x-text="$store.theme.mode === 'dark' ? 'Dark mode' : ($store.theme.mode === 'light' ? 'Light mode' : 'System mode')"></span>
            </button>

            <label for="tardis-drawer" 
                   class="btn btn-ghost btn-sm w-full justify-start gap-2 drawer-button lg:is-drawer-open:tooltip lg:is-drawer-open:tooltip-right" 
                   data-tip="Expand sidebar">
                <x-tardis::icon name="x-mark" class="w-5 h-5 flex-shrink-0 lg:is-drawer-open:hidden" />
                <x-tardis::icon name="bars-3" class="w-5 h-5 flex-shrink-0 hidden lg:is-drawer-open:inline" />
                <span class="lg:is-drawer-open:hidden">Collapse sidebar</span>
            </label>
        </div>
    </aside>
</div>
