@php
    try {
        $count = \Illuminate\Support\Facades\DB::table('tardis_media')->count();
    } catch (\Throwable) {
        $count = 0;
    }
@endphp

<div class="flex items-center gap-4">
    <div class="p-3 rounded-box bg-accent/10 text-accent">
        <x-tardis::icon name="photo" class="w-8 h-8" />
    </div>
    <div>
        <p class="text-3xl font-bold">{{ $count }}</p>
        <p class="text-sm text-base-content/60">{{ __('tardis::dashboard.media_files') }}</p>
    </div>
</div>
