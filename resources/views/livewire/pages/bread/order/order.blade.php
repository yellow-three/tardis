@php($rows = $this->rows())
<div
    x-data="{
        dragging: null,
        start(index) { this.dragging = index },
        drop(index) {
            if (this.dragging === null || this.dragging === index) { this.dragging = null; return }
            const ids = Array.from(this.$refs.list.querySelectorAll('[data-id]')).map(el => el.dataset.id)
            const [moved] = ids.splice(this.dragging, 1)
            ids.splice(index, 0, moved)
            this.dragging = null
            this.$wire.saveOrder(ids)
        },
    }"
>
    <x-tardis::page-header :title="__('tardis::bread.order_title', ['name' => app(\Tardis\Bread\BreadManager::class)->find($slug)?->resolvedNamePlural() ?? $slug])" :description="__('tardis::bread.order_description')">
        <x-slot:action>
            <a href="{{ route('tardis.bread.index', ['slug' => $slug]) }}" wire:navigate class="btn btn-ghost btn-sm">{{ __('tardis::bread.back_to_list') }}</a>
        </x-slot:action>
    </x-tardis::page-header>

    @if (session('message'))
        <div class="alert alert-success mb-4" role="status"><span>{{ session('message') }}</span></div>
    @endif

    @if ($this->total() > count($rows))
        <div class="alert alert-warning mb-4" role="status"><span>{{ __('tardis::bread.order_limited', ['count' => count($rows)]) }}</span></div>
    @endif

    <x-tardis::card>
        @if ($rows === [])
            <p class="text-base-content/60">{{ __('tardis::bread.no_records_found') }}</p>
        @else
            <ol x-ref="list" class="divide-y divide-base-200">
                @foreach ($rows as $index => $row)
                    <li
                        wire:key="order-{{ $row['id'] }}"
                        data-id="{{ $row['id'] }}"
                        draggable="true"
                        x-on:dragstart="start({{ $index }})"
                        x-on:dragover.prevent
                        x-on:drop.prevent="drop({{ $index }})"
                        class="flex items-center gap-3 py-2 cursor-move"
                    >
                        <x-tardis::icon name="bars-3" class="w-4 h-4 text-base-content/40" aria-hidden="true" />
                        <span class="w-8 text-end text-sm text-base-content/50">{{ $index + 1 }}</span>
                        <span class="flex-1">{{ $row['label'] }}</span>
                        <button type="button" wire:click="move(@js($row['id']), -1)" @disabled($index === 0) class="btn btn-ghost btn-xs" aria-label="{{ __('tardis::menu_builder.move_up') }}">▲</button>
                        <button type="button" wire:click="move(@js($row['id']), 1)" @disabled($index === count($rows) - 1) class="btn btn-ghost btn-xs" aria-label="{{ __('tardis::menu_builder.move_down') }}">▼</button>
                    </li>
                @endforeach
            </ol>
        @endif
    </x-tardis::card>
</div>
