<div x-data="{ rows: @entangle($model).live }" class="flex flex-col gap-3">
    <template x-for="(row, index) in rows" :key="index">
        <div class="card bg-base-200 border border-base-300">
            <div class="card-body p-4 gap-3">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <template x-for="sub in {{ Illuminate\Support\Js::from($subfields) }}" :key="sub.name">
                        <label class="flex flex-col gap-1 w-full">
                            <span class="text-base-content/70 text-sm" x-text="sub.label"></span>

                            <template x-if="sub.type === 'textarea'">
                                <textarea class="textarea w-full" rows="3" x-model="rows[index][sub.name]"></textarea>
                            </template>

                            <template x-if="sub.type === 'number'">
                                <input type="number" step="any" class="input w-full" x-model="rows[index][sub.name]" />
                            </template>

                            <template x-if="sub.type === 'select'">
                                <select class="select w-full" x-model="rows[index][sub.name]">
                                    <option value="">—</option>
                                    <template x-for="pair in Object.entries(sub.options)" :key="pair[0]">
                                        <option :value="pair[0]" x-text="pair[1]"></option>
                                    </template>
                                </select>
                            </template>

                            <template x-if="sub.type === 'text'">
                                <input type="text" class="input w-full" x-model="rows[index][sub.name]" />
                            </template>
                        </label>
                    </template>
                </div>

                @if (! $readonly && ! $disabled)
                    <button
                        type="button"
                        class="btn btn-ghost btn-xs w-fit"
                        @click="rows.splice(index, 1)"
                    >{{ __('tardis::bread.remove_row') }}</button>
                @endif
            </div>
        </div>
    </template>

    @if (! $readonly && ! $disabled)
        <button
            type="button"
            class="btn btn-outline btn-sm w-fit"
            @click="rows.push({{ Illuminate\Support\Js::from($blankRow) }})"
        >{{ __('tardis::bread.add_row') }}</button>
    @endif
</div>
