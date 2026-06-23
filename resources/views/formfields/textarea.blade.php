<div class="w-full @if($field->width < 12) md:col-span-{{ $field->width }} @endif @if($field->wrapperClass) {{ $field->wrapperClass }} @endif">
    <label for="field_{{ $name }}" class="label">
        <span class="label-text flex items-center gap-1">
            <x-heroicon-o-document-text class="w-4 h-4" />
            {{ $label }}
            @if($required)
                <span class="text-error">*</span>
            @endif
        </span>
    </label>
    <textarea
        id="field_{{ $name }}"
        wire:model.lazy="{{ $name }}"
        placeholder="{{ $placeholder }}"
        @if($disabled) disabled @endif
        @if($readonly) readonly @endif
        class="textarea textarea-bordered w-full"
        @foreach($attributes as $key => $value) {{ $key }}="{{ $value }}" @endforeach
    ></textarea>
    @error($name)
        <span class="text-error text-sm mt-1">{{ $message }}</span>
    @enderror
    @if($helpText)
        <span class="text-base-content/60 text-sm mt-1">{{ $helpText }}</span>
    @endif
</div>
