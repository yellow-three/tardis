{{--
    Previous / next for a paged media listing. The component calls
    $action(page) on the Livewire component it sits in.
--}}
@props(['page', 'lastPage', 'action' => 'goToPage'])
@php
    // Worked out here: the static-text check reads a `>` inside an attribute
    // as the end of the tag.
    $previous = $page - 1;
    $next = $page + 1;
    $isFirst = $page <= 1;
    $isLast = $page >= $lastPage;
    $hasPages = $lastPage > 1;
@endphp
@if ($hasPages)
    <nav class="flex items-center justify-center gap-3 text-sm" aria-label="{{ __('tardis::media.page_of', ['page' => $page, 'last' => $lastPage]) }}">
        <button type="button" class="btn btn-ghost btn-sm" wire:click="{{ $action }}({{ $previous }})" @disabled($isFirst) aria-label="{{ __('tardis::media.previous_page') }}">
            <x-tardis::icon name="chevron-left" class="w-4 h-4" />
        </button>
        <span class="text-base-content/60">{{ __('tardis::media.page_of', ['page' => $page, 'last' => $lastPage]) }}</span>
        <button type="button" class="btn btn-ghost btn-sm" wire:click="{{ $action }}({{ $next }})" @disabled($isLast) aria-label="{{ __('tardis::media.next_page') }}">
            <x-tardis::icon name="chevron-right" class="w-4 h-4" />
        </button>
    </nav>
@endif
