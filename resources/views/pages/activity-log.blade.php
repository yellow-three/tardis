<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Illuminate\Support\Facades\DB;

new #[Title('Activity Log')] #[Layout('tardis::layouts.admin')] class extends Component
{
    public array $activities = [];

    public string $filterAction = '';

    public string $filterUser = '';

    public string $search = '';

    public int $page = 1;

    public int $perPage = 25;

    public int $totalRows = 0;

    public function mount(): void
    {
        $this->loadActivities();
    }

    public function loadActivities(): void
    {
        $query = DB::table('activity_logs');

        if ($this->filterAction) {
            $query->where('action', $this->filterAction);
        }

        if ($this->filterUser) {
            $query->where('user_id', $this->filterUser);
        }

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('model_type', 'like', '%'.$this->search.'%')
                    ->orWhere('action', 'like', '%'.$this->search.'%');
            });
        }

        $this->totalRows = $query->count();

        $this->activities = $query
            ->orderByDesc('created_at')
            ->offset(($this->page - 1) * $this->perPage)
            ->limit($this->perPage)
            ->get()
            ->toArray();
    }

    public function updatedFilterAction(): void
    {
        $this->page = 1;
        $this->loadActivities();
    }

    public function updatedFilterUser(): void
    {
        $this->page = 1;
        $this->loadActivities();
    }

    public function updatedSearch(): void
    {
        $this->page = 1;
        $this->loadActivities();
    }

    public function previousPage(): void
    {
        if ($this->page > 1) {
            $this->page--;
            $this->loadActivities();
        }
    }

    public function nextPage(): void
    {
        if (($this->page * $this->perPage) < $this->totalRows) {
            $this->page++;
            $this->loadActivities();
        }
    }

    public function getTotalPages(): int
    {
        return (int) ceil($this->totalRows / $this->perPage);
    }

    public function getActionColor(string $action): string
    {
        return match ($action) {
            'created' => 'success',
            'updated' => 'info',
            'deleted' => 'error',
            default => 'ghost',
        };
    }
}; ?>

<div>
    <div class="mb-6">
        <h1 class="text-2xl font-bold">Activity Log</h1>
        <p class="text-base-content/60 mt-1">Track all system activities</p>
    </div>

    <!-- Filters -->
    <div class="card bg-base-100 shadow-sm mb-6">
        <div class="card-body p-4">
            <div class="flex flex-wrap gap-4">
                <div class="form-control flex-1 min-w-[200px]">
                    <input type="text" wire:model.live.debounce.300ms="search" class="input input-bordered input-sm" placeholder="Search..." />
                </div>
                <div class="form-control">
                    <select wire:model.live="filterAction" class="select select-bordered select-sm">
                        <option value="">All Actions</option>
                        <option value="created">Created</option>
                        <option value="updated">Updated</option>
                        <option value="deleted">Deleted</option>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <!-- Activities -->
    <div class="card bg-base-100 shadow-sm">
        <div class="overflow-x-auto">
            <table class="table table-sm">
                <thead>
                    <tr>
                        <th>Action</th>
                        <th>Model</th>
                        <th>User</th>
                        <th>Time</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($activities as $activity)
                        <tr>
                            <td>
                                <span class="badge badge-{{ $this->getActionColor($activity->action) }} badge-sm">
                                    {{ $activity->action }}
                                </span>
                            </td>
                            <td class="text-sm">
                                <span class="font-medium">{{ class_basename($activity->model_type) }}</span>
                                <span class="opacity-50">#{{ $activity->model_id }}</span>
                            </td>
                            <td class="text-sm opacity-60">{{ $activity->user_id ?? 'System' }}</td>
                            <td class="text-sm opacity-60">{{ $activity->created_at }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="text-center py-8 opacity-50">No activities found</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($totalRows > $perPage)
            <div class="card-body p-4 border-t border-base-200">
                <div class="flex items-center justify-between">
                    <span class="text-sm opacity-60">
                        Showing {{ ($page - 1) * $perPage + 1 }}-{{ min($page * $perPage, $totalRows) }} of {{ $totalRows }}
                    </span>
                    <div class="join">
                        <button wire:click="previousPage" class="join-item btn btn-sm" {{ $page <= 1 ? 'disabled' : '' }}>«</button>
                        <span class="join-item btn btn-sm btn-disabled">{{ $page }} / {{ $this->getTotalPages() }}</span>
                        <button wire:click="nextPage" class="join-item btn btn-sm" {{ $page >= $this->getTotalPages() ? 'disabled' : '' }}>»</button>
                    </div>
                </div>
            </div>
        @endif
    </div>
</div>
