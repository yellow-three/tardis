@php($current = $value ?? null)
@if (is_string($current) && $current !== '')
    <img src="{{ $field->thumbnailUrl($current, $field->thumbnails[0]['name'] ?? 'thumbnail') }}" alt="" class="mb-2 h-24 w-24 rounded-box object-cover border border-base-300" loading="lazy" />
@endif
<input type="file" accept="image/*" id="{{ $id }}" wire:model="{{ $model }}" @disabled($disabled) @readonly($readonly) class="file-input w-full" @foreach ($extraAttributes as $attr => $attrValue) {{ $attr }}="{{ $attrValue }}" @endforeach />
@if ($mimes)
    <span class="text-base-content/60 text-xs">{{ __('tardis::fields.allowed_types', ['types' => implode(', ', $mimes)]) }}</span>
@endif
