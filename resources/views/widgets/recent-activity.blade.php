@php
    try {
        $recent = \Illuminate\Support\Facades\DB::table('activity_logs')->orderByDesc('created_at')->limit(5)->get();
    } catch (\Throwable) {
        $recent = collect();
    }
@endphp

<h2 class="card-title">{{ __('tardis::dashboard.recent_activity') }}</h2>
@if ($recent->isEmpty())
    <p class="text-base-content/60 text-center py-4">{{ __('tardis::dashboard.no_recent_activities') }}</p>
@else
    <div class="divide-y divide-base-200">
        @foreach ($recent as $activity)
            <div class="flex items-center gap-3 py-3">
                <x-tardis::badge :color="match ($activity->action) { 'created' => 'success', 'updated' => 'info', 'deleted' => 'error', default => 'ghost' }">{{ $activity->action }}</x-tardis::badge>
                <span class="text-sm">{{ class_basename($activity->model_type) }} #{{ $activity->model_id }}</span>
                <span class="text-xs text-base-content/40 ml-auto">{{ $activity->created_at }}</span>
            </div>
        @endforeach
    </div>
@endif
