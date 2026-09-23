@php
    $menuManager = app(\Tardis\Manager\MenuManager::class);
    $pluginManager = app(\Tardis\Manager\PluginManager::class);
    $menuManager->collectFromPlugins($pluginManager);
    $items = $menuManager->tree();
@endphp

<div class="drawer-side z-40 is-drawer-close:overflow-visible">
    <label for="tardis-drawer" aria-label="close sidebar" class="drawer-overlay"></label>

    <aside class="bg-base-100 min-h-full flex flex-col border-r border-base-300 transition-[width] duration-200 is-drawer-close:w-16 is-drawer-open:w-72">
        <div class="px-3 h-16 flex items-center gap-3 border-b border-base-300 is-drawer-close:justify-center">
            <div class="bg-primary text-primary-content rounded-lg w-10 h-10 flex items-center justify-center shrink-0">
                <span class="text-primary-content font-bold text-xl">T</span>
            </div>
            <div class="tardis-sidebar-label">
                <p class="font-bold text-base leading-tight">TARDIS</p>
                <p class="text-xs text-base-content/60">Yönetim Paneli</p>
            </div>
        </div>

        <div class="flex-1 overflow-y-auto px-2 py-4 is-drawer-close:overflow-visible">
            @php
                $sections = $items->groupBy(fn ($item) => $item->section ?? 'General');
            @endphp

            @foreach ($sections as $sectionName => $sectionItems)
                @if ($sectionName !== 'General')
                    <div class="tardis-sidebar-label mb-3 px-2 pt-2">
                        <p class="text-[10px] font-semibold uppercase tracking-[0.18em] text-base-content/40">{{ $sectionName }}</p>
                    </div>
                @endif

                <ul class="menu menu-md w-full gap-1 mb-2">
                    @foreach ($sectionItems as $item)
                        @include('tardis::partials.menu-item', ['item' => $item, 'level' => 0])
                    @endforeach
                </ul>
            @endforeach
        </div>

        @php
            $user = auth()->user();
            $userName = $user?->name ?: $user?->email ?: 'Admin';
            $userInitial = mb_strtoupper(mb_substr($userName, 0, 1));
        @endphp

        <div class="border-t border-base-300 p-2">
            <div
               data-tip="{{ $userName }}"
               class="flex items-center gap-3 p-2 rounded-lg is-drawer-close:tooltip is-drawer-close:tooltip-right is-drawer-close:justify-center">
                <div class="avatar avatar-placeholder">
                    <div class="bg-neutral text-neutral-content rounded-full w-10">
                        <span>{{ $userInitial }}</span>
                    </div>
                </div>
                <div class="flex-1 min-w-0 tardis-sidebar-label">
                    <p class="font-medium text-sm truncate">{{ $userName }}</p>
                    <p class="text-xs text-base-content/60 truncate">{{ $user?->email }}</p>
                </div>
            </div>
        </div>
    </aside>
</div>
