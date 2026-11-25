@props(['name', 'items' => null, 'depth' => 1])

{{--
    A site menu built in the panel (Menus → Site menus), for the host's own
    pages: <x-tardis::site-menu name="main" class="menu menu-horizontal" />.
    Nested items render as nested lists; the current page's item gets
    aria-current="page".
--}}
@php
    $items ??= tardis_menu($name);
    $current = url()->current();
@endphp

@if ($items !== [])
    <ul {{ $depth === 1 ? $attributes : '' }}>
        @foreach ($items as $item)
            @php
                $href = \Tardis\Support\SafeUrl::isAllowed($item['url'], allowFragment: true)
                    ? (str_starts_with($item['url'], '#') ? $item['url'] : url($item['url']))
                    : '#';
                $active = rtrim($href, '/') === rtrim($current, '/');
            @endphp
            <li>
                <a href="{{ $href }}"
                   @if ($item['new_tab'] ?? false) target="_blank" rel="noopener noreferrer" @endif
                   @if ($active) aria-current="page" @endif
                >{{ $item['title'] }}</a>
                @if (($item['children'] ?? []) !== [])
                    <x-tardis::site-menu :name="$name" :items="$item['children']" :depth="$depth + 1" />
                @endif
            </li>
        @endforeach
    </ul>
@endif
