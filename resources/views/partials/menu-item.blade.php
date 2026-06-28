@props(['item', 'level' => 0])

@if ($item->isDivider)
    <li class="divider"></li>
@else
    @php
        $hasChildren = $item->children->isNotEmpty();
        $isActive = $item->isActive();
        $isExpanded = $isActive || $item->children->some(fn ($child) => $child->isActive());
    @endphp

    @if ($level > 0)
        <li>
    @endif

    @if ($hasChildren)
        <details {{ $isExpanded ? 'open' : '' }}>
            <summary class="{{ $isActive ? 'active' : '' }} flex items-center gap-2 lg:is-drawer-open:tooltip lg:is-drawer-open:tooltip-right" data-tip="{{ $item->title }}">
                @if ($item->icon)
                    <x-dynamic-component :component="$item->icon" class="w-5 h-5 flex-shrink-0" />
                @endif
                <span class="lg:is-drawer-open:hidden flex items-center gap-2 flex-1 min-w-0">
                    <span class="truncate">{{ $item->title }}</span>
                    @if ($item->badgeColor)
                        <span class="badge badge-{{ $item->badgeColor }} badge-sm ml-auto">
                            {{ $item->badgeValue ?? '' }}
                        </span>
                    @endif
                </span>
            </summary>
            <ul class="lg:is-drawer-open:hidden">
                @foreach ($item->children as $child)
                    @include('tardis::partials.menu-item', ['item' => $child, 'level' => $level + 1])
                @endforeach
            </ul>
        </details>
    @else
        <a href="{{ $item->href() }}" class="{{ $isActive ? 'active' : '' }} flex items-center gap-2 lg:is-drawer-open:tooltip lg:is-drawer-open:tooltip-right" data-tip="{{ $item->title }}">
            @if ($item->icon)
                <x-dynamic-component :component="$item->icon" class="w-5 h-5 flex-shrink-0" />
            @endif
            <span class="lg:is-drawer-open:hidden flex items-center gap-2 flex-1 min-w-0">
                <span class="truncate">{{ $item->title }}</span>
                @if ($item->badgeColor)
                    <span class="badge badge-{{ $item->badgeColor }} badge-sm ml-auto">
                        {{ $item->badgeValue ?? '' }}
                    </span>
                @endif
            </span>
        </a>
    @endif

    @if ($level > 0)
        </li>
    @endif
@endif
