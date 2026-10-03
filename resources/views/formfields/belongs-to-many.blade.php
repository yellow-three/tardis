<input
    type="search"
    wire:model.live.debounce.300ms="relationSearch.{{ $name }}"
    placeholder="{{ __('tardis::bread.search_name', ['name' => $label]) }}"
    class="input input-sm w-full mb-2"
/>
<div class="border border-base-300 rounded-lg max-h-48 overflow-y-auto">
    @forelse ($relationOptions ?? [] as $optionValue => $optionLabel)
        <label class="flex items-center gap-2 px-3 py-1.5 hover:bg-base-200 cursor-pointer">
            <input type="checkbox" wire:model="{{ $model }}" value="{{ $optionValue }}" class="checkbox checkbox-sm" />
            <span class="text-sm">{{ $optionLabel }}</span>
        </label>
    @empty
        <p class="px-3 py-2 text-sm text-base-content/60">{{ __('tardis::bread.no_matches_found') }}</p>
    @endforelse
</div>
