@props(['color' => null])

@php
    $palette = ['primary', 'secondary', 'accent', 'neutral', 'info', 'success', 'warning', 'error', 'ghost'];
    $tone = in_array($color, $palette, true) ? $color : 'ghost';
@endphp

<span {{ $attributes->class(['badge badge-sm', 'badge-'.$tone]) }}>{{ $slot }}</span>
