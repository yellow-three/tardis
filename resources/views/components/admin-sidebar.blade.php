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
            <div class="is-drawer-close:hidden">
                <p class="font-bold text-base leading-tight">TARDIS</p>
                <p class="text-xs text-base-content/60">Yönetim Paneli</p>
            </div>
        </div>

        <ul class="menu menu-md w-full px-2 py-4 gap-1 flex-1 overflow-y-auto is-drawer-close:overflow-visible">
            @foreach ($items as $item)
                @include('tardis::partials.menu-item', ['item' => $item, 'level' => 0])
            @endforeach
        </ul>

        {{-- Alt kullanıcı kartı --}}
        <div class="border-t border-base-300 p-2">
            <a href="#"
               data-tip="Ahmet Yılmaz"
               class="flex items-center gap-3 p-2 rounded-lg hover:bg-base-200 transition-colors is-drawer-close:tooltip is-drawer-close:tooltip-right is-drawer-close:justify-center">
                <div class="avatar avatar-placeholder">
                    <div class="bg-neutral text-neutral-content rounded-full w-10">
                        <span>AY</span>
                    </div>
                </div>
                <div class="flex-1 min-w-0 is-drawer-close:hidden">
                    <p class="font-medium text-sm truncate">Ahmet Yılmaz</p>
                    <p class="text-xs text-base-content/60 truncate">admin@ornek.com</p>
                </div>
                <i class="icon-log-out text-base-content/60 is-drawer-close:hidden"></i>
            </a>
        </div>
    </aside>
</div>
