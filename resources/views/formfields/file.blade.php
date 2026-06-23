<div class="w-full @if($field->width < 12) md:col-span-{{ $field->width }} @endif @if($field->wrapperClass) {{ $field->wrapperClass }} @endif">
    <label for="field_{{ $name }}" class="label">
        <span class="label-text flex items-center gap-1">
            <x-heroicon-o-paper-clip class="w-4 h-4" />
            {{ $label }}
            @if($required)
                <span class="text-error">*</span>
            @endif
        </span>
    </label>
    <input
        id="field_{{ $name }}"
        type="file"
        wire:model.lazy="{{ $name }}"
        @if($disabled) disabled @endif
        @if($readonly) readonly @endif
        class="file-input file-input-bordered w-full"
        @foreach($attributes as $key => $value) {{ $key }}="{{ $value }}" @endforeach
    />
    @if($mimes ?? false)
        <span class="text-base-content/60 text-xs mt-1">{{ __('Allowed:') }} {{ implode(', ', $mimes) }}</span>
    @endif
    @error($name)
        <span class="text-error text-sm mt-1">{{ $message }}</span>
    @enderror
    @if($helpText)
        <span class="text-base-content/60 text-sm mt-1">{{ $helpText }}</span>
    @endif
</div>
