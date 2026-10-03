@php($count = count(app(\Tardis\Bread\BreadManager::class)->all()))

<div class="flex items-center gap-4">
    <div class="p-3 rounded-box bg-secondary/10 text-secondary">
        <x-tardis::icon name="database" class="w-8 h-8" />
    </div>
    <div>
        <p class="text-3xl font-bold">{{ $count }}</p>
        <p class="text-sm text-base-content/60">{{ __('tardis::dashboard.breads') }}</p>
    </div>
</div>
