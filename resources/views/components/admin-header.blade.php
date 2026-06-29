@props(['title' => 'Dashboard'])

@php
    $menuManager = app(\Tardis\Manager\MenuManager::class);
    $userMenuItems = $menuManager->userMenu();
@endphp

<header class="navbar bg-base-100 border-b border-base-300 sticky top-0 z-30">
    <div class="flex-none">
        <label for="tardis-drawer" class="btn btn-square btn-ghost" aria-label="Menüyü aç/kapat">
            <x-tardis::icon name="ui.menu" class="w-5 h-5 is-drawer-open:hidden" />
            <x-tardis::icon name="ui.close" class="w-5 h-5 is-drawer-close:hidden" />
        </label>
    </div>

    <div class="flex-1">
        <span class="text-xl font-bold px-4">{{ $title }}</span>
    </div>

    <div class="flex-none gap-1">
        <label class="input input-bordered input-sm flex items-center gap-2 max-w-xs w-full hidden md:flex">
            <x-tardis::icon name="action.search" class="w-4 h-4 opacity-50" />
            <input type="search" class="grow" placeholder="Ara..." />
        </label>

        <button class="btn btn-ghost btn-circle" aria-label="Bildirimler">
            <div class="indicator">
                <x-tardis::icon name="ui.bell" class="w-5 h-5" />
                <span class="badge badge-xs badge-primary indicator-item"></span>
            </div>
        </button>

        <button class="btn btn-ghost btn-circle" @click="$store.theme.toggle()" aria-label="Tema değiştir">
            <template x-if="$store.theme.applied === $store.theme.dark">
                <x-tardis::icon name="ui.sun" class="w-5 h-5" />
            </template>
            <template x-if="$store.theme.applied !== $store.theme.dark">
                <x-tardis::icon name="ui.moon" class="w-5 h-5" />
            </template>
        </button>

        <div class="dropdown dropdown-end">
            <div tabindex="0" role="button" class="btn btn-ghost btn-circle avatar">
                <div class="w-10 rounded-full bg-primary text-primary-content flex items-center justify-center font-bold">
                    {{ substr(auth()->user()->name ?? 'A', 0, 1) }}
                </div>
            </div>
            <ul tabindex="0" class="menu menu-sm dropdown-content mt-3 z-[1] p-2 shadow bg-base-100 rounded-box w-52">
                @forelse ($userMenuItems as $userItem)
                    @if ($userItem->divider)
                        <li class="menu-divider"></li>
                    @endif
                    <li>
                        @if ($userItem->method && $userItem->method !== 'GET')
                            <form method="POST" action="{{ $userItem->href() }}">
                                @csrf
                                @method($userItem->method)
                                <button type="submit" class="text-left w-full">
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
                    <li>
                        <a class="justify-between">
                            {{ auth()->user()->name ?? 'Admin' }}
                            <span class="badge">New</span>
                        </a>
                    </li>
                    <li><a href="{{ route('profile.edit') }}">Settings</a></li>
                    <li>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="text-left w-full">Logout</button>
                        </form>
                    </li>
                @endforelse
            </ul>
        </div>
    </div>
</header>
