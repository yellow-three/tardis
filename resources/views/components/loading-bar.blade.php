{{-- A thin bar across the top while Livewire navigates between pages. --}}
<div
    x-data="{ busy: false }"
    x-on:livewire:navigate.document="busy = true"
    x-on:livewire:navigated.document="busy = false"
    x-show="busy"
    x-cloak
    class="fixed inset-x-0 top-0 z-[60] h-1 overflow-hidden"
    role="progressbar"
    aria-hidden="true"
>
    <div class="h-full w-1/3 animate-pulse bg-primary"></div>
</div>
