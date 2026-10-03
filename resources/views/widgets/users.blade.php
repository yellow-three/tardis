@php
    try {
        $count = \Illuminate\Support\Facades\DB::table('users')->count();
    } catch (\Throwable) {
        $count = 0;
    }
@endphp

<div class="flex items-center gap-4">
    <div class="p-3 rounded-box bg-primary/10 text-primary">
        <x-tardis::icon name="user-group" class="w-8 h-8" />
    </div>
    <div>
        <p class="text-3xl font-bold">{{ $count }}</p>
        <p class="text-sm text-base-content/60">{{ __('tardis::dashboard.users') }}</p>
    </div>
</div>
