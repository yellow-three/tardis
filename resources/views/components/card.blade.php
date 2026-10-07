@props(['title' => null, 'icon' => null])

<section {{ $attributes->class(['card bg-base-100 border border-base-300']) }}>
    @if ($title || $icon || isset($actions))
        <header class="flex flex-wrap items-center justify-between gap-2 border-b border-base-300 px-4 py-3">
            <h2 class="flex items-center gap-2 text-base font-semibold">
                @if ($icon)
                    <x-tardis::icon :name="$icon" class="w-5 h-5" />
                @endif
                {{ $title }}
            </h2>

            @isset($actions)
                <div class="flex flex-wrap items-center gap-2">{{ $actions }}</div>
            @endisset
        </header>
    @endif

    <div class="card-body p-4">{{ $slot }}</div>
</section>
