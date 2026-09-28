@props(['item', 'level' => 0])

@if ($item->isDivider)
    <li class="my-1 h-px bg-base-300/60" x-show="typeof menuQuery === 'string' && menuQuery.trim() === ''"></li>
@else
    @php
        $hasChildren = $item->children->isNotEmpty();
        $isActive = $item->isActive();
        $isExpanded = $isActive || $item->children->some(fn ($child) => $child->isActive());
        $isTopLevel = $level === 0;
    @endphp

    <li
        data-menu-title="{{ $item->title }}"
        @if ($isTopLevel) x-show="typeof menuFilter === 'function' ? menuFilter($el) : true" @endif
    >
        @if ($hasChildren)
            {{-- x-data + @toggle keep aria-expanded truthful when the user opens/closes it,
                 which a static Blade-rendered value would let drift out of date. --}}
            <details
                {{ $isExpanded ? 'open' : '' }}
                class="group"
                x-data="{ menuOpen: {{ $isExpanded ? 'true' : 'false' }} }"
                @toggle="menuOpen = $event.target.open"
            >
                <summary
                    @if ($isActive) aria-current="page" @endif
                    :aria-expanded="menuOpen"
                    aria-label="{{ $item->title }}"
                    data-tip="{{ $item->title }}"
                    class="relative flex cursor-pointer list-none items-center gap-2.5 rounded-lg py-2 pl-3 pr-2.5 text-[13px] transition-colors is-drawer-close:justify-center is-drawer-close:pl-2.5 is-drawer-close:after:hidden is-drawer-close:tooltip is-drawer-close:tooltip-right focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary {{ $isActive ? 'menu-active' : '' }}"
                >
                    @if ($isActive)
                        <span aria-hidden="true" class="absolute left-0 top-1/2 h-5 w-0.5 -translate-y-1/2 rounded-full bg-primary"></span>
                    @endif

                    @if ($item->icon)
                        <x-tardis::icon :name="$item->icon" class="h-[18px] w-[18px] shrink-0" />
                    @endif

                    <span class="tardis-sidebar-label flex min-w-0 flex-1 items-center gap-2">
                        <span class="truncate">{{ $item->title }}</span>
                        @if ($item->badgeColor)
                            <span class="badge badge-{{ $item->badgeColor }} badge-sm ml-auto">
                                {{ $item->badgeValue ?? '' }}
                            </span>
                        @endif
                    </span>

                    <x-tardis::icon
                        name="chevron-down"
                        class="tardis-sidebar-label h-3.5 w-3.5 shrink-0 text-base-content/45 transition-transform duration-150 group-open:rotate-180"
                    />
                </summary>

                <ul class="mt-0.5 ml-3.5 flex flex-col gap-0.5 border-l border-base-300 pl-1.5 is-drawer-close:hidden">
                    @foreach ($item->children as $child)
                        @include('tardis::partials.menu-item', ['item' => $child, 'level' => $level + 1])
                    @endforeach
                </ul>
            </details>
        @else
            <a
                @if ($isActive) aria-current="page" @endif
                aria-label="{{ $item->title }}"
                href="{{ $item->href() }}"
                data-tip="{{ $item->title }}"
                wire:navigate
                class="relative flex items-center gap-2.5 rounded-lg py-2 pl-3 pr-2.5 text-[13px] transition-colors is-drawer-close:justify-center is-drawer-close:pl-2.5 is-drawer-close:tooltip is-drawer-close:tooltip-right focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary {{ $isActive ? 'menu-active' : '' }}"
            >
                @if ($isActive)
                    <span aria-hidden="true" class="absolute left-0 top-1/2 h-5 w-0.5 -translate-y-1/2 rounded-full bg-primary"></span>
                @endif

                @if ($item->icon)
                    <x-tardis::icon :name="$item->icon" class="h-[18px] w-[18px] shrink-0" />
                @endif

                <span class="tardis-sidebar-label flex min-w-0 flex-1 items-center gap-2">
                    <span class="truncate">{{ $item->title }}</span>
                    @if ($item->badgeColor)
                        <span class="badge badge-{{ $item->badgeColor }} badge-sm ml-auto">
                            {{ $item->badgeValue ?? '' }}
                        </span>
                    @endif
                </span>
            </a>
        @endif
    </li>
@endif
