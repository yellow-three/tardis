@props([
    'title' => null,
    'description' => null,
])

<div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between mb-6">
    <div>
        @if ($title)
            <h1 class="text-2xl font-bold">{{ $title }}</h1>
        @endif

        @if ($description)
            <p class="text-base-content/60 mt-1">{{ $description }}</p>
        @endif
    </div>

    @isset($action)
        <div>{{ $action }}</div>
    @endisset
</div>
