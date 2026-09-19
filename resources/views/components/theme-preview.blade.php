@props(['theme'])

<div class="flex items-center gap-3 p-3 rounded-lg bg-base-200">
    {{-- Color swatches --}}
    <div class="flex gap-1">
        @foreach (($theme['previewColors'] ?? []) as $color)
            <div
                class="w-6 h-6 rounded-full border border-base-300 shrink-0"
                style="background-color: {{ $color }}"
                title="{{ $color }}"
            ></div>
        @endforeach
    </div>

    {{-- Theme info --}}
    <div class="flex-1 min-w-0">
        <div class="font-medium text-sm truncate">{{ $theme['name'] ?? 'Unknown' }}</div>
        <div class="flex items-center gap-2 mt-0.5">
            <span class="badge badge-ghost badge-xs">{{ ucfirst($theme['colorScheme'] ?? 'light') }}</span>
            @if (!empty($theme['default']))
                <span class="badge badge-primary badge-xs">Default</span>
            @endif
        </div>
    </div>
</div>
