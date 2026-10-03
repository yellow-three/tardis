@props(['title' => 'Dashboard'])

@php
    $menuManager = app(\Tardis\Manager\MenuManager::class);
    $menuManager->collectFromPlugins(app(\Tardis\Manager\PluginManager::class));
    $userMenuItems = $menuManager->userMenu();
    $userName = auth()->user()?->name ?: auth()->user()?->email ?: 'Admin';
    $userInitial = mb_strtoupper(mb_substr($userName, 0, 1));
@endphp

<header class="navbar sticky top-0 z-30 min-h-16 gap-2 border-b border-base-300 bg-base-100/80 px-2 backdrop-blur-md lg:gap-4 lg:px-5">
    <div class="flex-none">
        <label for="tardis-drawer" aria-label="Menüyü aç/kapat" class="btn btn-square btn-ghost btn-sm focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary">
            <x-tardis::icon name="ui.menu" class="tardis-drawer-closed-only w-5 h-5" />
            <x-tardis::icon name="ui.close" class="tardis-drawer-open-only w-5 h-5" />
        </label>
    </div>

    <h1 class="min-w-0 flex-1 truncate px-1 text-lg font-semibold tracking-tight lg:text-xl">
        {{ $title }}
    </h1>

    <form method="GET" action="{{ route('tardis.search') }}" class="hidden w-full max-w-xs flex-none md:block xl:max-w-sm">
        <label class="input input-sm flex w-full items-center gap-2 bg-base-200/60 focus-within:outline-2 focus-within:outline-offset-0 focus-within:outline-primary">
            <x-tardis::icon name="action.search" class="w-4 h-4 opacity-50" aria-hidden="true" />
            <span class="sr-only">Search records</span>
            <input type="search" name="query" class="grow" placeholder="Ara…" autocomplete="off" />
        </label>
    </form>

    <div class="flex flex-none items-center gap-1">
        <button class="btn btn-ghost btn-circle btn-sm focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary" aria-label="Bildirimler">
            <x-tardis::icon name="ui.bell" class="w-5 h-5" />
        </button>

        <button class="btn btn-ghost btn-circle btn-sm focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary" @click="$store.theme.toggle()" aria-label="Tema değiştir">
            <template x-if="$store.theme.applied === $store.theme.dark">
                <x-tardis::icon name="ui.sun" class="w-5 h-5" />
            </template>
            <template x-if="$store.theme.applied !== $store.theme.dark">
                <x-tardis::icon name="ui.moon" class="w-5 h-5" />
            </template>
        </button>

        <div class="dropdown dropdown-end">
            <div tabindex="0" role="button" class="btn btn-circle btn-ghost btn-sm focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary">
                <span class="flex h-8 w-8 items-center justify-center rounded-full bg-primary text-sm font-semibold text-primary-content">
                    {{ $userInitial }}
                </span>
            </div>
            <ul tabindex="0" class="menu dropdown-content menu-sm mt-3 w-56 rounded-box border border-base-300 bg-base-100 p-2 shadow-lg">
                @forelse ($userMenuItems as $userItem)
                    @if ($userItem->divider)
                        <li class="divider"></li>
                    @endif
                    <li>
                        @if ($userItem->method && $userItem->method !== 'GET')
                            <form method="POST" action="{{ $userItem->href() }}">
                                @csrf
                                @method($userItem->method)
                                <button type="submit" class="w-full text-left">
                                    @if ($userItem->icon)
                                        <x-dynamic-component :component="$userItem->icon" class="w-4 h-4" />
                                    @endif
                                    {{ $userItem->title }}
                                </button>
                            </form>
                        @else
                            <a href="{{ $userItem->href() }}">
                                @if ($userItem->icon)
                                    <x-dynamic-component :component="$userItem->icon" class="w-4 h-4" />
                                @endif
                                {{ $userItem->title }}
                            </a>
                        @endif
                    </li>
                @empty
                    <li class="menu-title truncate">{{ $userName }}</li>
                    <li>
                        <form method="POST" action="{{ route('tardis.logout') }}">
                            @csrf
                            <button type="submit" class="w-full text-left">Logout</button>
                        </form>
                    </li>
                @endforelse
            </ul>
        </div>
    </div>
</header>
