<x-tardis::admin-layout title="Media">
    @push('styles')
        @livewireStyles
    @endpush

    <livewire:tardis-media::media-index />

    @push('scripts')
        @livewireScripts
    @endpush
</x-tardis::admin-layout>
