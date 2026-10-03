@props(['title' => null])

{{-- A side drawer for options that do not deserve a page. Toggle with $dispatch('toggle-slide-in'). --}}
<div
    x-data="{ open: false }"
    x-on:toggle-slide-in.window="open = ! open"
    x-on:keydown.escape.window="open = false"
>
    <aside
        x-show="open"
        x-cloak
        x-transition
        class="fixed inset-y-0 right-0 z-50 flex w-full max-w-md flex-col border-l border-base-300 bg-base-100 shadow-xl"
        role="dialog"
        aria-modal="true"
        @if ($title) aria-label="{{ $title }}" @endif
    >
        <header class="flex items-center justify-between border-b border-base-300 px-4 py-3">
            <h2 class="font-semibold">{{ $title }}</h2>
            <button type="button" class="btn btn-ghost btn-sm btn-circle" x-on:click="open = false" aria-label="{{ __('tardis::appearance.close') }}">✕</button>
        </header>

        <div class="flex-1 overflow-y-auto p-4">{{ $slot }}</div>
    </aside>
</div>
