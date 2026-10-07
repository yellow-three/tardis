<div x-data="{ items: @entangle($model).live }" class="flex flex-col gap-2">
    <template x-for="(item, index) in items" :key="index">
        <div class="flex items-center gap-2">
            <input
                type="text"
                x-model="items[index]"
                @disabled($disabled) @readonly($readonly)
                placeholder="{{ $placeholder }}"
                class="input w-full"
            />
            <button
                type="button"
                class="btn btn-ghost btn-sm"
                @if (! $readonly && ! $disabled) @click="items.splice(index, 1)" @else disabled @endif
                aria-label="{{ __('tardis::bread.remove_row') }}"
            >
                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
    </template>

    @if (! $readonly && ! $disabled)
        <button type="button" class="btn btn-outline btn-sm w-fit" @click="items.push('')">
            {{ __('tardis::bread.add_row') }}
        </button>
    @endif
</div>
