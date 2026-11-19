{{--
    Everything a Tardis page needs in <head>, in the order it needs it: the
    theme data before the stylesheets, Tardis styles before Livewire's.

    A published layout should keep this component rather than the directives
    it wraps, so a later release can change the order in one place.

    $theme is the resolved choice the layout already used for data-theme, so it
    is worked out once per request.
--}}
@props(['scope' => 'admin', 'theme' => null])
<x-tardis::theme-boot :choice="$theme" />
@tardisStyles($scope)
@livewireStyles
