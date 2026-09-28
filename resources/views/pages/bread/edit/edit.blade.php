<div>
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold">Edit {{ $bread['name'] ?? ucfirst($slug) }}</h1>
            <p class="text-base-content/60 mt-1">Update the selected record.</p>
        </div>
    </div>

    <form wire:submit="save" class="card bg-base-100 border border-base-300">
        <div class="card-body space-y-5">
            @foreach ($this->fields as $field)
                @php($name = $field['name'] ?? '')
                @php($label = $field['label'] ?? ucfirst((string) $name))
                @php($type = $field['type'] ?? 'text')
                @php($translatable = ! empty($field['translatable']))
                @php($translatableLocales = $translatable ? \Tardis\Classes\Translation::locales($field['locales'] ?? null) : [])

                <div class="flex flex-col gap-2">
                    <label class="label">
                        <span class="text-base-content font-medium">{{ $label }}</span>
                    </label>

                    @if ($type === 'textarea')
                        @if ($translatable)
                            @foreach ($translatableLocales as $locale)
                                <div class="mb-2">
                                    <span class="badge badge-ghost badge-sm uppercase mr-2">{{ $locale }}</span>
                                    <textarea wire:model="form.{{ $name }}.{{ $locale }}" class="textarea w-full" rows="4"></textarea>
                                </div>
                            @endforeach
                        @else
                            <textarea wire:model="form.{{ $name }}" class="textarea" rows="4"></textarea>
                        @endif
                    @elseif ($type === 'toggle')
                        <label class="label cursor-pointer justify-start gap-3">
                            <input type="checkbox" wire:model="form.{{ $name }}" class="checkbox" />
                            <span class="text-base-content">{{ $label }}</span>
                        </label>
                    @elseif ($type === 'select')
                        <select wire:model="form.{{ $name }}" class="select w-full">
                            <option value="">—</option>
                            @foreach (($field['options'] ?? []) as $optionValue => $optionLabel)
                                <option value="{{ $optionValue }}">{{ $optionLabel }}</option>
                            @endforeach
                        </select>
                    @elseif ($type === 'password')
                        <input type="password" wire:model="form.{{ $name }}" class="input" autocomplete="new-password" />
                    @elseif ($type === 'file')
                        <input type="file" wire:model="form.{{ $name }}" class="file-input w-full" />
                    @elseif ($type === 'belongs_to_many')
                        <input
                            type="search"
                            wire:model.live.debounce.300ms="relationSearch.{{ $name }}"
                            placeholder="Search {{ $label }}..."
                            class="input input-sm w-full mb-2"
                        />
                        <div class="border border-base-300 rounded-lg max-h-48 overflow-y-auto">
                            @forelse (($this->relationResults[$name] ?? []) as $optionValue => $optionLabel)
                                <label class="flex items-center gap-2 px-3 py-1.5 hover:bg-base-200 cursor-pointer">
                                    <input type="checkbox" wire:model="form.{{ $name }}" value="{{ $optionValue }}" class="checkbox checkbox-sm" />
                                    <span class="text-sm">{{ $optionLabel }}</span>
                                </label>
                            @empty
                                <p class="px-3 py-2 text-sm text-base-content/60">No matches found.</p>
                            @endforelse
                        </div>
                    @elseif ($type === 'has_many')
                        <div class="border border-base-300 rounded-lg p-4 text-sm text-base-content/60">
                            Related items will be managed here.
                        </div>
                    @else
                        @if ($translatable)
                            @foreach ($translatableLocales as $locale)
                                <div class="mb-2">
                                    <span class="badge badge-ghost badge-sm uppercase mr-2">{{ $locale }}</span>
                                    <input type="{{ $type === 'number' ? 'number' : 'text' }}" wire:model="form.{{ $name }}.{{ $locale }}" class="input w-full" />
                                </div>
                            @endforeach
                        @else
                            <input type="{{ $type === 'number' ? 'number' : 'text' }}" wire:model="form.{{ $name }}" class="input" />
                        @endif
                    @endif
                </div>
            @endforeach

            <div class="card-actions justify-end">
                <a href="{{ url(trim(config('tardis.admin.prefix', 'admin'), '/').'/'.$slug) }}" class="btn btn-ghost">Cancel</a>
                <button type="submit" class="btn btn-primary">Update</button>
            </div>
        </div>
    </form>
</div>
