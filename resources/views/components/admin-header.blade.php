@props(['title' => 'Dashboard'])

<header class="navbar bg-base-100 border-b border-base-300 sticky top-0 z-30">
    <div class="flex-none lg:hidden">
        <label for="tardis-drawer" class="btn btn-square btn-ghost">
            <x-heroicon-o-bars-3 class="w-6 h-6" />
        </label>
    </div>

    <div class="flex-1">
        <span class="text-xl font-bold px-4">{{ $title }}</span>
    </div>

    <div class="flex-none gap-1">
        <div class="swap swap-rotate btn btn-ghost btn-sm" @click="$store.theme.toggle()" :class="{ 'swap-active': $store.theme.mode !== 'light' }">
            <x-heroicon-o-sun class="swap-on w-5 h-5" />
            <x-heroicon-o-moon class="swap-off w-5 h-5" />
        </div>

        <div class="dropdown dropdown-end">
            <div tabindex="0" role="button" class="btn btn-ghost btn-sm">
                <x-heroicon-o-cog-6-tooth class="w-5 h-5" />
            </div>
            <ul tabindex="0" class="dropdown-content menu p-2 shadow bg-base-100 rounded-box w-48 mt-1 z-[1]">
                <li>
                    <a @click="$store.theme.mode = 'light'; $store.theme.apply()" :class="{ active: $store.theme.mode === 'light' }">
                        <x-heroicon-o-sun class="w-4 h-4" /> Light
                    </a>
                </li>
                <li>
                    <a @click="$store.theme.mode = 'dark'; $store.theme.apply()" :class="{ active: $store.theme.mode === 'dark' }">
                        <x-heroicon-o-moon class="w-4 h-4" /> Dark
                    </a>
                </li>
                <li>
                    <a @click="$store.theme.mode = 'system'; $store.theme.apply()" :class="{ active: $store.theme.mode === 'system' }">
                        <x-heroicon-o-computer-desktop class="w-4 h-4" /> System
                    </a>
                </li>
            </ul>
        </div>

        <div class="dropdown dropdown-end">
            <div tabindex="0" role="button" class="btn btn-ghost btn-circle avatar">
                <div class="w-10 rounded-full bg-primary text-primary-content flex items-center justify-center font-bold">
                    {{ substr(auth()->user()->name ?? 'A', 0, 1) }}
                </div>
            </div>
            <ul tabindex="0" class="menu menu-sm dropdown-content mt-3 z-[1] p-2 shadow bg-base-100 rounded-box w-52">
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
            </ul>
        </div>
    </div>
</header>
