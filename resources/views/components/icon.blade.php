@props(['name', 'class' => 'w-5 h-5'])

@php
    // 1) Check semantic alias (e.g. nav.roles → heroicon-o-user-group)
    //    NOTE: Use array access not dot-notation, because keys like "ui.menu"
    //    contain dots that would be interpreted as path separators.
    $aliases = config('tardis-icons.aliases', []);
    $resolved = $aliases[$name] ?? $name;

    // 2) If it already looks like a full prefixed name (heroicon-o-*, heroicon-s-*), leave it
    // 3) Otherwise, assume it's a short name and prefix with heroicon-o-
    if (! str_starts_with($resolved, 'heroicon-')) {
        $resolved = 'heroicon-o-' . $resolved;
    }
@endphp

@svg($resolved, $class, $attributes->except(['name', 'class'])->getAttributes())
