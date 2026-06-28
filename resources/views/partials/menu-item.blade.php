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
            <summary class="{{ $isActive ? 'active' : '' }}">
                @if ($item->icon)
                    <x-dynamic-component :component="$item->icon" class="w-5 h-5" />
                @endif
                {{ $item->title }}
                @if ($item->badgeColor)
                    <span class="badge badge-{{ $item->badgeColor }} badge-sm ml-auto">
                        {{ $item->badgeValue ?? '' }}
                    </span>
                @endif
            </summary>
            <ul>
                @foreach ($item->children as $child)
                    @include('tardis::partials.menu-item', ['item' => $child, 'level' => $level + 1])
                @endforeach
            </ul>
        </details>
    @else
        <a href="{{ $item->href() }}" class="{{ $isActive ? 'active' : '' }}">
            @if ($item->icon)
                <x-dynamic-component :component="$item->icon" class="w-5 h-5" />
            @endif
            {{ $item->title }}
            @if ($item->badgeColor)
                <span class="badge badge-{{ $item->badgeColor }} badge-sm ml-auto">
                    {{ $item->badgeValue ?? '' }}
                </span>
            @endif
        </a>
    @endif

    @if ($level > 0)
        </li>
    @endif
@endif
