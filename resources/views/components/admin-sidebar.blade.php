@php
    $menuManager = app(\Tardis\Manager\MenuManager::class);
    $pluginManager = app(\Tardis\Manager\PluginManager::class);
    $menuManager->collectFromPlugins($pluginManager);
    $items = $menuManager->tree();

    // The default user menu points Profile at `profile.edit`, which no shipped
    // route defines. Rendering it would call route() and 500 the whole shell,
    // so entries without a registered route are dropped instead of linked.
    $userItems = $menuManager->userMenu()
        ->filter(fn ($item) => $item->routeName === null || \Illuminate\Support\Facades\Route::has($item->routeName))
        ->values();

    $user = auth()->user();
    $userName = $user?->name ?: $user?->email ?: 'Admin';
    $userInitial = mb_strtoupper(mb_substr($userName, 0, 1));
@endphp

<div class="drawer-side z-40 is-drawer-close:overflow-visible">
    <label for="tardis-drawer" aria-label="Sidebarı kapat" class="drawer-overlay"></label>

    <aside aria-label="Ana gezinme" class="flex min-h-full flex-col border-r border-base-300 bg-base-100 transition-[width] duration-200 is-drawer-close:w-18 is-drawer-open:w-68">
        {{-- Brand --}}
        <div class="flex h-16 shrink-0 items-center gap-3 border-b border-base-300 px-3 is-drawer-close:justify-center">
            <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-primary text-primary-content">
                <span class="text-lg font-bold leading-none">T</span>
            </div>
            <div class="tardis-sidebar-label min-w-0">
                <p class="text-sm font-bold leading-tight tracking-tight">TARDIS</p>
                <p class="truncate text-[11px] leading-tight text-base-content/60">Yönetim Paneli</p>
            </div>
        </div>

        {{-- Navigation --}}
        <div
            class="min-h-0 flex-1 overflow-y-auto px-2 py-3 is-drawer-close:overflow-visible"
            x-data="{
                menuQuery: '',
                menuFilter(el) {
                    const q = this.menuQuery.trim().toLowerCase()
                    if (! q) return true
                    if ((el.dataset.menuTitle || '').toLowerCase().includes(q)) return true
                    return Array.from(el.querySelectorAll('[data-menu-title]'))
                        .some(child => (child.dataset.menuTitle || '').toLowerCase().includes(q))
                },
                menuHasMatch() {
                    const q = this.menuQuery.trim().toLowerCase()
                    if (! q) return true
                    return Array.from(this.$el.querySelectorAll('li[data-menu-title]'))
                        .some(li => this.menuFilter(li))
                }
            }"
        >
            <label class="tardis-drawer-open-only mb-2 flex items-center gap-2 rounded-lg border border-base-300 px-2.5 py-1.5 focus-within:border-primary">
                <x-tardis::icon name="magnifying-glass" class="h-4 w-4 shrink-0 opacity-50" />
                <input
                    type="search"
                    x-model="menuQuery"
                    placeholder="Menüde ara"
                    aria-label="Menüde ara"
                    class="w-full bg-transparent text-[13px] outline-none placeholder:text-base-content/40"
                />
            </label>

            @php
                $sections = $items->groupBy(fn ($item) => $item->section ?? 'General');
            @endphp

            @foreach ($sections as $sectionName => $sectionItems)
                @if ($sectionName !== 'General')
                    <div
                        aria-hidden="true"
                        class="tardis-drawer-closed-only mx-2 my-2 h-px bg-base-300/70"
                        x-show="typeof menuQuery === 'string' && menuQuery.trim() === ''"
                    ></div>

                    <div class="tardis-sidebar-label mb-1 px-3 pt-2">
                        <p class="text-[11px] font-semibold uppercase tracking-widest text-base-content/45">{{ $sectionName }}</p>
                    </div>
                @endif

                <ul class="menu menu-md mb-1 w-full gap-0.5 p-0">
                    @foreach ($sectionItems as $item)
                        @include('tardis::partials.menu-item', ['item' => $item, 'level' => 0])
                    @endforeach
                </ul>
            @endforeach

            <p
                x-cloak
                x-show="! menuHasMatch()"
                class="tardis-sidebar-label px-3 py-4 text-center text-xs text-base-content/50"
            >
                Eşleşen menü öğesi yok.
            </p>
        </div>

        {{-- Account --}}
        <div class="shrink-0 border-t border-base-300 p-2">
            <details class="dropdown dropdown-top w-full">
                <summary
                    data-tip="{{ $userName }}"
                    aria-label="Hesap menüsü"
                    class="flex cursor-pointer list-none items-center gap-2.5 rounded-lg p-2 transition-colors hover:bg-base-200 is-drawer-close:justify-center is-drawer-close:tooltip is-drawer-close:tooltip-right focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary"
                >
                    <div class="avatar avatar-placeholder shrink-0">
                        <div class="w-8 rounded-full bg-neutral text-sm text-neutral-content">
                            <span class="font-medium">{{ $userInitial }}</span>
                        </div>
                    </div>
                    <div class="tardis-sidebar-label min-w-0 flex-1">
                        <p class="truncate text-[13px] font-medium leading-tight">{{ $userName }}</p>
                        <p class="truncate text-[11px] leading-tight text-base-content/60">{{ $user?->email }}</p>
                    </div>
                </summary>

                <ul class="menu dropdown-content z-50 mb-2 w-full gap-0.5 rounded-box border border-base-300 bg-base-100 p-1">
                    @foreach ($userItems as $item)
                        {{-- $divider means "draw a separator above this entry", not "this entry
                             IS a separator" like MenuItem::$isDivider does. Collapsing the two
                             into an elseif chain silently drops the entry itself. --}}
                        @if ($item->divider)
                            <li class="my-1 h-px bg-base-300/60" role="separator"></li>
                        @endif

                        @if ($item->method === 'POST')
                            {{-- toForm() emits a hidden CSRF form without a button, so the
                                 visible trigger submits that sibling form explicitly. --}}
                            <li>
                                {!! $item->toForm() !!}
                                <button
                                    type="button"
                                    @click="$el.closest('li').querySelector('form')?.requestSubmit()"
                                    class="flex w-full items-center gap-2.5 rounded-lg px-2.5 py-1.5 text-left text-[13px] transition-colors hover:bg-base-200"
                                >
                                    @if ($item->icon)
                                        <x-tardis::icon :name="$item->icon" class="h-4 w-4 shrink-0 opacity-70" />
                                    @endif
                                    <span class="truncate">{{ $item->title }}</span>
                                </button>
                            </li>
                        @else
                            <li>
                                <a
                                    href="{{ $item->href() }}"
                                    class="flex items-center gap-2.5 rounded-lg px-2.5 py-1.5 text-[13px] transition-colors hover:bg-base-200"
                                >
                                    @if ($item->icon)
                                        <x-tardis::icon :name="$item->icon" class="h-4 w-4 shrink-0 opacity-70" />
                                    @endif
                                    <span class="truncate">{{ $item->title }}</span>
                                </a>
                            </li>
                        @endif
                    @endforeach
                </ul>
            </details>
        </div>
    </aside>
</div>
