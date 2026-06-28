<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Illuminate\Support\Facades\DB;
use Tardis\Bread\Repositories\JsonBreadRepository;

new #[Title('Dashboard')] #[Layout('tardis::layouts.admin')] class extends Component
{
    public int $totalUsers = 0;

    public int $totalBreads = 0;

    public int $totalMedia = 0;

    public int $totalActivities = 0;

    public array $recentActivities = [];

    public function mount(): void
    {
        $this->loadStats();
    }

    public function loadStats(): void
    {
        try {
            $this->totalUsers = DB::table('users')->count();
        } catch (\Throwable) {
            $this->totalUsers = 0;
        }

        $repo = app(JsonBreadRepository::class);
        $this->totalBreads = count($repo->all());

        try {
            $this->totalMedia = DB::table('tardis_media')->count();
        } catch (\Throwable) {
            $this->totalMedia = 0;
        }

        try {
            $this->totalActivities = DB::table('activity_logs')->count();
            $this->recentActivities = DB::table('activity_logs')
                ->orderByDesc('created_at')
                ->limit(5)
                ->get()
                ->toArray();
        } catch (\Throwable) {
            $this->totalActivities = 0;
            $this->recentActivities = [];
        }
    }
}; ?>

<div>
    <div class="mb-6">
        <h1 class="text-2xl font-bold">Dashboard</h1>
        <p class="text-base-content/60 mt-1">Welcome to TARDIS Admin</p>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <div class="card bg-base-100 shadow-sm">
            <div class="card-body">
                <div class="flex items-center gap-4">
                    <div class="p-3 rounded-box bg-primary/10 text-primary">
                        <x-tardis::icon name="user-group" class="w-8 h-8" />
                    </div>
                    <div>
                        <p class="text-3xl font-bold">{{ $totalUsers }}</p>
                        <p class="text-sm opacity-60">Users</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="card bg-base-100 shadow-sm">
            <div class="card-body">
                <div class="flex items-center gap-4">
                    <div class="p-3 rounded-box bg-secondary/10 text-secondary">
                        <x-tardis::icon name="database" class="w-8 h-8" />
                    </div>
                    <div>
                        <p class="text-3xl font-bold">{{ $totalBreads }}</p>
                        <p class="text-sm opacity-60">BREADs</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="card bg-base-100 shadow-sm">
            <div class="card-body">
                <div class="flex items-center gap-4">
                    <div class="p-3 rounded-box bg-accent/10 text-accent">
                        <x-tardis::icon name="photo" class="w-8 h-8" />
                    </div>
                    <div>
                        <p class="text-3xl font-bold">{{ $totalMedia }}</p>
                        <p class="text-sm opacity-60">Media Files</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="card bg-base-100 shadow-sm">
            <div class="card-body">
                <div class="flex items-center gap-4">
                    <div class="p-3 rounded-box bg-info/10 text-info">
                        <x-tardis::icon name="clock" class="w-8 h-8" />
                    </div>
                    <div>
                        <p class="text-3xl font-bold">{{ $totalActivities }}</p>
                        <p class="text-sm opacity-60">Activities</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Activities -->
    <div class="card bg-base-100 shadow-sm">
        <div class="card-body">
            <h2 class="card-title">Recent Activity</h2>
            @if (empty($recentActivities))
                <p class="text-base-content/60 text-center py-4">No recent activities</p>
            @else
                <div class="divide-y divide-base-200">
                    @foreach ($recentActivities as $activity)
                        <div class="flex items-center gap-3 py-3">
                            <span class="badge badge-{{ match($activity->action) { 'created' => 'success', 'updated' => 'info', 'deleted' => 'error', default => 'ghost' } }} badge-sm">
                                {{ $activity->action }}
                            </span>
                            <span class="text-sm">{{ class_basename($activity->model_type) }} #{{ $activity->model_id }}</span>
                            <span class="text-xs opacity-40 ml-auto">{{ $activity->created_at }}</span>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</div>
