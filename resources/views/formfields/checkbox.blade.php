@if ($options)
    <div class="flex flex-wrap gap-3">
        @foreach ($options as $optionValue => $optionLabel)
            <label class="flex items-center gap-2 cursor-pointer">
                <input type="checkbox" wire:model="{{ $model }}" value="{{ $optionValue }}" @disabled($disabled) class="checkbox checkbox-sm checkbox-primary" />
                <span class="label-text">{{ $optionLabel }}</span>
            </label>
        @endforeach
    </div>
@else
    <input type="checkbox" id="{{ $id }}" wire:model="{{ $model }}" @disabled($disabled) @readonly($readonly) class="checkbox checkbox-primary" />
@endif
