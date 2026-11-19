{{--
    The end of <body>: the Tardis core script has to run before Livewire starts
    Alpine, so it comes first. Keep this component in a published layout rather
    than the two directives, so the order stays the package's.
--}}
@props(['scope' => 'admin'])
@tardisScripts($scope)
@livewireScripts
