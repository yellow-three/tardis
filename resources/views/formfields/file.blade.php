<input type="file" id="{{ $id }}" wire:model="{{ $model }}" @disabled($disabled) @readonly($readonly) class="file-input w-full" @foreach ($extraAttributes as $attr => $attrValue) {{ $attr }}="{{ $attrValue }}" @endforeach />
@if ($mimes)
    <span class="text-base-content/60 text-xs">{{ __('tardis::fields.allowed_types', ['types' => implode(', ', $mimes)]) }}</span>
@endif
