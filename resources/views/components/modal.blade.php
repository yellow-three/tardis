@props(['name', 'title' => null])

{{--
    A dialog driven by an Alpine flag. Open it with
    $dispatch('open-modal', 'name') and close it with $dispatch('close-modal', 'name').
--}}
<div
    x-data="{ open: false }"
    x-on:open-modal.window="if ($event.detail === @js($name)) open = true"
    x-on:close-modal.window="if ($event.detail === @js($name)) open = false"
    x-on:keydown.escape.window="open = false"
    x-show="open"
    x-cloak
    class="modal modal-open"
    role="dialog"
    aria-modal="true"
    @if ($title) aria-label="{{ $title }}" @endif
>
    <div class="modal-box" x-on:click.outside="open = false">
        @if ($title)
            <h3 class="mb-3 text-lg font-bold">{{ $title }}</h3>
        @endif

        {{ $slot }}

        @isset($footer)
            <div class="modal-action">{{ $footer }}</div>
        @endisset
    </div>
</div>
