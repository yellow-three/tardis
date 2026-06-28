@php
    $menuManager = app(\Tardis\Manager\MenuManager::class);
    $pluginManager = app(\Tardis\Manager\PluginManager::class);
    $menuManager->collectFromPlugins($pluginManager);
    $items = $menuManager->tree();
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
            @foreach ($items as $item)
                @include('tardis::partials.menu-item', ['item' => $item, 'level' => 0])
            @endforeach
        </ul>

        <div class="border-t border-base-300 p-3">
            <button @click="$store.theme.toggle()" class="btn btn-ghost btn-sm w-full justify-start gap-2">
                <template x-if="$store.theme.mode === 'dark' || ($store.theme.mode === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches)">
                    <x-tardis::icon name="sun" class="w-4 h-4" />
                </template>
                <template x-if="$store.theme.mode === 'light' || ($store.theme.mode === 'system' && !window.matchMedia('(prefers-color-scheme: dark)').matches)">
                    <x-tardis::icon name="moon" class="w-4 h-4" />
                </template>
                <span x-text="$store.theme.mode === 'dark' ? 'Dark mode' : ($store.theme.mode === 'light' ? 'Light mode' : 'System mode')"></span>
            </button>
        </div>
    </aside>
</div>
